<?php

namespace App\Models;

use ActiveRecord\Model;

/**
 * @property string $key
 * @property string $value
 * @method static GlobalSetting|GlobalSetting[] find(...$args)
 */
class GlobalSetting extends Model {
	public static $primary_key = 'key';
}
