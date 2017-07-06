<?php

namespace App\Models;

/**
 * @inheritdoc
 * @property string $type
 * @property string $requested_by
 * @property User   $reserver
 * @property User   $requester
 */
class Request extends Post {
	static $belongs_to = [
		['reserver', 'class' => 'User', 'foreign_hey' => 'reserved_by'],
		['requester', 'class' => 'User', 'foreign_hey' => 'requested_by'],
	];

	function get_isRequest():bool {
		return true;
	}
	function get_isReservation():bool {
		return false;
	}
}
