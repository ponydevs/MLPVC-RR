<?php

namespace App\Models;

use ActiveRecord\DateTime;

/**
 * @property int            $id
 * @property int            $old_post_id
 * @property int            $post_id
 * @property string         $type
 * @property DateTime       $created_at
 * @property DateTime       $updated_at
 * @property string         $user_id
 * @property DeviantartUser $user          (Via relations)
 * @property Post           $post          (Via magic method)
 */
class LockedPost extends NSModel {
  public static $belongs_to = [
    ['user', 'class' => 'DeviantartUser', 'foreign_key' => 'user_id'],
  ];

  /** For Twig */
  public function getPost():Post {
    return $this->get_post();
  }

  public function get_post():Post {
    return $this->old_post_id ? Post::find_by_old_id($this->old_post_id) : Post::find($this->post_id);
  }

  public static function record(int $post_id) {
    self::create(['post_id' => $post_id]);
  }
}
