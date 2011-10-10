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