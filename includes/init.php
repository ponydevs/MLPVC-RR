<?php

// Autoload classes \\
$_dir = rtrim(__DIR__, '\/').DIRECTORY_SEPARATOR;
require $_dir.'init/minimal.php';
require $_dir.'init/kint.php';
require $_dir.'init/monolog.php';
unset($_dir);

if (defined('CSP_ENABLED') && CSP_ENABLED === true){
	header('Content-Security-Policy-Report-Only: '.CSP_HEADER);
	header('X-Content-Security-Policy-Report-Only: '.CSP_HEADER);
	header('X-WebKit-CSP-Report-Only: '.CSP_HEADER);
}

use App\About;
use App\DB;
use App\PostgresDbWrapper;

// Maintenance mode \\
if (defined('MAINTENANCE_START')){
	$errcause = 'maintenance';
	die(require INCPATH.'views/error/fatal.php');
}

// Database connection & Required Functionality Checking \\
try {
	$inipath = 'in/to '.php_ini_loaded_file().' then restart '.About::getServerSoftware().' and/or FPM';
	if (About::iniGet('short_open_tag') !== true)
		throw new RuntimeException("Short open tags (&lt;?) are disabled\nUncomment/add the line <strong>short_open_tag=On</strong> $inipath to fix");
}
catch (Exception $e){
	$errcause = 'libmiss';
	die(require INCPATH.'views/error/fatal.php');
}

try {
	$conn = \Activerecord\Connection::instance();
	DB::$instance = PostgresDbWrapper::withConnection(DB_NAME, $conn->connection);
}
catch (Exception $e){
	$errcause = 'db';
	die(require INCPATH.'views/error/fatal.php');
}
