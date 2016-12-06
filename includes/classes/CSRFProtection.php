<?php

namespace App;

class CSRFProtection {
	protected static $_cookieKey = 'CSRF_TOKEN';

	/**
	 * Checks POSTed data for CSRF token validity
	 */
	static function Detect(){
		$CSRF = !isset($_POST[self::$_cookieKey]) || !Cookie::Exists(self::$_cookieKey) || $_POST[self::$_cookieKey] !== Cookie::Get(self::$_cookieKey);
		if (!POST_REQUEST && $CSRF)
			Cookie::Set(self::$_cookieKey,md5(time()+rand()), Cookie::SESSION);
	}

	/**
	 * Blocks CSRF requests
	 *
	 * @param bool $return_as_bool Return the result of the check as a boolean
	 *
	 * @return null|bool
	 */
	static function Protect($return_as_bool = false){
		global $CSRF;
		$is_forged = isset($CSRF) && $CSRF;

		if ($return_as_bool === RETURN_AS_BOOL)
			return $is_forged;
		else if ($is_forged)
			HTTP::StatusCode(401, AND_DIE);
	}

	/**
	 * Removes the CSRF query parameter (if any) from any URL
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	static function RemoveParamFromURL($url){
		return rtrim(regex_replace(new RegExp(preg_quote(self::$_cookieKey).'=[^&]+(&|$)'),'',$url),'?&');
	}
}
