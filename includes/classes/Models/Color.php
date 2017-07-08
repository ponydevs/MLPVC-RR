<?php

namespace App\Models;

use ActiveRecord\Model;

/**
 * @property int $group_id
 * @property int $order
 * @property string $label
 * @property string $hex
 * @property ColorGroup $color_group
 * @method static Color|Color[] find(...$args)
 */
class Color extends Model {
	public static $primary_key = ['group_id', 'order'];

	public static $belongs_to = [
		['color_group', 'foreign_key' => 'grooup_id']
	];
}
