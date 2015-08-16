<?php

	class MismatchedProviderException extends Exception {
		private $actualProvider;
		function __construct($actualProvider){
			$this->actualProvider = $actualProvider;
		}
		function getActualProvider(){ return $this->actualProvider; }
	}

	class Image {
		public $preview, $fullsize, $title = '', $provider, $id;
		private $url;
		public function __construct($url, $reqProv = null){
			$this->url = trim($url);
			$this->preview = $this->fullsize = false;

			$provider = $this->get_provider($this->url);
			if (!empty($reqProv)){
				if (!is_array($reqProv))
					$reqProv = array($reqProv);
				if (!in_array($provider['name'], $reqProv))
					throw new MismatchedProviderException($provider['name']);
			}
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
			'puu\.sh/([A-Za-z\d]+(?:/[A-Fa-f\d]+)?)' => 'puush',
		);
		private static $stripProtocol = array('fav.me','sta.sh');
		public static function removeProtocol(&$url){
			$url = preg_replace('/^https?:/','',$url);
			return 'NOPE';
		}
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
		private function get_direct_url($id){
			switch ($this->provider){
				case 'imgur':
					$this->fullsize = "//i.imgur.com/$id.png";
					$this->preview = "//i.imgur.com/{$id}m.png";
				break;
				case 'derpibooru':
					$Data = file_get_contents("http://derpibooru.org/$id.json");

					if (empty($Data))
						throw new Exception('The requested image could not be found on Derpibooru');
					$Data = json_decode($Data, true);

					if (!$Data['is_rendered'])
						throw new Exception('The image was found but it hasn\'t been rendered yet. Please wait for it to render and try again shortly.');

					$this->fullsize = $Data['representations']['full'];
					$this->preview = $Data['representations']['small'];
				break;
				case 'puush':
					$path = "http://puu.sh/{$id}";
					$image = @file_get_contents($path);

					if (empty($image) || $image === 'That puush could not be found.')
						throw new Exception('The requested image could not be found on Puu.sh');
					if ($image === 'You do not have access to view that puush.')
						throw new Exception('The requested image is a private Puu.sh and the token is missing from the URL');

					$this->fullsize = $this->preview = $path;
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

			if (in_array($this->provider,self::$stripProtocol)){
				self::removeProtocol($this->preview);
				self::removeProtocol($this->fullsize);
			}

			$this->id = $id;
		}
	}
