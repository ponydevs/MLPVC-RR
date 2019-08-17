<?php

namespace App\Models;

use ActiveRecord\Model;
use ActiveRecord\RecordNotFound;

class NSModel extends Model {
  public static function find(...$args) {
    if (isset($args[0]) && is_numeric($args[0]) && \count($args) === 1){
      $id = \intval($args[0], 10);
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
