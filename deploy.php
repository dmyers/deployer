<?php
/**
 * Deployer
 * 
 * Automatically syncs your servers with a git
 * repo automatically on push using hooks.
 * 
 * You can tail this file while deploying or view
 * for debugging.
 * 
 * @author Envoa htttp://code.envoa.com/deployer
 */

error_reporting(-1);

define('REQUEST_ID', substr(md5(microtime(true)), 0, 6));

define('LOG_PRINT_INSTEAD', isset($printlog) ? $printlog : false);

define('LOGFILE', isset($logfile) ? $logfile : '/tmp/deployer.log');
define('LOCKFILE', isset($lockfile) ? $lockfile : '/tmp/deployer.lock');

function say ($raw, $skipPre = false)
{
	static $fp, $printed;
	
	$pre = '[' . REQUEST_ID . ': ' . date('r') . '] ';
	$str = array();

	foreach (explode("\n", $raw) as $i=>$line) {
		$str[] = ($i === 0 && !$skipPre ? $pre : str_repeat(' ', strlen($pre))) . rtrim($line);
	}

	$str = implode("\n", $str) . "\n";

	if (LOG_PRINT_INSTEAD) {
		if (!$printed) {
			echo '<pre>';
			$printed = true;
		}

		echo $str;
		ob_flush();
		flush();
		return true;
	}

	# logging mode
	if ($fp === null) {
		$fp = fopen(LOGFILE, 'a');
		if (!$fp) {
			echo "failed to open logfile";
			exit(1);
		}
	}
	
	fwrite($fp, $str);
	fflush($fp);
};

function run ()
{
	$args = func_get_args();
	$command = call_user_func_array('sprintf', $args) . " 2>&1";
	
	$fp = popen($command, 'r');
	
	say("$ $command");
	
	$t = array();
	
	if (!$fp) {
		say("failed to run $command");
		exit;
	}
	
	while (!feof($fp)) {
		$line = rtrim(fgets($fp, 4096));
		$t[] = $line;
		say("  " . $line, true);
	}
	
	fclose($fp);
	
	return implode("\n", $t);
}

function element ($array, $k, $def = null)
{
	return array_key_exists($k, $array ? : array()) ? $array[$k] : $def;
}

function get ($k, $d = null)
{
	return element($_GET, $k, $d);
}

function post ($k, $d = null)
{
	return element($_POST, $k, $d);
}

set_error_handler(function ($no, $str, $file, $line) {
	$types = array(
		E_ERROR=>'ERROR',
		E_WARNING=>'WARNING',
		E_PARSE=>'PARSING ERROR',
		E_NOTICE=>'NOTICE',
		E_CORE_ERROR=>'CORE ERROR',
		E_CORE_WARNING=>'CORE WARNING',
		E_COMPILE_ERROR=>'COMPILE ERROR',
		E_COMPILE_WARNING=>'COMPILE WARNING',
		E_USER_ERROR=>'USER ERROR',
		E_USER_WARNING=>'USER WARNING',
		E_USER_NOTICE=>'USER NOTICE',
		E_STRICT=>'STRICT NOTICE',
		E_RECOVERABLE_ERROR=>'RECOVERABLE ERROR'
	);
	
	say($types[$no] . ': ' . $str . ' ' . basename($file) . '#' . $line);
});

say("Deployer rev: {{GITREVISION}}");

# make sure config has been loaded
if (!isset($repos)) {
	say('$repos not loaded!');
	exit(1);
}

# locking mechanism, make sure we dont run multiple and conflict something or worse
$lockfp = fopen(LOCKFILE, 'w');

say('aquiring lock (' . LOCKFILE . ') ...');
if (!$lockfp) {
	say('failed to fopen lockfile');
	exit(1);
}

if (!flock($lockfp, LOCK_EX | LOCK_NB)) {
	say('deployer is locked by another process, retrying');
	
	# retry
	$tries = 0;
	while (true) {
		$tries++;
		
		if ($tries > 4) {
			say("lockfile still locked after $tries tries, exiting");
			exit(1);
		}
		
		sleep(1);
		
		say("deployer still locked by another process, try #$tries, retrying");
		
		if (flock($lockfp, LOCK_EX | LOCK_NB)) {
			break;
		}
	}
}

