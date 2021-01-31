<?php

declare(strict_types=1);

namespace App;

class File {
  /**
   * @param string $name
   * @param string $data
   *
   * @return int|bool Number of bytes that written or false on failure
   */
  public static function put(string $name, string $data) {
    $bytes = file_put_contents($name, $data);
    if ($bytes === false)
      return false;

    self::chmod($name);

    return $bytes;
  }

  /**
   * @param string $name
   *
   * @return string|bool The read data or false on failure
   */
  public static function get(string $name) {
    return file_get_contents($name);
  }

  /**
   * @param string $name
   *
   * @return bool True on success, false on failure
   */
  public static function chmod(string $name):bool {
    // @ required to avoid permission error messages causing premature output in local dev
    $result = @chmod($name, FILE_PERM);
    if ($result !== true){
      CoreUtils::logError(__METHOD__.": Fail for file $name");
      $result = false;
    }

    return $result;
  }
}
