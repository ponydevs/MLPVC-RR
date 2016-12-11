<?php

namespace App;

class Cookie {
	const SESSION = 0;
	const HTTPONLY = true;

	static public function exists($name){
		return isset($_COOKIE[$name]);
	}

	static public function get($name){
		return $_COOKIE[$name] ?? null;
	}

	static public function set($name, $value, $expires, $httponly = false, $path = '/'){
		$success = setcookie($name, $value, $expires, $path, $_SERVER['HTTP_HOST'], true, $httponly);
		if ($success)
			$_COOKIE[$name] = $value;
		return $success;
	}

	static public function delete($name, $httponly, $path = '/'){
		$retval = false;
		$retval = setcookie($name, '', time() - 3600, $path, $_SERVER['HTTP_HOST']);

		unset($_COOKIE[$name]);
		return $retval;
	}
}
