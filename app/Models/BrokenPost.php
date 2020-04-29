<?php

namespace App\Models;

use ActiveRecord\DateTime;

/**
 * @property int            $id
 * @property int            $old_post_id
 * @property int            $post_id
 * @property string         $type
 * @property string         $reserved_by
 * @property int            $response_code
 * @property string         $failing_url
 * @property DateTime       $created_at
 * @property DateTime       $updated_at
 * @property DeviantartUser $user          (Via relations)
 * @property Post           $post          (Via magic method)
 */
class BrokenPost extends NSModel {
  /** For Twig */
  public function getPost():Post {
    return $this->get_post();
  }

  public function get_post():Post {
    return $this->old_post_id ? Post::find_by_old_id($this->old_post_id) : Post::find($this->post_id);
  }

  public static function record(int $post_id, int $response_code, string $failing_url, string $reserved_by) {
    self::create([
      'post_id' => $post_id,
      'response_code' => $response_code,
      'failing_url' => $failing_url,
      'reserved_by' => $reserved_by,
    ]);
  }
}
