<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\Auth;
use RuntimeException;

/**
 * @property int        $id
 * @property int        $tag_id
 * @property string     $tag_name
 * @property int        $appearance_id
 * @property int        $user_id
 * @property bool       $added
 * @property DateTime   $created_at
 * @property Tag        $tag           (Via relations)
 * @property Appearance $appearance    (Via relations)
 * @property User       $user          (Via relations)
 */
class TagChange extends NSModel {
  public static $belongs_to = [
    ['tag'],
    ['appearance'],
    ['user'],
  ];

  public static function record(bool $added, int $tag_id, string $tag_name, int $appearance_id, ?int $user_id = null):self {
    if ($user_id === null){
      if (!Auth::$signed_in)
        throw new RuntimeException(__METHOD__.' called without $user_id but no user is signed in');
      $user_id = Auth::$user->id;
    }

    $instance = new self();
    $instance->tag_id = $tag_id;
    $instance->tag_name = $tag_name;
    $instance->appearance_id = $appearance_id;
    $instance->user_id = $user_id;
    $instance->added = $added;
    $instance->save();

    return $instance;
  }
}
