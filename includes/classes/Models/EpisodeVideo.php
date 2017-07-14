<?php

namespace App\Models;

use ActiveRecord\Model;
use App\CoreUtils;
use App\Time;
use App\VideoProvider;

/**
 * @property int     $season
 * @property int     $episode
 * @property int     $part
 * @property bool    $fullep
 * @property string  $provider
 * @property string  $id
 * @property string  $modified
 * @property string  $not_broken_at
 * @property Episode $ep
 */
class EpisodeVideo extends Model {
	public static $belongs_to = [
		['ep', 'class' => 'Episode', 'foreign_key' => ['season','episode']],
	];

	public function isBroken():bool {
		if (isset($this->not_broken_at)){
			$nb = strtotime($this->not_broken_at);
			if ($nb+(Time::IN_SECONDS['hour']*2) > time())
				return false;
		}

		$url = VideoProvider::getEmbed($this, VideoProvider::URL_ONLY);
		if ($this->provider === 'yt')
			$url = "http://www.youtube.com/oembed?url=$url";
		$broken = !CoreUtils::isURLAvailable($url);

		if (!$broken){
			$this->not_broken_at = date('c');
			$this->save();
		}

		return $broken;
	}
}
