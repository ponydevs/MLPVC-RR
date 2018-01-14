<?php

namespace App;

use App\Controllers\Controller;

class RouteHelper {
	public static function processHandler(string $handler):callable {
		return function($params) use ($handler){
			list($class, $method) = explode('#', $handler);
			$class = "App\\Controllers\\$class";
			$controller = new $class();
			if (false === $controller instanceof Controller)
				throw new \RuntimeException("$class must be an instance of ".Controller::class);
			$controller->{$method}($params);
		};
	}
}
