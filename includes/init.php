<?php

// Autoload classes \\
$_dir = rtrim(__DIR__, '\/').DIRECTORY_SEPARATOR;
require $_dir.'test_init.php';

ini_set('error_log',PROJPATH.'mlpvc-rr-error.log');

use App\About;
use App\DB;
use App\PostgresDbWrapper;

// Maintenance mode \\
if (defined('MAINTENANCE_START')){
	$errcause = 'maintenance';
	die(require INCPATH.'views/fatalerr.php');
}

// Database connection & Required Functionality Checking \\
try {
	$inipath = 'in/to '.php_ini_loaded_file().' then restart '.About::getServerSoftware();
	if (About::iniGet('short_open_tag') !== true)
		throw new RuntimeException("Short open tags (&lt;?) are disabled\nUncomment/add the line <strong>short_open_tag=On</strong> $inipath to fix");
}
catch (Exception $e){
	$errcause = 'libmiss';
	die(require INCPATH.'views/fatalerr.php');
}

DB::$instance = new PostgresDbWrapper('mlpvc-rr');

try {
	$conn = \Activerecord\Connection::instance();
	DB::$instance->setConnection($conn->connection);
}
catch (Exception $e){
	$errcause = 'db';
	die(require INCPATH.'views/fatalerr.php');
}

header('Access-Control-Allow-Origin: '.(HTTPS?'http':'https').'://'.$_SERVER['SERVER_NAME']);
