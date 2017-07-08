<?php

namespace App;

class CachedFile {
	private const
		TYPE_ANY = 1,
		TYPE_JSON = 2;
	/** @var string */
	private $_path;
	/** @var int */
	private $_max_age, $_type;

	public function __construct(string $path, int $max_age){
		$this->_path = $path;
		$this->_max_age = $max_age;
		$this->_guessType();
	}

	private function _guessType(){
		$ext = strtolower(array_slice(explode('.',$this->_path), -1)[0]);
		switch ($ext){
			case 'json':
				$this->_type = self::TYPE_JSON;
			break;
			default:
				$this->_type = self::TYPE_ANY;
		}
	}

	/** @var self[] Stores instances of the object created by the init method */
	private static $_CACHES = [];

	/**
	 * Creates an instance and stores it in an internal array which it's returned from on consequent calls to save resources
	 *
	 * @param string $path    Path to the cache file
	 * @param int    $max_age How long until the file is considered expired (seconds)
	 *
	 * @return self
	 */
	public static function init(string $path, int $max_age):self {
		if (isset(self::$_CACHES[$path]))
			return self::$_CACHES[$path];

		return self::$_CACHES[$path] = new self($path, $max_age);
	}

	public function expired():bool {
		return !file_exists($this->_path) || time()-filemtime($this->_path) > $this->_max_age;
	}

	/**
	 * Overwrites the cache file with the provided data
	 *
	 * @param mixed $data Type depens on file type
	 *
	 * @return int|false Bytes written (false on failure)
	 */
	public function update($data){
		switch ($this->_type){
			case self::TYPE_JSON:
				$data = JSON::encode($data);
			break;
		}

		CoreUtils::createUploadFolder($this->_path);
		return file_put_contents($this->_path, $data);
	}

	/**
	 * Returns the data currently stored in the cache file
	 * Return value type can change based on file type
	 *
	 * @return mixed
	 */
	public function read(){
		$data = file_get_contents($this->_path);

		switch ($this->_type){
			case self::TYPE_JSON:
				$data = JSON::decode($data);
			break;
		}

		return $data;
	}
}
