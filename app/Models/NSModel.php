<?php

namespace App\Models;

use ActiveRecord\DatabaseException;
use ActiveRecord\Model;
use ActiveRecord\RecordNotFound;

class NSModel extends Model {
  public static function find(...$args) {
    try {
      return parent::find(...$args);
    }
    catch (DatabaseException $e) {
      if (str_contains($e->getMessage(), 'Numeric value out of range')) {
        return null;
      }

      throw $e;
    }
    catch (RecordNotFound $e){
      return null;
    }
  }
}
