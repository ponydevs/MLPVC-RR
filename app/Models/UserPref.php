<?php

namespace App\Models;

/**
 * @property string $user_id
 * @property string $key
 * @property string $value
 * @method static UserPref|UserPref[] find(...$args)
 * @method static UserPref find_by_user_id_and_key(string $user_id, string $key)
 */
class UserPref extends NSModel {
  public static $primary_key = ['user_id', 'key'];

  public static $belongs_to = [
    ['user', 'class' => 'DeviantartUser', 'foreign_key' => 'user_id'],
  ];

  /**
   * @param string         $key
   * @param DeviantartUser $user
   *
   * @return bool
   */
  public static function has(string $key, DeviantartUser $user) {
    return self::exists(['user_id' => $user->id, 'key' => $key]);
  }

  /**
   * @param string         $key
   * @param DeviantartUser $user
   *
   * @return UserPref|null
   */
  public static function find_for(string $key, DeviantartUser $user) {
    return self::find_by_user_id_and_key($user->id, $key);
  }
}
