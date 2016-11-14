<?php

	class VideoProvider {
		public static $id, $embed;
		/** @var \DB\EpisodeVideo */
		public $episodeVideo;
		private $url;
		public function __construct($url){
			$this->url = CoreUtils::Trim($url);
			$this->episodeVideo = self::getEpisodeVideo($this->url);
			self::$id = $this->episodeVideo->id;
			self::getEmbed($this->episodeVideo);
		}
		private static $providerRegexes = array(
			'youtu(?:\.be/|be.com/watch.*[&?]v=)([^&?=]+)(?:&|$)' => 'yt',
			'dai(?:\.ly/|lymotion.com/video/(?:embed/)?)([a-z\d]+)(?:_|$)' => 'dm'
		);
		private static function test_provider(string $url, string $pattern, string $name):\DB\EpisodeVideo {
			$match = array();
			if (regex_match(new RegExp("^(?:https?://(?:www\\.)?)?$pattern"), $url, $match))
				return new \DB\EpisodeVideo(array(
					'provider' => $name,
					'id' => $match[1]
				));
			return null;
		}
		public static function getEpisodeVideo(string $url):\DB\EpisodeVideo {
			foreach (self::$providerRegexes as $pattern => $name){
				$test = self::test_provider($url, $pattern, $name);
				if (!empty($test))
					return $test;
			}
			throw new Exception('Unsupported provider');
		}
		const URL_ONLY = true;
		public static function getEmbed(\DB\EpisodeVideo $video, bool $urlOnly = false):string {
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
