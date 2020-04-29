<?php

namespace App;

use App\Controllers\Controller;
use RuntimeException;

class RouteHelper {
  public static function processHandler(string $handler, $params):void {
    [$class, $method] = explode('#', $handler);
    $class = "App\\Controllers\\$class";
    $controller = new $class();
    if (false === $controller instanceof Controller)
      throw new RuntimeException("$class must be an instance of ".Controller::class);
    $controller->{$method}($params);
  }
}
