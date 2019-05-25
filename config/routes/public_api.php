<?php

namespace App;

global $router;

// Publicly available API methods
\define('PUBLIC_API_PATH', '/api/v1');
$public_api_endpoint = function($path, $controller) use ($router){
	$router->map('POST|GET|PUT|DELETE', PUBLIC_API_PATH.$path, $controller);
};
$public_api_endpoint('/appearances', 'API\AppearancesController#getAll');
