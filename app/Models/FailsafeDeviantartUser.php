<?php

namespace App\Models;

/**
 * @inheritdoc
 */
class FailsafeDeviantartUser extends DeviantartUser {
  public static $connection = 'failsafe';

  public $id, $name, $role, $avatar_url;
}
