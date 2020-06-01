<?php

namespace App;

class URL {
  /**
   * Makes an absolute URL HTTPS
   *
   * @param string $url
   *
   * @return string
   */
  public static function makeHttps($url) {
    return preg_replace('~^(https?:)?//~', 'https://', $url);
  }
}
