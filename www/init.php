<?php

	require 'conf.php';

	// Global constants \\
	define('ABSPATH',(!empty($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['SERVER_NAME'].'/');
	define('APPATH',dirname(__FILE__).DIRECTORY_SEPARATOR);
	define('RQMTHD',$_SERVER['REQUEST_METHOD']);
	define('REWRITE_REGEX','~^/(?:([\w\.\-]+|-?\d+)(?:/((?:[\w\-]+|-?\d+)(?:/(?:[\w\-]+|-?\d+))?))?/?)?$~');
	define('GITHUB_URL','https://github.com/ponydevs/MLPVC-RR');
	define('SITE_TITLE', 'Vector Club Requests & Reservations');

	// Imports \\
	require 'includes/PostgresDbWrapper.php';
	$Database = new PostgresDbWrapper('mlpvc-rr');
	require 'includes/Cookie.php';
	require 'includes/Utils.php';
	require 'includes/AuthCheck.php';

	if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])){
		require 'includes/CloudFlare.php';
		if (CloudFlare::CheckUserIP())
			$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
	}
