<?php

namespace App\Models;

use ActiveRecord\Model;
use ActiveRecord\RecordNotFound;
use function count;

class NSModel extends Model {
  public static function find(...$args) {
    if (isset($args[0]) && is_numeric($args[0]) && count($args) === 1){
      $id = (int)$args[0];
      if ($id < POSTGRES_INTEGER_MIN || $id > POSTGRES_INTEGER_MAX){
        return null;
      }
    }

    try {
      return parent::find(...$args);
    }
    catch (RecordNotFound $e){
      return null;
    }
  }
}
