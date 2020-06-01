<?php

require __DIR__.'/../config/init.php';

use App\CoreUtils;
use App\RouteHelper;

// Strip &hellip; and what comes after
$decoded_uri = CoreUtils::trim(urldecode($_SERVER['REQUEST_URI']));
$request_uri = preg_replace('/(?:….*|<)$/u', '', $decoded_uri);
// Strip non-ascii
$safe_uri = preg_replace('/[^ -~]/', '', $request_uri);
// Enforce URL
CoreUtils::fixPath($safe_uri);

require CONFPATH.'routes/index.php';
/** @var $match array */
$match = $router->match($safe_uri);
if (!isset($match['target']))
  CoreUtils::notFound();
RouteHelper::processHandler($match['target'], $match['params']);

