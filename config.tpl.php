<?php
/**
 * Deployer
 * 
 * Automatically syncs your servers with a git
 * repo automatically on push using hooks.
 * 
 * Logging file:
 *   /tmp/deployer.log
 * You can tail this file while deploying or view
 * for debugging.
 * 
 * @author Envoa htttp://code.envoa.com/deployer
 */

/**
 * The repos to track git pushes from.
 * 
 * A recursive replacement will be done for {{GITREVISION}} 
 *   inside every .php, .js, .html, .css for the branch's git rev
 *   
 *   Link your static files with ?revision to
 *   break CDN caching if they allow query strings
 *
 *		path = project path
 *		replace_rev boolean, default true	If true, {{ GITREVISION }} will be replaced
 *		
 *		random_rev boolean, default false	Generate a random number as revision
 *		time_rev boolean, default false	Generate a random
 */

$repos = array(
	'project1'=>array(
		'master'=>array(
			'path'=>'/www/vhosts/dev.project1.com/',
		),

		'prod'=>array(
			'path'=>'/www/vhosts/project1.com',
			'replace_rev'=>false
		)
	),

	'project2'=>array(
		'master'=>array(
			'path'=>'/www/vhosts/project2.com/',
			'random_rev'=>true
		)
	)
);

# optional variables, value in config are their default values

# print log instead of logfile
$printlog = false;

# custom log file
$logfile = '/tmp/deployer.log';

# custom lockfile
$lockfile = '/tmp/deployer.lock';