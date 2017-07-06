<?php

namespace App\Models;

use \ActiveRecord\Model;

/**
 * @property string $user_id
 * @property string $key
 * @property string $value
 * @method static UserPref|UserPref[] find(...$args)
 */
class UserPref extends Model {
	static $table_name = 'user_prefs';

	static $primary_key = ['user_id','key'];

	static $belongs_to = [
		['user'],
	];

	static function find_for(string $key, User $user){
		return self::find_by_user_id_and_key($user->id, $key);
	}
}
