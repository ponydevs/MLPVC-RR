<?php

namespace App\Models;

/**
 * @property string $key
 * @property string $value
 * @method static GlobalSetting|GlobalSetting[] find(...$args)
 */
class GlobalSetting extends NSModel {
  public static $primary_key = 'key';
}
