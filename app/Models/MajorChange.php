<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\DB;
use function is_string;

/**
 * @property int        $id
 * @property int        $appearance_id
 * @property int        $user_id
 * @property string     $reason
 * @property DateTime   $created_at
 * @property DateTime   $updated_at
 * @property Appearance $appearance
 * @property User       $user
 * @property null       $log
 */
class MajorChange extends NSModel {
  public static $belongs_to = [
    ['appearance'],
    ['user'],
  ];

  /** For Twig */
  public function getAppearance() {
    return $this->appearance;
  }

  /** For Twig */
  public function getUser() {
    return $this->user;
  }

  public static function total(string $guide):int {
    $query = DB::$instance->querySingle(
      'SELECT COUNT(mc.id) as total
			FROM major_changes mc
			INNER JOIN appearances a ON mc.appearance_id = a.id
			WHERE a.guide = ?', [$guide]);

    return $query['total'] ?? 0;
  }

  /**
   * Gets the list of updates for an entire guide or just an appearance
   *
   * @param int|null        $PonyID
   * @param string|null     $guide
   * @param string|int|null $count
   *
   * @return MajorChange|MajorChange[]
   */
  public static function get(?int $PonyID, ?string $guide, $count = null) {
    $limit_query = '';
    if ($count !== null)
      $limit_query = is_string($count) ? $count : "LIMIT $count";
    if ($PonyID !== null){
      $where_query = "mc.appearance_id = ?";
      $where_param = $PonyID;
    }
    else {
      $where_query = 'a.guide = ?';
      $where_param = $guide;
    }

    $query = DB::$instance->setModel(__CLASS__)->query(
      "SELECT mc.*
			FROM major_changes mc
			INNER JOIN appearances a ON mc.appearance_id = a.id
			WHERE {$where_query}
			ORDER BY mc.created_at DESC
			{$limit_query}", [$where_param]);

    if ($count === MOST_RECENT)
      return $query[0] ?? null;

    return $query;
  }

  public static function record(int $appearance_id, string $reason) {
    self::create([
      'appearance_id' => $appearance_id,
      'reason' => $reason,
    ]);
  }
}
