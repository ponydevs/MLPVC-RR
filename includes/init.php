<?php

// Autoload classes \\
$_dir = rtrim(__DIR__, '\/').DIRECTORY_SEPARATOR;
require $_dir.'../vendor/autoload.php';

require $_dir.'constants.php';

use \App\About;
use \App\PostgresDbWrapper;

// Maintenance mode \\
if (defined('MAINTENANCE_START')){
	$errcause = 'maintenance';
	die(require INCPATH.'views/fatalerr.php');
}

// Database connection & Required Functionality Checking \\
try {
	$inipath = 'in/to '.php_ini_loaded_file().' then restart '.About::getServerSoftware();
	if (About::iniGet('short_open_tag') !== true)
		throw new Exception("Short open tags (&lt;?) are disabled\nUncomment/add the line <strong>short_open_tag=On</strong> $inipath to fix");
}
catch (Exception $e){
	$errcause = 'libmiss';
	die(require INCPATH.'views/fatalerr.php');
}
$Database = new PostgresDbWrapper('mlpvc-rr');
try {
	$Database->pdo();
}
catch (Exception $e){
	unset($Database);
	$errcause = 'db';
	die(require INCPATH.'views/fatalerr.php');
}

header('Access-Control-Allow-Origin: '.(HTTPS?'http':'https').'://'.$_SERVER['SERVER_NAME']);
