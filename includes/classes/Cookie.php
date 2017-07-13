<?php

namespace App;

class Cookie {
	const SESSION = 0;
	const HTTPONLY = true;

	public static function exists($name){
		return isset($_COOKIE[$name]);
	}

	public static function get($name){
		return $_COOKIE[$name] ?? null;
	}

	public static function set($name, $value, $expires, $httponly = false, $path = '/'){
		$success = setcookie($name, $value, $expires, $path, $_SERVER['HTTP_HOST'], true, $httponly);
		if ($success)
			$_COOKIE[$name] = $value;
		return $success;
	}

	public static function delete($name, $httponly, $path = '/'){
		$retval = setcookie($name, '', time() - 3600, $path, $_SERVER['HTTP_HOST']);

		unset($_COOKIE[$name]);
		return $retval;
	}
}
