<?php

namespace App;

use ActiveRecord\Model;
use PDO;
use RuntimeException;
use SeinopSys\PostgresDb;
use function count;
use function is_array;

class PostgresDbWrapper extends PostgresDb {
  public static function withConnection(PDO $PDO):PostgresDbWrapper {
    $instance = new self();
    $instance->setConnection($PDO);

    return $instance;
  }

  /**
   * Sets the output format to use the specified class with late property fetching for php-activerecord
   * Expects ModelName::class as the name argument, or the equivalent fully qualified model name.
   *
   * @param string $class_name Fully qualified class name
   *
   * @return self
   */
  public function setModel(string $class_name):self {
    if (!CoreUtils::startsWith($class_name, 'App\\'))
      $class_name = "App\\Models\\$class_name";
    if (!class_exists($class_name))
      throw new RuntimeException("The model $class_name does not exist");

    $this->setClass($class_name, PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE);

    return $this;
  }

  private array $non_existing_class_cache = [];

  /**
   * @inheritdoc
   */
  protected function execStatement($stmt, $reset = true) {
    $class_name = $this->tableNameToClassName();
    if ($class_name !== null && empty($this->non_existing_class_cache[$class_name])){
      try {
        $this->setModel($class_name);
      }
      catch (RuntimeException $e){
        $this->non_existing_class_cache[$class_name] = true;
      }
    }

    $exec_result = parent::execStatement($stmt, $reset);
    $is_array = is_array($exec_result);
    if ($is_array && count($exec_result) > 0)
      $check = $exec_result[0];
    else $check = $exec_result;

    if ($check instanceof Model){
      /** @var $exec_result Model|Model[] */
      if ($is_array){
        foreach ($exec_result as $el)
          $el->forceExisting(true);
      }
      else $exec_result->forceExisting(true);
    }

    return $exec_result;
  }
}
