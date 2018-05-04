<?php

namespace App;

class CSRFProtection {
	protected static $_cookieKey = 'CSRF_TOKEN';
	public static $isForged;

	/**
	 * Checks POSTed data for CSRF token validity
	 */
	public static function detect(){
		if (self::$isForged !== null || $_SERVER['REQUEST_METHOD'] === 'GET')
			return;

		self::$isForged = !isset($_REQUEST[self::$_cookieKey]) || !Cookie::exists(self::$_cookieKey) || $_REQUEST[self::$_cookieKey] !== Cookie::get(self::$_cookieKey);
		if (self::$isForged)
			Cookie::set(self::$_cookieKey,bin2hex(random_bytes(16)), Cookie::SESSION);
	}

	/**
	 * Blocks CSRF requests
	 */
	public static function protect(){
		self::detect();

		if (self::$isForged === true)
			HTTP::statusCode(401, AND_DIE);
	}

	/**
	 * Removes the CSRF query parameter (if any) from any URL
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	public static function removeParamFromURL($url){
		return rtrim(preg_replace(new RegExp(preg_quote(self::$_cookieKey, '~').'=[^&]+(&|$)'),'',$url),'?&');
	}
}
