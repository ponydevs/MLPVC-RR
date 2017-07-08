<?php

namespace App\Models;

/** @inheritdoc */
class Reservation extends Post {
	public static $belongs_to = [
		['ep', 'class' => 'Episode', 'foreign_key' => ['season','episode']],
		['reserver', 'class' => 'User', 'foreign_key' => 'reserved_by'],
	];

	public static $alias_attribute = [
		'posted' => 'reserved_at',
	];

	public function get_is_request():bool {
		return false;
	}
	public function get_is_reservation():bool {
		return true;
	}
}
