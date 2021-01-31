<?php

// Autoload classes \\
require __DIR__.'/init/minimal.php';
require __DIR__.'/init/monolog.php';
require __DIR__.'/init/kint.php';
require __DIR__.'/init/twig.php';

// Wait a bit if assets are still compiling
$lock_sleep = 1000e3;
while (file_exists(PROJPATH.$_ENV['NPM_BUILD_LOCK_FILE_PATH'])){
  usleep($lock_sleep);
  $lock_sleep = min(2000e3, $lock_sleep * 1.15);
}

use App\CoreUtils;
use App\HTTP;
use App\LibHelper;
use App\NavBreadcrumb;
use App\RedisHelper;
use App\Twig;

CoreUtils::outputCSPHeaders();

function fatal_error(string $cause, ?Throwable $e = null) {
  HTTP::statusCode(503);
  if ($e !== null)
    CoreUtils::logError(sprintf("%s: Fatal error of type $cause; %s\nStack trace:\n%s", __FILE__, $e->getMessage(), $e->getTraceAsString()));
  $bc = new NavBreadcrumb('Error');
  $bc->setChild(HTTP::STATUS_CODES[503]);
  $scope = [
    'err_cause' => $cause,
    'breadcrumbs' => $bc,
    'default_js' => true,
    'css' => CoreUtils::DEFAULT_CSS,
    'js' => CoreUtils::DEFAULT_JS,
  ];
  LibHelper::process($scope, [], CoreUtils::DEFAULT_LIBS);
  $scope['css'] = array_map(static function ($css) {
    return CoreUtils::cachedAssetLink($css, 'css', 'min.css');
  }, $scope['css']);
  $scope['js'] = array_map(static function ($js) {
    return CoreUtils::cachedAssetLink($js, 'js', 'min.js');
  }, $scope['js']);
  echo Twig::$env->render('error/fatal.html.twig', $scope);
  die();
}

// Redis is missing (again) \\
try {
  RedisHelper::getInstance();
}
catch (Throwable $e){
  fatal_error('config', $e);
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
