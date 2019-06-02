<?php

namespace App;

global $router;

/**
 * @file
 * List of API v1 endpoints meant for general use
 * These endpoints must remain backwards compatible
 * If backwards compatibility is broken a new copy of this file shall be created with an increased major version number
 */
\define('PUBLIC_API_V1_PATH', '/api/v1');
$public_api_endpoint = function($path, $controller) use ($router){
	$router->map('POST|GET|PUT|DELETE', PUBLIC_API_V1_PATH.$path, $controller);
};
$public_api_endpoint('/appearances',  'API\AppearancesController#all');
$public_api_endpoint('/users/me',     'API\UsersController#me');
$public_api_endpoint('/about/server', 'API\AboutController#server');
