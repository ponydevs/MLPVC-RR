<?php

namespace App\Models;

/**
 * @inheritdoc
 * @property string $type
 * @property string $requested_by
 * @property User   $reserver
 */
class Reservation extends Post {
	static $belongs_to = [
		['reserver', 'class' => 'User', 'foreign_hey' => 'reserved_by'],
	];

	function get_isRequest():bool {
		return false;
	}
	function get_isReservation():bool {
		return true;
	}
}
