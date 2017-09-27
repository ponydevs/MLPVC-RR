<?php

namespace App\Models;

use ActiveRecord\DatabaseException;
use ActiveRecord\DateTime;
use App\Logs;
use App\Time;

/**
 * @property int      $id
 * @property string   $ip
 * @property string   $user_id
 * @property DateTime $first_seen
 * @property DateTime $last_seen
 * @property User     $user
 * @method static KnownIP[] find_all_by_ip(string $ip)
 * @method static KnownIP find_by_ip_and_user_id(string $ip, string $user_id)
 * @method static KnownIP create($attributes, $validate = true, $guard_attributes = false)
 */
class KnownIP extends NSModel implements LinkableInterface {
	static $belongs_to = [
		['user'],
	];

	/**
	 * @param string $ip
	 * @param string $user_id
	 * @param string|int|DateTime $last_seen
	 * @param string|int|DateTime $first_seen
	 *
	 * @return KnownIP|null
	 */
	static function record(?string $ip = null, ?string $user_id = null, $last_seen = null, $first_seen = null){
		$data = [
			'ip' => strtolower($ip ?? $_SERVER['REMOTE_ADDR']),
			'user_id' => $user_id,
		];

		foreach (['first','last'] as $k){
			$vname = $k.'_seen';
			$val = $$vname;
			if ($val !== null){
				if (is_string($val))
					$data[$k.'_seen'] = date('c',strtotime($val));
				else if (is_int($val))
					$data[$k.'_seen'] = date('c',$val);
				else if ($val instanceof DateTime)
					$data[$k.'_seen'] = date('c',$val->getTimestamp());
			}
		}

		if (in_array($data['ip'], Logs::LOCALHOST_IPS, true))
			$data['ip'] = 'localhost';

		$existing = self::find_by_ip_and_user_id($data['ip'], $data['user_id']);
		if (empty($existing)){
			try {
				return self::create($data);
			}
			catch (\PDOException | DatabaseException $e){
				if (strpos($e->getMessage(), 'duplicate key value violates unique constraint "known_ips_ip_user_id"') === false)
					throw $e;
				return self::find_by_ip_and_user_id($data['ip'], $data['user_id']);
			}
		}
		else $existing->update_attributes($data);
		return $existing;
	}

	function toAnchor(bool $with_freshness = true):string {
		$fresh = $with_freshness ? "style='opacity:{$this->getFreshness()}'" : '';
		return "<a href='{$this->toURL()}' $fresh>$this->ip</a>";
	}

	function toURL():string {
		return "/admin/ip/$this->ip";
	}

	function getFreshness():float {
		$diff = time() - $this->last_seen->getTimestamp();
		if ($diff < Time::IN_SECONDS['week'])
			return 1;
		else if ($diff < Time::IN_SECONDS['week']*2)
			return .9;
		else if ($diff < Time::IN_SECONDS['month'])
			return .8;
		else if ($diff < Time::IN_SECONDS['month']*3)
			return .7;
		else return .4;
	}
}
