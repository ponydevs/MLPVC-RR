<?php

namespace App;

use Redis;

class RedisHelper {
	/** @var Redis */
	private static $instance;
	/** @var bool */
	private static $connected;

	private static function _connect(){
		if (self::$instance !== null)
			return self::$instance;

		self::$instance = new Redis();
		self::$connected = self::$instance->connect($_ENV['REDIS_HOST'], $_ENV['REDIS_PORT']);
		if (!self::$connected)
			CoreUtils::error_log("Could not connect to Redis server on {$_ENV['REDIS_HOST']}:{$_ENV['REDIS_PORT']}");
	}

	public static function getInstance():?Redis {
		if (self::$connected === null)
			self::_connect();
		if (self::$connected === false)
			return null;

		return self::$instance;
	}

	public static function get(string $key){
		if (self::$connected === null)
			self::_connect();
		if (self::$connected === false)
			return null;

		$result = self::$instance->get($key);
		return $result === false ? null : $result;
	}

	public static function set(string $key, $value, ?int $ttl = 3600):?bool {
		if (self::$connected === null)
			self::_connect();
		if (self::$connected === false)
			return null;

		return self::$instance->setex($key, $ttl, $value);
	}

	public static function del(...$keys):?int {
		if (self::$connected === null)
			self::_connect();
		if (self::$connected === false)
			return null;

		return self::$instance->del(...$keys);
	}
}
