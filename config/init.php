<?php

// Autoload classes \\
require __DIR__.'/init/minimal.php';
require __DIR__.'/init/kint.php';
require __DIR__.'/init/monolog.php';
require __DIR__.'/init/twig.php';

if ($_ENV['CSP_ENABLED'] === 'true'){
	$csp_header = implode(';', [
		"default-src {$_ENV['CSP_DEFAULT_SRC']}",
		"script-src {$_ENV['CSP_SCRIPT_SRC']} {$_ENV['WS_SERVER_HOST']} 'nonce-".CSP_NONCE."'",
		"object-src {$_ENV['CSP_OBJECT_SRC']}",
		"style-src {$_ENV['CSP_STYLE_SRC']}",
		"img-src {$_ENV['CSP_IMG_SRC']}",
		"manifest-src {$_ENV['CSP_MANIFEST_SRC']}",
		"media-src {$_ENV['CSP_MEDIA_SRC']}",
		"frame-src {$_ENV['CSP_FRAME_SRC']}",
		"font-src {$_ENV['CSP_FONT_SRC']}",
		"connect-src 'self' {$_ENV['WS_SERVER_HOST']} wss://{$_ENV['WS_SERVER_HOST']}",
		"report-uri {$_ENV['CSP_CONNECT_SRC']}",
	]);
	header("Content-Security-Policy: $csp_header");
	header("X-Content-Security-Policy: $csp_header");
	header("X-WebKit-CSP: $csp_header");
	unset($csp_header);
}

use App\About;

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
if (isset($_ENV['MAINTENANCE_START']))
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
	require __DIR__.'/init/db_class.php';
}
catch (Exception $e){
	fatal_error('db', $e);
}
