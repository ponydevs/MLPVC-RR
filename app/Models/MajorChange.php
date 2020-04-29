<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\DB;
use function is_string;

/**
 * @property int            $id
 * @property int            $appearance_id
 * @property int            $user_id
 * @property string         $reason
 * @property DateTime       $created_at
 * @property DateTime       $updated_at
 * @property Appearance     $appearance
 * @property DeviantartUser $user
 * @property null           $log
 */
class MajorChange extends NSModel {
  public static $belongs_to = [
    ['appearance'],
    ['user', 'class' => 'DeviantartUser', 'foreign_key' => 'user_id'],
  ];

  /** For Twig */
  public function getAppearance():Appearance {
    return $this->appearance;
  }

  public function getUser():DeviantartUser {
    return $this->user;
  }

  public static function total(bool $eqg):int {
    $query = DB::$instance->querySingle(
      'SELECT COUNT(mc.id) as total
			FROM major_changes mc
			INNER JOIN appearances a ON mc.appearance_id = a.id
			WHERE a.ishuman = ?', [$eqg]);

    return $query['total'] ?? 0;
  }

  /**
   * Gets the list of updates for an entire guide or just an appearance
   *
   * @param int|null        $PonyID
   * @param bool|null       $EQG
   * @param string|int|null $count
   *
   * @return MajorChange|MajorChange[]
   */
  public static function get(?int $PonyID, ?bool $EQG, $count = null) {
    $LIMIT = '';
    if ($count !== null)
      $LIMIT = is_string($count) ? $count : "LIMIT $count";
    $WHERE = $PonyID !== null ? "WHERE mc.appearance_id = $PonyID" : 'WHERE a.ishuman = '.($EQG ? 'true' : 'false');

    $query = DB::$instance->setModel(__CLASS__)->query(
      "SELECT mc.*
			FROM major_changes mc
			INNER JOIN appearances a ON mc.appearance_id = a.id
			{$WHERE}
			ORDER BY mc.created_at DESC
			{$LIMIT}");

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
