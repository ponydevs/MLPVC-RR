<?php

namespace App\Models;

/**
 * @property int            $show_id
 * @property int            $vote
 * @property string         $user_id
 * @property DeviantartUser $user    (Via relations)
 * @property Show           $show    (Via relations)
 * @method static ShowVote find_by_show_id_and_user_id(int $show_id, string $user_id)
 */
class ShowVote extends NSModel {
  public static $table_name = 'show_votes';
  public static $belongs_to = [
    ['show'],
    ['user', 'class' => 'DeviantartUser', 'foreign_key' => 'user_id'],
  ];

  /**
   * @param Show           $show
   * @param DeviantartUser $user
   *
   * @return ShowVote|null
   */
  public static function find_for(Show $show, ?DeviantartUser $user):?ShowVote {
    if ($user === null)
      return null;

    return self::find_by_show_id_and_user_id($show->id, $user->id);
  }
}
