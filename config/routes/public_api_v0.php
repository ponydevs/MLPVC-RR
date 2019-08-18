<?php

namespace App;

global $router;

/**
 * @file
 * List of API v0 endpoints meant for pre-release testing
 * These endpoints may change as needed until v1 is released
 */
\define('PUBLIC_API_V0_PATH', '/api/v0');
$public_api_endpoint = function ($path, $controller) use ($router) {
  $router->map('POST|GET|PUT|DELETE', PUBLIC_API_V0_PATH.$path, $controller);
};
$public_api_endpoint('/appearances', 'API\AppearancesController#queryPublic');
$public_api_endpoint('/appearances/all', 'API\AppearancesController#queryAll');
$public_api_endpoint('/appearances/[i:id]/sprite', 'API\AppearancesController#sprite');
$public_api_endpoint('/appearances/[i:id]/color-groups', 'API\AppearancesController#getColorGroups');
$public_api_endpoint('/users/me', 'API\UsersController#me');
$public_api_endpoint('/about/server', 'API\AboutController#server');
