<?php

namespace App;

use InvalidArgumentException;
use RuntimeException;
use function array_slice;
use function gettype;
use function is_callable;
use function is_int;

class CachedFile {
  private const
    TYPE_ANY = 1,
    TYPE_JSON = 2,
    TYPE_LOCK = 3;
  /** @var string */
  private $path;
  /** @var int */
  private $max_age, $type;
  /** @var bool */
  private $gzip = false;
  /** @var callable */
  private $expiry_check;

  /**
   * @param string       $path    Path to the cache file
   * @param int|callable $max_age How long until the file is considered expired (seconds). Set to -1 to never expire.
   *                              Passing a callable will run that instead when the expires() method is called
   */
  public function __construct(string $path, $max_age) {
    $this->path = $path;
    if (is_int($max_age))
      $this->max_age = $max_age;
    else if (is_callable($max_age))
      $this->expiry_check = $max_age;
    else throw new InvalidArgumentException(__METHOD__.' $max_age should be int or callable, '.gettype($max_age).' given');
    $this->guessType();
  }

  private function guessType() {
    $path_parts = explode('.', $this->path);
    $last_part = strtolower(array_slice($path_parts, -1, 1)[0]);
    if ($last_part === 'gz'){
      $this->gzip = true;
      $ext = strtolower(array_slice($path_parts, -2, 1)[0]);
    }
    else $ext = $last_part;
    switch ($ext){
      case 'json':
        $this->type = self::TYPE_JSON;
      break;
      case 'lock':
        $this->type = self::TYPE_LOCK;
        // Lock files cannot be compressed because they're empty
        $this->gzip = false;
      break;
      default:
        $this->type = self::TYPE_ANY;
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
    if ($this->max_age !== null)
      return !file_exists($this->path) || ($this->max_age !== -1 && time() - filemtime($this->path) > $this->max_age);
    else return (bool)($this->expiry_check)($this->path);
  }

  /**
   * Overwrites the cache file with the provided data
   *
   * @param mixed $data Type depends on file type
   */
  public function update($data) {
    switch ($this->type){
      case self::TYPE_JSON:
        $data = JSON::encode($data);
      break;
    }

    CoreUtils::createFoldersFor($this->path);

    if ($this->gzip){
      $handle = gzopen($this->path, 'w9');
      gzwrite($handle, $data);
      gzclose($handle);
      File::chmod($this->path);
    }
    else File::put($this->path, $data);
  }

  /**
   * Set the file modification time to the current time
   *
   * @return bool Whether the change was successful
   */
  public function bump() {
    CoreUtils::createFoldersFor($this->path);
    if (!file_exists($this->path)){
      if ($this->type !== self::TYPE_LOCK)
        throw new RuntimeException("Trying to bump non-existant non-lock file {$this->path}, use ".__CLASS__.'->update instead!');

      return File::put($this->path, '') !== false;
    }
    else return touch($this->path) !== false;
  }

  /**
   * Returns the data currently stored in the cache file
   * Return value type can change based on file type
   *
   * @return mixed
   */
  public function read() {
    $data = $this->gzip
      ? CoreUtils::gzread($this->path)
      : File::get($this->path);

    switch ($this->type){
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
