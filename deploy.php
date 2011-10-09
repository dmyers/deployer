<?php
/**
 * Deployer
 * 
 * Automatically syncs your servers with a git
 * repo automatically on push using hooks.
 * 
 * Logging file:
 *   /tmp/githubsync-log
 * You can tail this file while deploying or view
 * for debugging.
 * 
 * @author Envoa htttp://code.envoa.com/deployer
 */

/**
 * The repos to track git pushes from.
 * 
 * Optional File: (for dealing with CDN caching)
 *   Deployer goes and changes the string
 *   $$GITREV$$ with the revision which you can
 *   then link your static files with ?revision to
 *   break CDN caching if they allow query strings
 */
$repos = array(
	'project1'=>array(							# the repo name
		'master'=>array(						# the repo branch name
			'path'=>'/www/vhosts/domain.com/',	# the path where your code lives
			'file'=>'/includes/config.php'		# optional file
		)
	),
	'project2'=>array(
		'master'=>array(
			'path'=>'/www/vhosts/domain2.com/'
		)
	)
);

error_reporting(-1);
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


define('REQUEST_ID', substr(md5(microtime(true)), 0, 6));

function say ($str)
{
	static $log;
	
	if ($log === null) {
		$log = fopen('/tmp/githubsync-log', 'a');
	}
	
	fwrite($log, '[' . REQUEST_ID . ': ' . date('r') . '] ' . $str . "\n");
	fflush($log);
};

function run ()
{
	$args = func_get_args();
	$command = call_user_func_array('sprintf', $args) . " 2>&1";
	
	$fp = popen($command, 'r');
	
	say("running $command");
	
	$t = array();
	
	if (!$fp) {
		say("failed to run $command");
		exit;
	}
	
	while (!feof($fp)) {
		$line = rtrim(fgets($fp, 4096));
		$t[] = $line;
		say("  " . $line);
	}
	
	fclose($fp);
	
	return implode("\n", $t);
}

# locking mechanism, make sure we dont run multiple and conflict something or worse
$lockfile = '/tmp/githubsync';
$lockfp = fopen($lockfile, 'w');

if (!flock($lockfp, LOCK_EX | LOCK_NB)) {
	say("githubsync script is locked by another process, retrying");
	
	# retry
	$tries = 0;
	while (true) {
		$tries++;
		
		if ($tries > 4) {
			say("lockfile still locked after $tries tries, exiting");
			exit;
		}
		
		sleep(1);
		
		say("githubsync script still locked by another process, try #$tries, retrying");
		
		if (flock($lockfp, LOCK_EX | LOCK_NB)) {
			break;
		}
	}
}

$payload = isset($_POST['payload']) ? json_decode($_POST['payload']) : false;
if (!$payload) {
	say('invalid payload');
	exit;
}

$project = $payload->repository->name;

# make sure repository exists
if (!isset($repos[$project])) {
	say("project not found!");
	exit;
}

$config = $repos[$project];
say("repository: $project");

$branch = substr($payload->ref, strrpos($payload->ref, '/') + 1);

# make sure branch is valid
if (!isset($config[$branch])) {
	say("$project/$branch: branch $branch is not configured with script");
	exit;
}

$config = $config[$branch];

# make sure path exists
if (!is_dir($config['path'])) {
	say("$project/$branch: repository path does not exist, have you checked it out? " . $config['path']);
	exit;
}

$people = array();
foreach ($payload->commits as $commit) {
	$author = $commit->author->name;
	
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

$git = "cd {$config['path']} && git";

run("$git reset --hard HEAD");
run("$git clean -f");
run("$git pull origin $branch");
run("$git submodule init");
run("$git submodule update");
run("find /tmp -name '*.php'  -exec rm {} \;");

if ($config['file']) {
	$file = $config['path'] . $config['file'];
	
	# file must have the full path in it
	if (!file_exists($file)) {
		$file = $config['file'];
	}
	
	if (file_exists($file)) {
		$data = file_get_contents($file);
		$data = str_replace('$$GITREV$$', ($rev = mt_rand(1, time())), $data);
		file_put_contents($file, $data);
		
		say("updating repo revision to $rev");
	}
}

fclose($lockfp);
unlink($lockfile);


if (function_exist('clearstatcache')) {
	say('clearing stat cache');
	clearstatcache();
}

if (function_exist('apc_clear_cache')) {
	say('clearing APC cache');
	apc_clear_cache('opcode');
}

say("done\n");
