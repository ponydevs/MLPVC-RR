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
			'~^https?://(?:[A-Za-z\-\d]+\.)deviantart.com/art/[A-Za-z\-\d]+-(\d+)~' => 'dA',
			'~^http://fav.me/(d[a-z\d]{6,})~' => 'fav.me',
			'~^http://sta.sh/([a-z\d]{12,})~' => 'sta.sh'
		);
		private function get_provider(){
			foreach ($this->providerRegexes as $rx => $name){
				$match = array();
				if (preg_match($rx, $this->url, $match)){
					$this->provider = $name;
					$this->get_direct_url($match[1]);
					break;
				}
			}
		}
		private function get_direct_url($id){
			if ($this->provider === 'dA'){
				$id = 'd'.base_convert($id, 10, 36);
				$this->provider = 'fav.me';
			}

			$CachedDeviation = da_cache_deviation($id,$this->provider);

			$this->preview = $CachedDeviation['preview'];
			$this->fullsize = $CachedDeviation['fullsize'];
			$this->title = $CachedDeviation['title'];
		}
	}