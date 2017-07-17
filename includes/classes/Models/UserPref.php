<?php

namespace App\Models;

use \ActiveRecord\Model;

/**
 * @property string $user_id
 * @property string $key
 * @property string $value
 * @method static UserPref|UserPref[] find(...$args)
 * @method static UserPref find_by_user_id_and_key(string $user_id, string $key)
 */
class UserPref extends Model {
	public static $primary_key = ['user_id', 'key'];

	public static $belongs_to = [
		['user'],
	];

	/**
	 * @param string $key
	 * @param User   $user
	 *
	 * @return bool
	 */
	public static function has(string $key, User $user){
		return self::exists(['user_id' => $user->id, 'key' => $key]);
	}

	/**
	 * @param string $key
	 * @param User   $user
	 *
	 * @return UserPref|null
	 */
	public static function find_for(string $key, User $user){
		return self::find_by_user_id_and_key($user->id, $key);
	}
}
