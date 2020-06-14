<?php

namespace App\Models;

use ActiveRecord\DateTime;

/**
 * @property int            $id
 * @property int            $post_id
 * @property DateTime       $created_at
 * @property DateTime       $updated_at
 * @property string         $user_id
 * @property DeviantartUser $user          (Via relations)
 * @property Post           $post          (Via magic method)
 */
class LockedPost extends NSModel {
  public static $belongs_to = [
    ['user', 'class' => 'DeviantartUser', 'foreign_key' => 'user_id'],
    ['post'],
  ];

  public static function record(int $post_id) {
    self::create(['post_id' => $post_id, 'user_id' => Auth::$user->id ?? null]);
  }
}
