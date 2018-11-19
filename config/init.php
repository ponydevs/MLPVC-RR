<?php

// Autoload classes \\
require __DIR__.'/init/minimal.php';
require __DIR__.'/init/kint.php';
require __DIR__.'/init/monolog.php';
require __DIR__.'/init/twig.php';

use App\About;
use App\CoreUtils;

if (CoreUtils::env('CSP_ENABLED')){
	$csp_header = implode(';', [
		'default-src '.CoreUtils::env('CSP_DEFAULT_SRC'),
		'script-src '.CoreUtils::env('CSP_SCRIPT_SRC').' '.CoreUtils::env('WS_SERVER_HOST')." 'nonce-".CSP_NONCE."'",
		'object-src '.CoreUtils::env('CSP_OBJECT_SRC'),
		'style-src '.CoreUtils::env('CSP_STYLE_SRC'),
		'img-src '.CoreUtils::env('CSP_IMG_SRC'),
		'manifest-src '.CoreUtils::env('CSP_MANIFEST_SRC'),
		'media-src '.CoreUtils::env('CSP_MEDIA_SRC'),
		'frame-src '.CoreUtils::env('CSP_FRAME_SRC'),
		'font-src '.CoreUtils::env('CSP_FONT_SRC'),
		"connect-src 'self' ".CoreUtils::env('WS_SERVER_HOST').' wss://'.CoreUtils::env('WS_SERVER_HOST'),
		'report-uri '.CoreUtils::env('CSP_CONNECT_SRC'),
	]);
	header("Content-Security-Policy: $csp_header");
	header("X-Content-Security-Policy: $csp_header");
	header("X-WebKit-CSP: $csp_header");
	unset($csp_header);
}

// Wait a bit if assets are still compiling
$lock_sleep = 1000e3;
while (file_exists(PROJPATH.$_ENV['NPM_BUILD_LOCK_FILE_PATH'])){
	usleep($lock_sleep);
	$lock_sleep *= 1.5;
}

function fatal_error(string $cause, ?Throwable $e = null){
	\App\HTTP::statusCode(503);
	if ($e !== null)
		CoreUtils::error_log(__FILE__.": Fatal error of type $cause; ".$e->getMessage()."\nStack trace:\n".$e->getTraceAsString());
	$bc = new \App\NavBreadcrumb('Error');
	$bc->setChild(\App\HTTP::STATUS_CODES[503]);
	$scope = [
		'err_cause' => $cause,
		'breadcrumbs' => $bc,
		'default_js' => true,
		'css' => CoreUtils::DEFAULT_CSS,
		'js' => CoreUtils::DEFAULT_JS,
	];
	\App\LibHelper::process($scope, [], CoreUtils::DEFAULT_LIBS);
	foreach ($scope['css'] as &$css)
		$css = CoreUtils::cachedAssetLink($css, 'css', 'min.css');
	unset($css);
	foreach ($scope['js'] as &$js)
		$js = CoreUtils::cachedAssetLink($js, 'js', 'min.js');
	unset($js);
	echo \App\Twig::$env->render('error/fatal.html.twig', $scope);
	die();
}

// Maintenance mode \\
if (CoreUtils::env('MAINTENANCE_START'))
	fatal_error('maintenance');

// Database connection \\
try {
	require __DIR__.'/init/db_class.php';
}
catch (Exception $e){
	fatal_error('db', $e);
}
