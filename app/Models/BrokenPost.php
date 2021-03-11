<?php

namespace App\Models;

use ActiveRecord\DateTime;

/**
 * @property int      $id
 * @property int      $post_id
 * @property int      $reserved_by
 * @property int      $response_code
 * @property string   $failing_url
 * @property DateTime $created_at
 * @property DateTime $updated_at
 * @property Post     $post          (Via magic method)
 */
class BrokenPost extends NSModel {
  public static $belongs_to = [
    ['post']
  ];

  public static function record(int $post_id, int $response_code, string $failing_url, ?string $reserved_by) {
    self::create([
      'post_id' => $post_id,
      'response_code' => $response_code,
      'failing_url' => $failing_url,
      'reserved_by' => $reserved_by,
    ]);
  }
}
