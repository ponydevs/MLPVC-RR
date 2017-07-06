<?php

namespace App\Models\Logs;

use ActiveRecord\Model;
use ActiveRecord\DateTime;

/**
 * @property int      $entryid
 * @property int      $refid
 * @property string   $initiator
 * @property string   $reftype
 * @property DateTime $timestamp
 * @property string   $ip
 * @method static Log find_by_reftype_and_refid(string $reftype, int $refid)
 */
class Log extends Model {
	static $table_name = 'log';

	static $primary_key = 'entryid';

	static $belongs_to = [
		['actor', 'class' => '\App\Models\User', 'foreign_key' => 'initiator'],
	];
}
