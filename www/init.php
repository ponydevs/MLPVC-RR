<?php

	require 'conf.php';
	require 'includes/RegExp.php';

	// Global constants \\
	define('ABSPATH',(!empty($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['SERVER_NAME'].'/');
	define('APPATH',dirname(__FILE__).DIRECTORY_SEPARATOR);
	define('RQMTHD',$_SERVER['REQUEST_METHOD']);
	define('REWRITE_REGEX','');
	$REWRITE_REGEX = new RegExp('^/(?:([\w\.\-]+|-?\d+)(?:/((?:[\w\-]+|-?\d+)(?:/(?:[\w\-]+|-?\d+))?))?/?)?$','i');
	define('GITHUB_URL','https://github.com/ponydevs/MLPVC-RR');
	define('SITE_TITLE', 'MLP Vector Club');

	// Git commit information
	define('LATEST_COMMIT_ID',rtrim(shell_exec('git rev-parse --short=4 HEAD')));
	define('LATEST_COMMIT_TIME',date('c',strtotime(shell_exec('git log -1 --date=short --pretty=format:%ci'))));

	// Imports \\
	require 'includes/JSON.php';
	require 'includes/PostgresDbWrapper.php';
	$Database = new PostgresDbWrapper('mlpvc-rr');
	try {
		$Database->pdo();
	}
	catch (Exception $e){
		unset($Database);
		die(require APPATH."views/dberr.php");
	}
	$CGDb = new PostgresDbWrapper('mlpvc-colorguide');
	require 'includes/Cookie.php';
	require 'includes/Utils.php';
	require 'includes/AuthCheck.php';

	header('Access-Control-Allow-Origin: '.(!empty($_SERVER['HTTPS'])?'http':'https').'://'.$_SERVER['SERVER_NAME']);

	if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])){
		require 'includes/CloudFlare.php';
		if (CloudFlare::CheckUserIP())
			$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
	}
