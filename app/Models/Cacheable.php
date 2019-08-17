<?php

namespace App\Models;

interface Cacheable {
  /**
   * Age of the object in seconds
   */
  public function getAge():int;

  /**
   * Returns whether the cache is expired
   */
  public function cacheExpired():bool;
}
