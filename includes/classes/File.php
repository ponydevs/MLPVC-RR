<?php

namespace App;

class File {
	/**
	 * @param string $name
	 * @param mixed $data
	 *
	 * @return int|bool Number of bytes that written or false on failure
	 */
	public static function put(string $name, $data){
		$bytes = file_put_contents($name, $data);
		if ($bytes === false)
			return false;

		self::chmod($name);
		return $bytes;
	}

	/**
	 * @param string $name
	 *
	 * @return string|bool The read data or false on failure
	 */
	public static function get(string $name){
		return file_get_contents($name);
	}

	public static function chmod(string $name){
		return chmod($name, 0770);
	}
}
