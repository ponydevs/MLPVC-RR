<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\CoreUtils;

/**
 * @property int      $id
 * @property string   $user_id
 * @property string   $platform
 * @property string   $browser_name
 * @property string   $browser_ver
 * @property string   $user_agent
 * @property string   $token   (Cookie Auth)
 * @property string   $access  (oAuth)
 * @property string   $refresh (oAuth)
 * @property string   $scope   (oAuth)
 * @property DateTime $expires (oAuth)
 * @property DateTime $created
 * @property DateTime $lastvisit
 * @property User     $user
 * @property bool     $expired (Via magic method)
 * @method static Session find_by_token(string $token)
 * @method static Session find_by_access(string $access)
 * @method static Session find_by_refresh(string $code)
 * @method static Session find(int $id)
 */
class Session extends NSModel {
	public static $belongs_to = [
		['user'],
	];

	public static $after_create = ['make_known_ip'];
	public static $after_update = ['make_known_ip'];

	public function get_expired(){
		return $this->expires->getTimestamp() < time();
	}

	public function make_known_ip(){
		KnownIP::record(null, $this->user_id, $this->lastvisit);
	}

	public function detect_browser(?string $ua = null){
		foreach (CoreUtils::detectBrowser($ua) as $k => $v)
			if (!empty($v))
				$this->{$k} = $v;
	}
}

