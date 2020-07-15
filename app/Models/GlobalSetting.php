<?php

namespace App\Models;

/**
 * @property string $name
 * @property string $val
 * @method static GlobalSetting find_by_name(string $name)
 */
class GlobalSetting extends NSModel {
  public static $table_name = 'settings';
}
