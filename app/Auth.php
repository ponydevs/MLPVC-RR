<?php

namespace App;

use App\Models\Session;
use App\Models\User;

/**
 * This class provides global access to some site-wide variables.
 * It's much more straight-forward and IDE friendly than the old approach using `global $var`
 */
class Auth {
  /** @var User|null Currently authenticated user (or null if guest) */
  public static ?User $user = null;

  /** @var Session Current session */
  public static ?Session $session = null;

  /** @var bool True if signed in, false if guest */
  public static bool $signed_in = false;

  public static function to_array():array {
    return [
      'current_user' => self::$user,
      'current_session' => self::$session,
      'signed_in' => self::$signed_in,
      'remote_addr' => $_SERVER['REMOTE_ADDR'],
    ];
  }
}
