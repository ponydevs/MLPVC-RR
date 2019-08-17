<?php

declare(strict_types=1);

namespace App;

class Cookie {
  public const SESSION = 0;
  public const HTTP_ONLY = true;

  public static function exists(string $name):bool {
    return isset($_COOKIE[$name]);
  }

  public static function missing(string $name):bool {
    return !self::exists($name);
  }

  public static function get(string $name):string {
    return $_COOKIE[$name];
  }

  public static function set(string $name, string $value, int $expire = self::SESSION, bool $http_only = false, string $path = '/'):bool {
    $success = setcookie($name, $value, [
      'expires' => $expire,
      'path' => $path,
      'domain' => $_SERVER['HTTP_HOST'],
      'secure' => HTTPS,
      'httponly' => $http_only,
      'samesite' => 'Lax',
    ]);
    if ($success)
      $_COOKIE[$name] = $value;

    return $success;
  }

  public static function delete(string $name, bool $http_only = false, string $path = '/'):bool {
    $success = setcookie($name, '', [
      'expires' => time() - 3600,
      'path' => $path,
      'domain' => $_SERVER['HTTP_HOST'],
    ]);
    if ($success)
      unset($_COOKIE[$name]);

    return $success;
  }
}
