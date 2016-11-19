<?php

	use Exceptions\cURLRequestException;

	class MismatchedProviderException extends Exception {
		private $actualProvider;
		function __construct($actualProvider){
			$this->actualProvider = $actualProvider;
		}
		function getActualProvider(){ return $this->actualProvider; }
	}

	class ImageProvider {
		public $preview = false, $fullsize = false, $title = '', $provider, $id, $author = null;
		public function __construct($url, $reqProv = null){
			$provider = $this->GetProvider(CoreUtils::Trim($url));
			if (!empty($reqProv)){
				if (!is_array($reqProv))
					$reqProv = array($reqProv);
				if (!in_array($provider['name'], $reqProv))
					throw new MismatchedProviderException($provider['name']);
			}
			$this->provider = $provider['name'];
			$this->_getDirectUrl($provider['itemid']);
		}
		private static $_providerRegexes = array(
			'(?:[A-Za-z\-\d]+\.)?deviantart\.com/art/(?:[A-Za-z\-\d]+-)?(\d+)' => 'dA',
			'fav\.me/(d[a-z\d]{6,})' => 'fav.me',
			'sta\.sh/([a-z\d]{10,})' => 'sta.sh',
			'(?:i\.)?imgur\.com/([A-Za-z\d]{1,7})' => 'imgur',
			'derpiboo(?:\.ru|ru\.org)/(\d+)' => 'derpibooru',
			'derpicdn\.net/img/(?:view|download)/\d{4}/\d{1,2}/\d{1,2}/(\d+)' => 'derpibooru',
			'puu\.sh/([A-Za-z\d]+(?:/[A-Fa-f\d]+)?)' => 'puush',
			'prntscr\.com/([\da-z]+)' => 'lightshot',
		);
		private static $_allowedMimeTypes = array('image/png' => true,'image/jpeg' => true,'image/jpg' => true);
		private static $_blockedMimeTypes = array('image/gif' => 'Animated GIFs');
		private static function _testProvider($url, $pattern, $name){
			$match = array();
			if (regex_match(new RegExp("^(?:https?://(?:www\\.)?)?$pattern"), $url, $match))
				return array(
					'name' => $name,
					'itemid' => $match[1]
				);
			return false;
		}
		public static function GetProvider($url){
			foreach (self::$_providerRegexes as $pattern => $name){
				$test = self::_testProvider($url, $pattern, $name);
				if ($test !== false)
					return $test;
			}
			throw new Exception("Unsupported provider. Try uploading your image to <a href='http://sta.sh' target='_blank'>sta.sh</a>");
		}

		private static function _checkImageAllowed($url, $ctype = null){
			if (empty($ctype)){
				if (empty($url))
					throw new Exception("Resource URL ($url) is empty, please try again.");
				$ctype = get_headers($url, 1)['Content-Type'];
			}
			if (empty(self::$_allowedMimeTypes[$ctype]))
				throw new Exception((!empty(self::$_blockedMimeTypes[$ctype])?self::$_blockedMimeTypes[$ctype].' are':"Content type \"$ctype\" is")." not allowed, please use a different image.");
		}

		private function _getDirectUrl($id){
			switch ($this->provider){
				case 'imgur':
					$this->fullsize = "https://i.imgur.com/$id.png";
					$this->preview = "https://i.imgur.com/{$id}m.png";
					self::_checkImageAllowed($this->fullsize);
				break;
				case 'derpibooru':
					$Data = @file_get_contents("http://derpibooru.org/$id.json");

					if (empty($Data))
						throw new Exception('The requested image could not be found on Derpibooru');
					$Data = JSON::Decode($Data, true);

					if (isset($Data['duplicate_of']))
						return $this->_getDirectUrl($Data['duplicate_of']);

					if (!isset($Data['is_rendered'])){
						error_log("Invalid Derpibooru response for ID $id\n".var_export($Data,true));
						throw new Exception('Derpibooru returned an invalid API response. This issue has been logged, please <a class="send-feedback">remind us</a> to take a look.');
					}

					if (!$Data['is_rendered'])
						throw new Exception('The image was found but it hasn\'t been rendered yet. Please wait for it to render and try again shortly.');

					$this->fullsize = $Data['representations']['full'];
					$this->preview = $Data['representations']['small'];

					self::_checkImageAllowed($this->fullsize, $Data['mime_type']);
				break;
				case 'puush':
					$path = "http://puu.sh/{$id}";
					$image = @file_get_contents($path);

					if (empty($image) || $image === 'That puush could not be found.')
						throw new Exception('The requested image could not be found on Puu.sh');
					if ($image === 'You do not have access to view that puush.')
						throw new Exception('The requested image is a private Puu.sh and the token is missing from the URL');

					self::_checkImageAllowed($path);
					$this->fullsize = $this->preview = $path;
				break;
				case 'dA':
				case 'fav.me':
				case 'sta.sh':
					if ($this->provider === 'dA'){
						$id = 'd'.base_convert($id, 10, 36);
						$this->provider = 'fav.me';
					}

					try {
						$CachedDeviation = DeviantArt::GetCachedSubmission($id,$this->provider);

						if (!DeviantArt::IsImageAvailable($CachedDeviation['preview'])){
							$preview = CoreUtils::AposEncode($CachedDeviation['preview']);
							throw new Exception("The preview image appears to be unavailable. Please make sure <a href='$preview'>this link</a> works and try again, or re-submit the deviation if this persists.");
						}
						if (!DeviantArt::IsImageAvailable($CachedDeviation['fullsize'])){
							$fullsize = CoreUtils::AposEncode($CachedDeviation['fullsize']);
							throw new Exception("The submission appears to be unavailable. Please make sure <a href='$fullsize'>this link</a> works and try again, or re-submit the deviation if this persists.");
						}
					}
					catch(cURLRequestException $e){
						if ($e->getCode() === 404)
							throw new Exception('The requested image could not be found');
						throw new Exception($e->getMessage());
					}

					if (empty($CachedDeviation))
						throw new Exception("{$this->provider} submission information could not be fetched for $id");

					$this->preview = $CachedDeviation['preview'];
					$this->fullsize = $CachedDeviation['fullsize'];
					$this->title = $CachedDeviation['title'];
					$this->author = $CachedDeviation['author'];

					self::_checkImageAllowed($this->preview);
					self::_checkImageAllowed($this->fullsize);
				break;
				case 'lightshot':
					$page = @file_get_contents("http://prntscr.com/$id");
					if (empty($page))
						throw new Exception('The requested page could not be found');
					if (!regex_match(new RegExp('<img\s+class="image__pic[^"]*"\s+src="http://i\.imgur\.com/([A-Za-z\d]+)\.'), $page, $_match))
						throw new Exception('The requested image could not be found');

					$this->provider = 'imgur';
					$this->_getDirectUrl($_match[1]);
				break;
				default:
					throw new Exception("The image could not be retrieved due to a missing handler for the provider \"{$this->provider}\"");
			}

			$this->preview = URL::MakeHttps($this->preview);
			$this->fullsize = URL::MakeHttps($this->fullsize);

			$this->id = $id;
		}
	}