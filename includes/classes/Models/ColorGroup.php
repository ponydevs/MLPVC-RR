<?php

namespace App\Models;

use ActiveRecord\Model;

/**
 * @property int        $groupid
 * @property int        $ponyid
 * @property int        $order
 * @property string     $label
 * @property Appearance $appearance
 */
class ColorGroup extends Model {
	static $primary_key = 'groupid';

	static $table_name = 'colorgrupus';

	static $belongs_to = [
		['appearance', 'foreign_key' => 'ponyid'],
	];
}
