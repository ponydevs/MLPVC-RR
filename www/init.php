<?php

	include "conf.php";
	// Global constants \\
	define('ABSPATH','http://'.$_SERVER['SERVER_NAME'].'/');
	define('DROOT',$_SERVER['DOCUMENT_ROOT'].(preg_match('/\/$/',$_SERVER['DOCUMENT_ROOT'])?'':'/'));
	define('APPATH',dirname(__FILE__).DIRECTORY_SEPARATOR);
	define('RQMTHD',$_SERVER['REQUEST_METHOD']);
	define('REWRITE_REGEX','~^/(?:([\w\.\-]+|-?\d+)(?:/((?:[\w\-]+|-?\d+)(?:/(?:[\w\-]+|-?\d+))?))?/?)?$~');
	define('GITHUB_URL','https://github.com/ponydevs/MLPVC-RR');

	// Imports \\
	require 'includes/MysqliDbWrapper.php';
	$Database = new MysqliDbWrapper('mlpvc-rr');
	require 'includes/Cookie.php';
	require 'includes/Utils.php';
	require 'includes/AuthCheck.php';
