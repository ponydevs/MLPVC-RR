<?php

namespace App\Models;

/**
 * @property string $key
 * @property string $val
 * @method static GlobalSetting|GlobalSetting[] find(...$args)
 */
class GlobalSetting extends NSModel {
  public static $primary_key = 'key';

  public static $table_name = 'settings';
}
