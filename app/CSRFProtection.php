<?php

declare(strict_types=1);

namespace App;

class CSRFProtection {
  private const COOKIE_NAME = 'CSRF_TOKEN';
  public static $tripped;

  /**
   * Checks POSTed data for CSRF token validity
   */
  public static function detect():void {
    $cookie_missing = Cookie::missing(self::COOKIE_NAME);
    $get_request = $_SERVER['REQUEST_METHOD'] === 'GET';
    if (self::$tripped !== null || ($get_request && !$cookie_missing))
      return;

    if ($get_request)
      self::$tripped = false;
    else self::$tripped = !isset($_REQUEST[self::COOKIE_NAME]) || $cookie_missing || $_REQUEST[self::COOKIE_NAME] !== Cookie::get(self::COOKIE_NAME);
    if (self::$tripped || $cookie_missing)
      Cookie::set(self::COOKIE_NAME, bin2hex(random_bytes(16)));
  }

  /**
   * Blocks CSRF requests
   */
  public static function protect():void {
    self::detect();

    if (self::$tripped === true)
      HTTP::statusCode(401, AND_DIE);
  }

  /**
   * Removes the CSRF query parameter (if any) from any URL
   *
   * @param string $url
   *
   * @return string
   */
  public static function removeParamFromURL(string $url):string {
    $cookie_name = preg_quote(self::COOKIE_NAME, '~');
    return rtrim(preg_replace("/$cookie_name=[^&]+(&|$)/", '', $url), '?&');
  }
}
