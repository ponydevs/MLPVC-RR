<?php

	// Database Access Info \\
	define('DB_HOST','');
	define('DB_USER','');
	define('DB_PASS','');
	define('DB_NAME','');

	// dA API Codes \\
	define('DA_CLIENT','');
	define('DA_SECRET','');

	// Episode Download Site \\
	define('EP_DL_SITE','http://domain.tld/page.php');

	// Google Analytics Tracking Code \\
	define('GA_TRACKING_CODE','');

	// Get latest commit version & time from Git \\
	define('LATEST_COMMIT_ID',shell_exec("git rev-parse --short HEAD"));
	define('LATEST_COMMIT_TIME',date('c',strtotime(shell_exec("git log -1 --date=short --pretty=format:%ci"))));