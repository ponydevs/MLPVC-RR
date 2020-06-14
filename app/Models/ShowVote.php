<?php

namespace App\Models;

/**
 * @property int  $id
 * @property int  $show_id
 * @property int  $vote
 * @property int  $user_id
 * @property User $user    (Via relations)
 * @property Show $show    (Via relations)
 * @method static ShowVote find_by_show_id_and_user_id(int $show_id, string $user_id)
 */
class ShowVote extends NSModel {
  public static $table_name = 'show_votes';
  public static $belongs_to = [
    ['show'],
    ['user'],
  ];

  /**
   * @param Show $show
   * @param User $user
   *
   * @return ShowVote|null
   */
  public static function findFor(Show $show, ?User $user):?ShowVote {
    if ($user === null)
      return null;

    return self::find_by_show_id_and_user_id($show->id, $user->id);
  }
}
