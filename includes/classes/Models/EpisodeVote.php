<?php

namespace App\Models;

use ActiveRecord\Model;

/**
 * @property int     $season
 * @property int     $episode
 * @property int     $vote
 * @property string  $user_id
 * @property User    $user
 * @property Episode $ep
 */
class EpisodeVote extends Model {
	public static $table_name = 'episodes__votes';

	public static $belongs_to = [
		['ep', 'class' => 'Episode', 'foreign_key' => ['season','episode']],
		['user'],
	];

	/**
	 * @param Episode $Episode
	 * @param User    $user
	 *
	 * @return EpisodeVote|null
	 */
	public static function find_for(Episode $Episode, ?User $user):?EpisodeVote {
		if ($user === null)
			return null;
		return self::find_by_season_and_episode_and_user($Episode->season, $Episode->episode, $user->id);
	}
}
