<?php

namespace App\Models\Logs;

use ActiveRecord\DateTime;
use App\Models\User;
use App\Models\NSModel;

/**
 * @property int      $entryid
 * @property int      $refid
 * @property string   $initiator
 * @property string   $reftype
 * @property DateTime $timestamp
 * @property string   $ip
 * @property User     $actor
 * @method static Log find_by_reftype_and_refid(string $reftype, int $refid)
 * @method static Log[] find_all_by_ip(string $ip)
 */
class Log extends NSModel {
	public static $table_name = 'log';

	public static $primary_key = 'entryid';

	public static $belongs_to = [
		['actor', 'class' => '\App\Models\User', 'foreign_key' => 'initiator'],
	];
	/** For Twig */
	public function getActor():User {
		return $this->actor;
	}
}
