<?php

	define('COOKIE_SESSION',true);

	class Cookie {
		static public function exists($name){ return isset($_COOKIE[$name]); }
		static public function get($name){ return (isset($_COOKIE[$name]) ? $_COOKIE[$name] : null); }
		static public function set($name, $value, $expires = true, $path = '/', $domain = false){
			$retval = false;

			if ($domain === false)
				$domain = $_SERVER['HTTP_HOST'];

			if (is_numeric($expires))
				$expires += NOW;
			else if (is_string($expires)) $expires = strtotime($expires);
			else $expires = 0;

			$retval = setcookie($name, $value, $expires, $path, $domain);
			if ($retval) $_COOKIE[$name] = $value;

			return $retval;
		}

		static public function delete($name, $path = '/', $domain = false){
			$retval = false;
			if ($domain === false)
				$domain = $_SERVER['HTTP_HOST'];
			$retval = setcookie($name, '', NOW - 3600, $path, $domain);

			unset($_COOKIE[$name]);
			return $retval;
		}
	}
