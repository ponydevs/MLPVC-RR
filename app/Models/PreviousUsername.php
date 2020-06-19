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
    ['user', 'class' => 'DeviantartUser'],
  ];

  public static function record(string $user_id, string $username, string $_new) {
    if (self::exists(['username' => $username]))
      return;

    self::create([
      'username' => $username,
      '_new' => $_new,
      'user_id' => $user_id,
    ]);
  }
}
