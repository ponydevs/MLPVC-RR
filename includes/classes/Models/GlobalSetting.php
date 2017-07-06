<?php

namespace App\Models;

use ActiveRecord\Model;

/**
 * @property string $key
 * @property string $value
 * @method static GlobalSetting|GlobalSetting[] find(...$args)
 */
class GlobalSetting extends Model {
	static $table_name = 'global_settings';

	static $primary_key = 'key';
}
