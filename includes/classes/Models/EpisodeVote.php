<?php

namespace App\Models;

use ActiveRecord\Model;

/**
 * @property int     $season
 * @property int     $episode
 * @property int     $vote
 * @property User    $voter
 * @property Episode $ep
 */
class EpisodeVote extends Model {
	static $table_name = 'episodes__votes';

	static $belongs_to = [
		['ep', 'class' => 'Episode', 'foreign_key' => ['season','episode']],
		['voter', 'class' => 'User', 'foreign_key' => 'user'],
	];

	/**
	 * @param Episode $Episode
	 * @param User    $user
	 *
	 * @return EpisodeVote|null
	 */
	static function find_for(Episode $Episode, User $user):?EpisodeVote {
		return self::find_by_season_and_episode_and_user($Episode->season, $Episode->episode, $user->id);
	}
}
