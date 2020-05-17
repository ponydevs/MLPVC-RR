<?php

namespace App\Models;

/**
 * @property int            $id
 * @property int            $old_id
 * @property int            $post_id
 * @property string         $type
 * @property Post           $post        (Via relations)
 * @method static LegacyPostMapping find_by_old_id_and_type(int $old_id, string $type)
 */
class LegacyPostMapping extends NSModel {
  public static $belongs_to = [
    ['post']
  ];

  public static function record(int $post_id, int $response_code, string $failing_url, string $reserved_by) {
    self::create([
      'post_id' => $post_id,
      'response_code' => $response_code,
      'failing_url' => $failing_url,
      'reserved_by' => $reserved_by,
    ]);
  }

  public static function lookup(int $old_id, string $type):?Post {
    $item = self::find_by_old_id_and_type($old_id, $type);
    if ($item === null)
      return null;

    return Post::find($item->post_id);
  }
}
