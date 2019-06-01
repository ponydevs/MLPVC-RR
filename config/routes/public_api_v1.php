<?php

namespace App;

global $router;

/**
 * @file
 * List of API v1 endpoints meant for general use
 * These endpoints must remain backwards compatible
 * If backwards compatibility is broken the major version must be bumped
 */
\define('PUBLIC_API_V1_PATH', '/api/v1');
$public_api_endpoint = function($path, $controller) use ($router){
	$router->map('POST|GET|PUT|DELETE', PUBLIC_API_V1_PATH.$path, $controller);
};
$public_api_endpoint('/appearances', 'API\AppearancesController#getAll');
$public_api_endpoint('/users/me', 'API\UsersController#getMe');
