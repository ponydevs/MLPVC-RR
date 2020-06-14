<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\Auth;

/**
 * @property int      $id
 * @property int      $post_id
 * @property DateTime $created_at
 * @property DateTime $updated_at
 * @property int      $user_id
 * @property User     $user       (Via relations)
 * @property Post     $post       (Via magic method)
 */
class LockedPost extends NSModel {
  public static $belongs_to = [
    ['user'],
    ['post'],
  ];

  public static function record(int $post_id) {
    self::create(['post_id' => $post_id, 'user_id' => Auth::$user->id ?? null]);
  }
}
