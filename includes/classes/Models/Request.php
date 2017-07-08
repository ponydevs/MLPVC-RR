<?php

namespace App\Models;

use ActiveRecord\DateTime;

/**
 * @inheritdoc
 * @property string   $type
 * @property string   $requested_by
 * @property DateTime $requested_at
 * @property User     $requester
 */
class Request extends Post {
	public static $belongs_to = [
		['ep', 'class' => 'Episode', 'foreign_key' => ['season','episode']],
		['reserver', 'class' => 'User', 'foreign_key' => 'reserved_by'],
		['requester', 'class' => 'User', 'foreign_key' => 'requested_by'],
	];

	public static $alias_attribute = [
		'posted' => 'requested_at',
	];

	public function get_is_request():bool {
		return true;
	}
	public function get_is_reservation():bool {
		return false;
	}
}
