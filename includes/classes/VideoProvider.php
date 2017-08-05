<?php

namespace App;

use App\Models\EpisodeVideo;

class VideoProvider {
	public static $id, $embed;
	/** @var EpisodeVideo */
	public $episodeVideo;
	public function __construct(string $url){
		$this->episodeVideo = self::getEpisodeVideo(CoreUtils::trim($url));
		self::$id = $this->episodeVideo->id;
		self::getEmbed($this->episodeVideo);
	}
	private static $providerRegexes = [
		'youtu(?:\.be/|be.com/watch.*[&?]v=)([^&?=]+)(?:&|$)' => 'yt',
		'dai(?:\.ly/|lymotion.com/video/(?:embed/)?)([a-z\d]+)(?:_|$)' => 'dm'
	];

	/**
	 * @param string $url
	 * @param string $pattern
	 * @param string $name
	 *
	 * @return EpisodeVideo|null
	 */
	private static function testProvider(string $url, string $pattern, string $name) {
		$match = [];
		if (preg_match(new RegExp("^(?:https?://(?:www\\.)?)?$pattern"), $url, $match))
			return new EpisodeVideo([
				'provider' => $name,
				'id' => $match[1]
			]);
		return null;
	}
	public static function getEpisodeVideo(string $url): EpisodeVideo {
		foreach (self::$providerRegexes as $pattern => $name){
			$test = self::testProvider($url, $pattern, $name);
			if (!empty($test))
				return $test;
		}
		throw new \Exception('Unsupported provider');
	}
	const URL_ONLY = true;
	public static function getEmbed(EpisodeVideo $video, bool $urlOnly = false):string {
		$urlOnly = $urlOnly === self::URL_ONLY;

		switch ($video->provider){
			case 'yt':
				$url = $urlOnly ? "http://youtu.be/$video->id" : "https://www.youtube.com/embed/$video->id";
			break;
			case 'dm':
				$url = $urlOnly ? "http://dai.ly/$video->id" : "https://www.dailymotion.com/embed/video/$video->id?related=0&quality=1080&highlight=2C73B1";
			break;
		}

		return $urlOnly ? $url : "<iframe async defer allowfullscreen src='$url'></iframe>";
	}
}
