<?php

// Autoload classes \\
$_dir = rtrim(dirname(__FILE__), '\/').DIRECTORY_SEPARATOR;
require $_dir.'../vendor/autoload.php';

require $_dir.'constants.php';

use \App\About;
use \App\PostgresDbWrapper;
use \App\RegExp;

// Maintenance mode \\
if (defined('MAINTENANCE_START')){
	$errcause = 'maintenance';
	die(require INCPATH."views/fatalerr.php");
}

// Database connection & Required Functionality Checking \\
try {
	if (PHP_OS === 'WINNT')
		$inipath = 'in/to '.php_ini_loaded_file().' then restart '.About::getServerSoftware();
	if (About::iniGet('short_open_tag') !== true)
		throw new Exception("Short open tags (&lt;?) are disabled\nUncomment/add the line <strong>short_open_tag=On</strong> $inipath to fix");
	if (!function_exists('curl_init'))
		throw new Exception("cURL extension is disabled or not installed\n".(PHP_OS !== 'WINNT' ? "Run <strong>sudo apt-get install php7.0-curl</strong>" : "Uncomment/add the line <strong>extension=php_curl.dll</strong> $inipath").' to fix');
	if (!function_exists('imagecreatefrompng'))
		throw new Exception("GD extension is disabled or not installed".(PHP_OS !== 'WINNT' ? "\nRun <strong>sudo apt-get install php7.0-gd</strong> to fix" : ""));
	if (!class_exists('DOMDocument', false))
		throw new Exception("XML extension is disabled or not installed".(PHP_OS !== 'WINNT' ? "\nRun <strong>sudo apt-get install php7.0-xml</strong> to fix" : ''));
	if (!function_exists('mb_substr') || !function_exists('mb_strlen'))
		throw new Exception("mbstring extension is disabled or not installed".(PHP_OS !== 'WINNT' ? "\nRun <strong>sudo apt-get install php7.0-mbstring</strong> to fix" : ''));
	if (!function_exists('pdo_drivers'))
		throw new Exception("PDO extension is disabled or not installed\nThe site requires PHP 7.0+ to function, please upgrade your server.");
	if (!in_array('pgsql', pdo_drivers()))
		throw new Exception("PostgreSQL PDO extension is disabled or not installed\n".(PHP_OS !== 'WINNT' ? "Run <strong>sudo apt-get install php7.0-pgsql</strong>" : "Uncomment/add the line <strong>extension=php_pdo_pgsql.dll</strong> $inipath").' to fix');
}
catch (Exception $e){
	$errcause = 'libmiss';
	die(require INCPATH."views/fatalerr.php");
}
$Database = new PostgresDbWrapper('mlpvc-rr');
try {
	$Database->pdo();
}
catch (Exception $e){
	unset($Database);
	$errcause = 'db';
	die(require INCPATH."views/fatalerr.php");
}

header('Access-Control-Allow-Origin: '.(HTTPS?'http':'https').'://'.$_SERVER['SERVER_NAME']);
