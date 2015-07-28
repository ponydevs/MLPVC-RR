<?php

	// Database Access Info \\
	define('DB_HOST','');
	define('DB_USER','');
	define('DB_PASS','');

	// dA API Codes \\
	define('DA_CLIENT','');
	define('DA_SECRET','');

	// Google Analytics Tracking Code \\
	define('GA_TRACKING_CODE','');

	// Get latest commit version & time from Git \\
	define('LATEST_COMMIT_ID',rtrim(shell_exec("git rev-parse --short=4 HEAD")));
	define('IS_LATEST_COMMIT',strpos(shell_exec("git log -1 --oneline"), LATEST_COMMIT_ID) === 0);
	define('LATEST_COMMIT_TIME',date('c',strtotime(shell_exec("git log -1 --date=short --pretty=format:%ci"))));
