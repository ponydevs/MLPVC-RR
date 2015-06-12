<?php

	class Image {
		public $preview, $fullsize, $title = '';
		private $url, $provider;
		public function __construct($url){
			$this->url = trim($url);
			$this->preview = $this->fullsize = false;

			$this->get_provider();
		}
		private $providerRegexes = array(
			'~^(?:https?://)?(?:[A-Za-z\-\d]+\.)?deviantart\.com/art/(?:[A-Za-z\-\d]+-)?(\d+)~' => 'dA',
			'~^(?:http://)?fav\.me/(d[a-z\d]{6,})~' => 'fav.me',
			'~^(?:http://)?sta\.sh/([a-z\d]{10,})~' => 'sta.sh',
			'~^(?:http://)?(?:i\.)?imgur\.com/([A-Za-z\d]{1,7})~' => 'imgur',
		);
		private function get_provider(){
			foreach ($this->providerRegexes as $rx => $name){
				$match = array();
				if (preg_match($rx, $this->url, $match)){
					$this->provider = $name;
					$this->get_direct_url($match[1]);
					return;
				}
			}
			throw new Exception('Unsupported provider. Try uploading your image to <a href=http://sta.sh target=_blank>sta.sh</a>');
		}
		private function get_direct_url($id){
			switch ($this->provider){
				case 'imgur':
					$this->fullsize = "http://i.imgur.com/$id.png";
					$this->preview = "http://i.imgur.com/{$id}m.png";
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
			}
		}
	}