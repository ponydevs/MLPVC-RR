<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\Time;
use App\TMDBHelper;

/**
 * @property int      $season
 * @property int      $episode
 * @property int      $part
 * @property int      $tmdb_id
 * @property string   $body
 * @property string   $image
 * @property DateTime $created_at
 * @property DateTime $updated_at
 * @property Episode  $ep         (Via relations)
 * @method static Synopsis find_by_season_and_episode_and_part(int $season, int $episode, int $part)
 */
class Synopsis extends NSModel implements Linkable, Cacheable {
	public static $primary_key = ['season', 'episode'];
	public static $table_name = 'synopses';

	public static $belongs_to = [
		['ep', 'class' => 'Episode', 'foreign_key' => ['season', 'episode']],
	];

	public static function for(Episode $ep, int $part):?Synopsis {
		return self::find_by_season_and_episode_and_part($ep->season, $ep->episode, $part);
	}

	public function toURL():string {
		$show_id = TMDBHelper::getShowId();

		return "https://www.themoviedb.org/tv/{$show_id}/season/{$this->season}/episode/{$this->episode}";
	}

	public function toAnchor():string {
		return "<a href='{$this->toURL()}'>View in database &raquo;</a>";
	}

	public function getAge():int {
		return time() - $this->updated_at->getTimestamp();
	}

	public function cacheExpired():bool {
		return $this->getAge() > Time::IN_SECONDS['day'];
	}

	public function updateCache($data = null):void {
		$client = TMDBHelper::getClient();

		if ($data === null){
			$data = TMDBHelper::getEpisode($client, $this->season, $this->episode + ($this->part - 1));
			if ($data === null){
				$this->delete();
				return;
			}
		}

		$this->tmdb_id = $data['id'];
		if (!empty($data['still_path'])){
			$this->image = TMDBHelper::getImageUrl($client, $data['still_path']);
		}
		$this->body = $data['overview'];
	}
}
