<?php

namespace App\Models;

use ActiveRecord\Model;

/**
 * @property int $id
 * @property string $type
 * @property string $data
 * @property string $sent_at
 * @property string $read_at
 * @property string $read_action
 */
class Notification extends Model {
	static $belongs_to = [
		['users'],
	];
}