say('lock aquired');

say('=== env ===');
say('method: ' . $_SERVER['REQUEST_METHOD']);
say('raw get: ' . print_r($_GET, true));
say('raw post: ' . print_r($_POST, true));
say('=== env ===');

$payload = post('payload');
$source = get('source');

$force = get('force');
if ($force) {
	@list ($project, $branch) = explode('/', $force);
	$source = 'github';
	$payload = json_encode(array('ref'=>$force, 'repository'=>array('name'=>$project), 'commits'=>array()));
}

$payload = $payload ? json_decode($payload) : false;

# make sure its a valid source
if (!in_array($source, array('bitbucket', 'github'))) {
	say('invalid source');
	exit(1);
}

$bitbucket = $source == 'bitbucket';
$github = $source == 'github';

if (!$payload) {
	say('invalid payload');
	exit(1);
}

$project = $payload->repository->name;

$config = element($repos, $project);

# make sure repository exists
if (!$config) {
	say('project not found!');
	exit(1);
}


# get the branch name
if ($github) {
	$branch = substr($payload->ref, strrpos($payload->ref, '/') + 1);
} else if ($bitbucket) {
	foreach ($payload->commits as $commit) {
		if ($commit->branch) {
			$branch = $commit->branch;
			break;
		}
	}
}


say("source: $source");
say("repository: $project");
say("branch: $branch");

$config = element($config, $branch);

# make sure branch is valid
if (!$config) {
	say("$project/$branch: branch $branch is not configured with script");
	exit(1);
}

say("project branch config: " . print_r($config, true));

$path = element($config, 'path');
$replaceRev = element($config, 'replace_rev', true);
$randomRev = element($config, 'random_rev', false);
$timeRev = element($config, 'time_rev', false);

if (!$path) {
	say("project path not defined");
	exit(1);
}

# make sure path exists
if (!is_dir($path)) {
	say("project dir not a dir");
	exit(1);
}

# extract commiters and print a nice message
$people = array();
foreach ($payload->commits as $commit) {
	if ($bitbucket) {
		$author = $commit->author;
	} else if ($github) {
		$author = $commit->author->name;
	}
	
	if (isset($people[$author])) {
		$people[$author]++;
	} else {
		$people[$author] = 1;
	}
}

$total = array_sum($people);

foreach ($people as $name=>$commits) {
	$people[$name] = $name . ' ' . $commits . ' commit' . ($commits > 1 ? 's' : '');
}

$people = implode(', ', $people);

say("$project/$branch commits ($total): " . $people);

$git = "cd {$path} && git";

# clean repository of local changes
run("$git reset --hard HEAD");
run("$git clean -xdf");

# fetch remote changes
run("$git pull --rebase origin $branch");

# clean submodules
run("$git submodule foreach git reset --hard HEAD");
run("$git submodule foreach git clean -xdf");

# initialize submodules not initialized and pull the code
run("$git submodule init");
run("$git submodule update");

# replace revision
if ($replaceRev) {
	if ($randomRev) {
		$replace = mt_rand(1, mt_getrandmax());
	} else if ($timeRev) {
		$replace = time();
	} else {
		$replace = trim(`$git rev-parse HEAD`);
	}

	run("find {$path} | grep -e '\.php$' -e '\.css$' -e '\.js$' | xargs perl -pi -e 's/{{GITREVISION}}/" . $replace . "/'");
}

# clear some cached stuff

# remove any temporary files
say('clearing temporary files');
run("find /tmp -name '*.php'  -exec rm {} \;");

if (function_exists('clearstatcache')) {
	say('clearing stat cache');
	clearstatcache();
}

if (function_exists('apc_clear_cache')) {
	say('clearing APC cache');
	apc_clear_cache('opcode');
}

say('unlocking');
fclose($lockfp);

say("done\n");