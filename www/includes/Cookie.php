<?php
	if (!defined('COOKIE_SESSION')) define('COOKIE_SESSION', false);

	class Cookie {
		/**
		 * Delete a cookie
		 *
		 * @param string $name
		 * @param bool $subdomainOnly
		 * @return If
		 */
		public static function delete($name, $subdomainOnly = true) {
			if (isset($_COOKIE[$name])) {
				unset($_COOKIE[$name]);
				return self::set($name, "", time() - 3600, $subdomainOnly);
			}
			return true;
		}

		/**
		 * Get the value of a cookie if it exists
		 *
		 * @param string $name
		 * @return Value of cookie
		 */
		public static function get($name) {
			if (isset($_COOKIE[$name])) {
				return $_COOKIE[$name];
			} else return false;
		}
		
		
		public static function exists($name) {
			return isset($_COOKIE[$name]);
		}

		/**
		 * Create a cookie
		 *
		 * @param string $name
		 * @param string $value
		 * @param int $time
		 * @param bool $subdomainOnly
		 * @return If cookie is set
		 */
		public static function set($name, $value, $time = null, $subdomainOnly = true) {
			// Variables
			if (isset($time)){
				if ($time !== false) $_ttl = time() + $time;
				else $_ttl = 0;
			}
			else $_ttl = time() + THIRTY_DAYS;

			$success = setcookie($name, $value, $_ttl, "/", $subdomainOnly ? $_SERVER['SERVER_NAME'] : ".djdavid98.eu");
			if ($success) $_COOKIE[$name] = $value;
			return $success;
		}
	}
?>