<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\Cookie;
use App\CoreUtils;
use App\Time;
use Ramsey\Uuid\Uuid;

/**
 * @property string   $id
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
 * @property string   $data
 * @property bool     $expired   (Via magic method)
 * @property User     $user      (Via relations)
 * @method static Session find_by_token(string $token)
 * @method static Session find_by_access(string $access)
 * @method static Session find_by_refresh(string $code)
 * @method static Session find(int $id)
 */
class Session extends NSModel {
	public static $belongs_to = [
		['user'],
	];

	public static $attr_protected = ['data'];

	public static $before_create = ['generate_id'];
	public static $after_create = ['make_known_ip'];
	public static $after_update = ['make_known_ip'];

	public function get_expired(){
		return $this->expires->getTimestamp() < time();
	}

	public function detectBrowser(?string $ua = null){
		foreach (CoreUtils::detectBrowser($ua) as $k => $v)
			if (!empty($v))
				$this->{$k} = $v;
	}

	public static function generateCookie():string {
		return bin2hex(random_bytes(64));
	}
	public static function setCookie(string $value){
		Cookie::set('access', $value, time() + Time::IN_SECONDS['year'], Cookie::HTTPONLY);
	}
	public static function newGuestSession():self {
		$session = new self();
		$cookie = self::generateCookie();
		$session->token = CoreUtils::sha256($cookie);
		$session->detectBrowser();
		$session->save();
		self::setCookie($cookie);
		return $session;
	}

	/** @var array */
	private $_data;
	private function _serializeData(){
		$this->data = serialize($this->_data);
		$this->save();
	}
	private function _unserializeData(){
		$this->_data = $this->data === null ? [] : unserialize($this->data);
	}
	public function importData(string $data){
		$this->data = $data;
		$this->_unserializeData();
	}
	public function setData(string $key, $value){
		if ($this->_data === null)
			$this->_unserializeData();
		$this->_data[$key] = $value;
		$this->_serializeData();
	}
	public function unsetData(string $key){
		if ($this->_data === null)
			$this->_unserializeData();
		unset($this->_data[$key]);
		$this->_serializeData();
	}
	public function getData(string $key){
		if ($this->_data === null)
			$this->_unserializeData();
		return $this->_data[$key] ?? null;
	}
	public function pullData(string $key){
		$value = $this->getData($key);
		$this->unsetData($key);
		return $value;
	}
	public function hasData(string $key){
		if ($this->_data === null)
			$this->_unserializeData();
		return isset($this->_data[$key]);
	}

	public function registerVisit(){
		if (time() - strtotime($this->lastvisit) > Time::IN_SECONDS['minute']){
			$this->lastvisit = date('c');
			$this->detectBrowser();
			$this->save();
		}
	}

	public function make_known_ip(){
		if ($this->user_id !== null)
			KnownIP::record(null, $this->user_id, $this->lastvisit);
	}

	public function generate_id(){
		$this->id = Uuid::uuid4()->toString();
	}
}

