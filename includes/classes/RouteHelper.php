<?php

namespace App;

class RouteHelper {
	public static function processHandler(string $handler):callable {
		return function($params) use ($handler){
			list($class, $method) = explode('#', $handler);
			$class = "App\\Controllers\\$class";
			$controller = new $class();
			$controller->{$method}($params);
		};
	}
}
