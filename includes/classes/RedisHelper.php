<?php

namespace App;

class RedisHelper {
	/**
	 * @type \Redis
	 */
	private static $_instance;
	private static $_connected;

	private static function _connect(){
		if (self::$_instance !== null)
			return self::$_instance;

		self::$_instance = new \Redis();
		self::$_connected = self::$_instance->connect(REDIS_HOST, REDIS_PORT);
		if (!self::$_connected)
			CoreUtils::error_log('Could not connect to Redis server on '.REDIS_HOST.':'.REDIS_PORT);
	}

	public static function get(string $key){
		if (self::$_connected === null)
			self::_connect();
		if (self::$_connected === false)
			return null;

		$result = self::$_instance->get($key);
		return $result === false ? null : $result;
	}

	public static function set(string $key, $value, int $ttl = 3600):?bool {
		if (self::$_connected === null)
			self::_connect();
		if (self::$_connected === false)
			return null;

		return self::$_instance->setex($key, $ttl, $value);
	}

	public static function del(...$keys):?int {
		if (self::$_connected === null)
			self::_connect();
		if (self::$_connected === false)
			return null;

		return self::$_instance->del(...$keys);
	}
}
