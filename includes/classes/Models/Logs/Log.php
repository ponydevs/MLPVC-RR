<?php

namespace App\Models\Logs;

use ActiveRecord\Model;
use ActiveRecord\DateTime;
use App\Models\User;

/**
 * @property int      $entryid
 * @property int      $refid
 * @property string   $initiator
 * @property string   $reftype
 * @property DateTime $timestamp
 * @property string   $ip
 * @property User     $actor
 * @method static Log find_by_reftype_and_refid(string $reftype, int $refid)
 */
class Log extends Model {
	public static $table_name = 'log';

	public static $primary_key = 'entryid';

	public static $belongs_to = [
		['actor', 'class' => '\App\Models\User', 'foreign_key' => 'initiator'],
	];
}
