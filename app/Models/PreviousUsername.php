<?php

namespace App\Models;

/**
 * @property int            $id
 * @property string         $username
 * @property string         $user_id
 * @property DeviantartUser $user     (Via relations)
 * @method static PreviousUsername|PreviousUsername[] find_by_username(string $username)
 */
class PreviousUsername extends NSModel {
  public static $belongs_to = [
    ['user', 'class' => 'DeviantartUser', 'foreign_key' => 'user_id'],
  ];

  public static function record(string $user_id, string $old_name, string $new_name) {
    self::create([
      'old' => $old_name,
      'new' => $new_name,
      'user_id' => $user_id,
    ]);
  }
}
