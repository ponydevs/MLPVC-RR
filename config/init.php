<?php

// Autoload classes \\
require __DIR__.'/init/minimal.php';
require __DIR__.'/init/kint.php';
require __DIR__.'/init/monolog.php';
require __DIR__.'/init/twig.php';

if (defined('CSP_ENABLED') && CSP_ENABLED === true){
	header('Content-Security-Policy: '.CSP_HEADER);
	header('X-Content-Security-Policy: '.CSP_HEADER);
	header('X-WebKit-CSP: '.CSP_HEADER);
}

use App\About;
use App\DB;
use App\PostgresDbWrapper;

function fatal_error(string $cause, ?Throwable $e = null){
	\App\HTTP::statusCode(503);
	if ($e !== null)
		\App\CoreUtils::error_log(__FILE__.": Fatal error of type $cause; ".$e->getMessage()."\nStack trace:\n".$e->getTraceAsString());
	$bc = new \App\NavBreadcrumb('Error');
	$bc->setChild(\App\HTTP::STATUS_CODES[503]);
	$scope = [
		'err_cause' => $cause,
		'breadcrumbs' => $bc,
		'css' => \App\CoreUtils::DEFAULT_CSS,
		'js' => \App\CoreUtils::DEFAULT_JS,
	];
	foreach ($scope['css'] as &$css)
		$css = \App\CoreUtils::cachedAssetLink($css, 'scss/min', 'css');
	unset($css);
	foreach ($scope['js'] as &$js)
		$js = \App\CoreUtils::cachedAssetLink($js, 'js/min', 'js');
	unset($js);
	echo \App\Twig::$env->render('error/fatal.html.twig', $scope);
	die();
}

// Maintenance mode \\
if (defined('MAINTENANCE_START'))
	fatal_error('maintenance');

// Database connection & Required Functionality Checking \\
try {
	$inipath = 'in/to '.php_ini_loaded_file().' then restart '.About::getServerSoftware().' and/or FPM';
	if (About::iniGet('short_open_tag') !== true)
		throw new RuntimeException("Short open tags (&lt;?) are disabled\nUncomment/add the line \"short_open_tag=On\" $inipath to fix");
}
catch (Exception $e){
	fatal_error('config', $e);
}

try {
	$conn = \Activerecord\Connection::instance();
	DB::$instance = PostgresDbWrapper::withConnection(DB_NAME, $conn->connection);
}
catch (Exception $e){
	fatal_error('db', $e);
}
