<?php

namespace App\Models;

use App\Users;
use App\RegExp;

class DiscordMember extends AbstractUser {
	/** @var string */
	public
		$id,
		$userid,
		$username,
		$nick,
		$avatar_hash,
		$joined_at;
	/** @var int */
	public $discriminator;

	/** @param array|object */
	public function __construct($iter = null){
		parent::__construct($this, $iter);

		$this->name = $this->displayedName();
		$this->avatar_url = $this->getAvatarURL();
	}

	function toArray(bool $remove_empty = false): array{
		$arr = parent::toArray($remove_empty);
		if (array_key_exists('name', $arr))
			unset($arr['name']);
		if (array_key_exists('avatar_url', $arr))
			unset($arr['avatar_url']);
		return $arr;
	}

	static function of(User $user):DiscordMember {
		global $Database;
		/** @noinspection PhpIncompatibleReturnTypeInspection */
		return $Database->where('userid', $user->id)->getOne('discord-members');
	}

	public function getAvatarURL(){
		return isset($this->avatar_hash) ? "https://images.discordapp.net/avatars/{$this->id}/{$this->avatar_hash}" : null;
	}

	public function displayedName(){
		return $this->nick ?? $this->username;
	}

	function nameToDAName(string $name):?string{
		global $DISCORD_NICK_REGEX;

		if (!preg_match($DISCORD_NICK_REGEX, $name))
			return null;

		return $DISCORD_NICK_REGEX->replace('$1$2', $name);
	}

	// This array defines static bindings for Staff members to prevent fraud
	// Prefixes are to prevent keys from converting to numbers
	const STAFF_BINDINGS = [
		'id-167355011754491904' => '0ed57486-fc42-a2b1-3092-8f74c7ec4921',
		'id-135035980292947968' => '06af57df-8755-a533-8711-c66f0875209a',
		'id-134863841006845952' => '3a3d7829-9021-91a6-d84a-a8c041102fdd',
		'id-134967730343247872' => 'c8fd7367-3ddb-dbe4-f95a-adb849660097',
		'id-169075425170030592' => 'd3c08918-ab8e-78df-9e71-38ed61f1d682',
		'id-168428391480033280' => 'c1e3862f-f75f-0476-e203-43111d079a8f',
		'id-134863413733097472' => 'fbd4c706-ae9d-87e1-d667-4901895e63ce',
		'id-187712023788912640' => '98a08424-25f4-14ad-fbd6-f7a2ee91ac74',
		'id-170649182288347136' => '1ed58761-f4dd-9268-ebfb-d09de58fddbd',
		'id-140360880079503362' => '46947ae2-62ae-28d1-2e49-6daee2048f59',
	];

	// List of DA user IDs we do not want to bind to for whatever reason (e.g. ambigous name)
	const BIND_BLACKLIST = [
		'de07c6f1-cdbe-d154-47d4-1d7315688c95',
		'ae90a347-25b4-a850-f7be-8399d16810ce',
		'd401c282-16bc-525b-c689-86c657fdcc14',
		'f15237dd-547b-4dac-09ff-7a44b7cd6f9f',
		'6d2b4808-1792-6342-7087-aa0fb261907d',
		'f73c6d54-49d2-a88b-ceb5-aba86dbb9b5b',
	];

	public function guessDAUser():?string {
		if (isset($this->userid))
			return true;

		if (!empty(self::STAFF_BINDINGS["id-{$this->id}"]))
			return $this->_checkDAUserBlacklist(self::STAFF_BINDINGS["id-{$this->id}"]);

		if (isset($this->nick)){
			$daname = $this->nameToDAName($this->nick);
			$firstGuess = Users::get($daname ?? $this->nick, 'name', 'id');
			if (!empty($firstGuess))
				return $this->_checkDAUserBlacklist($firstGuess->id);
		}

		$daname = $this->nameToDAName($this->username);
		if (!empty($daname)){
			$secondGuess = Users::get($daname, 'name', 'id');
			if (!empty($secondGuess))
				return $this->_checkDAUserBlacklist($secondGuess->id);
		}

		$thirdGuess = Users::get($this->username, 'name', 'id');
		if (!empty($thirdGuess))
			return $this->_checkDAUserBlacklist($thirdGuess->id);

		return null;
	}

	private function _checkDAUserBlacklist($id){
		return $this->userid = (in_array($id, self::BIND_BLACKLIST) ? null : $id);
	}
}
