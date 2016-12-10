<?php

namespace App\Models;

use App\CoreUtils;
use App\Models\AbstractFillable;
use App\Time;
use App\VideoProvider;

class EpisodeVideo extends AbstractFillable  {
	/** @var int */
	var $season, $episode, $part;
	/** @var bool */
	var $fullep;
	/** @var string */
	var $provider,
		$id,
		$modified,
		$not_broken_at;

	/** @param array|object */
	public function __construct($iter = null){
		parent::__construct($this, $iter);
	}

	public function isBroken():bool {
		global $Database;

		if (isset($this->not_broken_at)){
			$nb = strtotime($this->not_broken_at);
			if ($nb+(Time::$IN_SECONDS['hour']*2) > time())
				return false;
		}

		$url = VideoProvider::getEmbed($this, VideoProvider::URL_ONLY);
		if ($this->provider === 'yt')
			$url = "http://www.youtube.com/oembed?url=$url";
		$broken = !CoreUtils::isURLAvailable($url);

		if (!$broken){
			$this->not_broken_at = date('c');
			$Database->whereEp($this->season, $this->episode)->where('provider', $this->provider)->where('id', $this->id)->update('episodes__videos',array(
				'not_broken_at' => $this->not_broken_at,
			));
		}

		return $broken;
	}
}
