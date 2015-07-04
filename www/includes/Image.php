<?php

	class Image {
		public $preview, $fullsize, $title = '', $provider, $id;
		private $url;
		public function __construct($url){
			$this->url = trim($url);
			$this->preview = $this->fullsize = false;

			$provider = $this->get_provider($this->url);
			$this->provider = $provider['name'];
			$this->get_direct_url($provider['itemid']);
		}
		private static $providerRegexes = array(
			'(?:[A-Za-z\-\d]+\.)?deviantart\.com/art/(?:[A-Za-z\-\d]+-)?(\d+)' => 'dA',
			'fav\.me/(d[a-z\d]{6,})' => 'fav.me',
			'sta\.sh/([a-z\d]{10,})' => 'sta.sh',
			'(?:i\.)?imgur\.com/([A-Za-z\d]{1,7})' => 'imgur',
			'derpiboo(?:\.ru|ru\.org)/(\d+)' => 'derpibooru',
			'derpicdn\.net/img/(?:view|download)/\d{4}/\d{1,2}/\d{1,2}/(\d+)' => 'derpibooru',
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
			throw new Exception('Unsupported provider. Try uploading your image to <a href=http://sta.sh target=_blank>sta.sh</a>');
		}
		private function addProtcol($url){
			if (!preg_match('/^https?:/', $url))
				$url = "http:$url";
			return $url;
		}
		private function get_direct_url($id){
			switch ($this->provider){
				case 'imgur':
					$this->fullsize = "http://i.imgur.com/$id.png";
					$this->preview = "http://i.imgur.com/{$id}m.png";
				break;
				case 'derpibooru':
					$Data = file_get_contents("http://derpibooru.org/$id.json");

					if (empty($Data))
						throw new Exception('The requested image could not be found on Derpibooru');
					$Data = json_decode($Data, true);

					if (!$Data['is_rendered'])
						throw new Exception('The image was found but it hasn\'t been rendered yet. Please wait for it to render and try again shortly.');

					$this->fullsize = $this->addProtcol($Data['representations']['full']);
					$this->preview = $this->addProtcol($Data['representations']['small']);
				break;
				case 'dA':
				case 'fav.me':
				case 'sta.sh':
					if ($this->provider === 'dA'){
						$id = 'd'.base_convert($id, 10, 36);
						$this->provider = 'fav.me';
					}

					$CachedDeviation = da_cache_deviation($id,$this->provider);

					$this->preview = $CachedDeviation['preview'];
					$this->fullsize = $CachedDeviation['fullsize'];
					$this->title = $CachedDeviation['title'];
				break;
				default:
					throw new Exception('The image could not be retrieved');
			}

			$this->id = $id;
		}
	}