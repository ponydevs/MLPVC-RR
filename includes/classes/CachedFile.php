<?php

namespace App;

class CachedFile {
	private const
		TYPE_ANY = 1,
		TYPE_JSON = 2,
		TYPE_LOCK = 3;
	/** @var string */
	private $_path;
	/** @var int */
	private $_max_age, $_type;
	/** @var bool */
	private $_gzip = false;
	/** @var callable */
	private $_expiry_check;

	/**
	 * @param string       $path    Path to the cache file
	 * @param int|callable $max_age How long until the file is considered expired (seconds). Set to -1 to never expire.
	 *                              Passing a callable will run that instead when the expires() method is called
	 */
	public function __construct(string $path, $max_age){
		$this->_path = $path;
		if (is_int($max_age))
			$this->_max_age = $max_age;
		else if (is_callable($max_age))
			$this->_expiry_check = $max_age;
		else throw new \InvalidArgumentException(__METHOD__.' $max_age should be int or callable, '.gettype($max_age).' given');
		$this->_guessType();
	}

	private function _guessType(){
		$pathParts = explode('.',$this->_path);
		$lastPart = strtolower(array_slice($pathParts, -1, 1)[0]);
		if ($lastPart === 'gz'){
			$this->_gzip = true;
			$ext = strtolower(array_slice($pathParts, -2, 1)[0]);
		}
		else $ext = $lastPart;
		switch ($ext){
			case 'json':
				$this->_type = self::TYPE_JSON;
			break;
			case 'lock':
				$this->_type = self::TYPE_LOCK;
				// Lock files cannot be compressed because they're empty
				$this->_gzip = false;
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
	 * @param string       $path
	 * @param int|callable $max_age
	 *
	 * @return self
	 */
	public static function init(string $path, $max_age):self {
		if (isset(self::$_CACHES[$path]))
			return self::$_CACHES[$path];

		return self::$_CACHES[$path] = new self($path, $max_age);
	}

	public function expired():bool {
		if ($this->_max_age !== null)
			return !file_exists($this->_path) || ($this->_max_age !== -1 && time()-filemtime($this->_path) > $this->_max_age);
		else return (bool)($this->_expiry_check)($this->_path);
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

		if ($this->_gzip)
			$data = gzencode($data, 9);

		CoreUtils::createUploadFolder($this->_path);
		return file_put_contents($this->_path, $data);
	}

	/**
	 * Set the file modification time to the current time
	 *
	 * @return bool Whether the change was successful
	 */
	public function bump(){
		CoreUtils::createUploadFolder($this->_path);
		if (!file_exists($this->_path)){
			if ($this->_type !== self::TYPE_LOCK)
				throw new \RuntimeException("Trying to bump non-existant non-lock file {$this->_path}, use ".__CLASS__.'->update instead!');
			return file_put_contents($this->_path, '') !== false;
		}
		else return filemtime($this->_path, time()) !== false;
	}

	/**
	 * Returns the data currently stored in the cache file
	 * Return value type can change based on file type
	 *
	 * @return mixed
	 */
	public function read(){
		$data = file_get_contents($this->_path);

		if ($this->_gzip)
			$data = gzdecode($data);

		switch ($this->_type){
			case self::TYPE_JSON:
				$data = JSON::decode($data);
			break;
			case self::TYPE_LOCK:
				$data = true;
			break;
		}

		return $data;
	}
}
