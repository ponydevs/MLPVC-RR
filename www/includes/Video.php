<?php

	class Video {
		public static $id, $embed;
		public $provider;
		private $url;
		public function __construct($url){
			$this->url = trim($url);
			$this->provider = $this->get_provider($this->url);
			$this->id = $this->provider['itemid'];
			$this->get_embed($this->provider['itemid'], $this->provider['name']);
		}
		private static $providerRegexes = array(
			'youtu(?:\.be/|be.com/watch.*[&?]v=)([^&?=]+)(?:&|$)' => 'yt',
			'dai(?:\.ly/|lymotion.com/video/(?:embed/)?)([^&?=]+)(?:&|$)' => 'dm'
		);
		private static function test_provider($url, $pattern, $name){
			$match = array();
			if (preg_match("~^(?:https?://(?:www\.)?)?$pattern~", $url, $match))
				return array(
					'name' => $name,
					'itemid' => $match[1]
				);
			return false;
		}
		public static function get_provider($url){
			foreach (self::$providerRegexes as $pattern => $name){
				$test = self::test_provider($url, $pattern, $name);
				if ($test !== false) return $test;
			}
			throw new Exception('Unsupported provider');
		}
		const URL_ONLY = true;
		public static function get_embed($id, $prov, $urlOnly = false){
			$urlOnly = $urlOnly === self::URL_ONLY;

			switch ($prov){
				case 'yt':
					$path = $urlOnly ? 'watch?v=' : 'embed/';
					$url = "https://www.youtube.com/$path$id";
				break;
				case 'dm':
					$path = !$urlOnly ? 'embed/' : '';
					$url = "https://www.dailymotion.com/{$path}video/$id";
					if (!$urlOnly) $url .= '?related=0&quality=1080&highlight=2E71B4';
				break;
			}

			return $urlOnly ? $url : "<iframe async defer allowfullscreen src='$url'></iframe>";
		}
	}
