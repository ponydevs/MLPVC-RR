<?php

// Autoload classes \\
$_dir = rtrim(__DIR__, '\/').DIRECTORY_SEPARATOR;
require $_dir.'test_init.php';

if (class_exists('Kint')){
	Kint::$app_root_dirs[PROJPATH] = 'PROJPATH';
	Kint::$app_root_dirs[INCPATH] = 'INCPATH';
	Kint::$app_root_dirs[FSPATH] = 'FSPATH';
	Kint::$app_root_dirs[APPATH] = 'APPATH';
	Kint::$mode_default = Kint::MODE_PLAIN;
	Kint::$aliases[] = 'sd';
	function sd(...$args){
	    Kint::dump(...$args);
	    exit;
	}
}

use App\UsefulLogger as Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

function monolog_setup(){
	global $logger;
	$formatter = new LineFormatter(LineFormatter::SIMPLE_FORMAT, LineFormatter::SIMPLE_DATE);
	$formatter->includeStacktraces(true);

	if (!defined('LOG_PATH'))
		throw new RuntimeException('The LOG_PATH constant is not defined, please add it to your conf.php file');

	$stream = new StreamHandler(PROJPATH.'logs/'.LOG_PATH);
	$stream->setFormatter($formatter);

	$logger = new Logger('logger');
	$logger->pushHandler($stream);

	$handler = new \App\GracefulErrorHandler($logger);
	$handler->registerErrorHandler([], false);
	$handler->registerExceptionHandler();
	$handler->registerFatalHandler();
}
if (!defined('DISABLE_MONOLOG'))
	monolog_setup();

use App\About;
use App\DB;
use App\PostgresDbWrapper;

// Maintenance mode \\
if (defined('MAINTENANCE_START')){
	$errcause = 'maintenance';
	die(require INCPATH.'views/error/fatal.php');
}

// Set new file & folder permissions
define('FILE_PERM', 0770);
define('FOLDER_PERM', 0770);
umask(0007);

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

header('Access-Control-Allow-Origin: '.(HTTPS?'http':'https').'://'.$_SERVER['SERVER_NAME']);
