<?php

	// Database Access Info \\
	define('DB_HOST','');
	define('DB_USER','');
	define('DB_PASS','');
	define('DB_NAME','');

	// dA API Codes \\
	define('DA_CLIENT','');
	define('DA_SECRET','');

	// Google Analytics Tracking Code \\
	define('GA_TRACKING_CODE','');

	// Get latest commit version & time from Git \\
	define('LATEST_COMMIT_ID',shell_exec("git rev-parse --short=4 HEAD"));
	define('LATEST_COMMIT_TIME',date('c',strtotime(shell_exec("git log -1 --date=short --pretty=format:%ci"))));

	// Use For Window, without shell_exec and/or git \\
	/* COMMENT OUT THE SECTION ABOVE AND UNCOMMENT THIS TO USE
	$gitfpath = dirname(__FILE__).'/../.git/refs/heads/master';
	define('LATEST_COMMIT_ID',substr(file_get_contents($gitfpath),0,7).'-local');
	define('LATEST_COMMIT_TIME',date('c',filemtime($gitfpath)));
	unset($gitfpath);
	*/