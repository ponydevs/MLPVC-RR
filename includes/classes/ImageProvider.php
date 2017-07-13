<?php

namespace App;

use App\Exceptions\CURLRequestException;
use App\Exceptions\MismatchedProviderException;
use App\Exceptions\UnsupportedProviderException;

class ImageProvider {
	public $preview = false, $fullsize = false, $title = '', $provider, $id, $author = null;
	public function __construct(string $url = null, $reqProv = null){
		if (!empty($url)){
			$provider = self::getProvider(CoreUtils::trim($url));
			if (!empty($reqProv)){
				if (!is_array($reqProv))
					$reqProv = [$reqProv];
				if (!in_array($provider->name, $reqProv, true))
					throw new MismatchedProviderException($provider->name);
			}
			$this->provider = $provider->name;
			$this->setUrls($provider->itemid);
		}
	}
	private static $_providerRegexes = [
		'(?:[A-Za-z\-\d]+\.)?deviantart\.com/art/(?:[A-Za-z\-\d]+-)?(\d+)' => 'dA',
		'fav\.me/(d[a-z\d]{6,})' => 'fav.me',
		'sta\.sh/([a-z\d]{10,})' => 'sta.sh',
		'(?:i\.)?imgur\.com/([A-Za-z\d]{1,7})' => 'imgur',
		'derpiboo(?:\.ru|ru\.org)/(\d+)' => 'derpibooru',
		'derpicdn\.net/img/(?:view|download)/\d{4}/\d{1,2}/\d{1,2}/(\d+)' => 'derpibooru',
		'prntscr\.com/([\da-z]+)' => 'lightshot',
	];
	private static $_allowedMimeTypes = ['image/png' => true, 'image/jpeg' => true, 'image/jpg' => true];
	private static $_blockedMimeTypes = ['image/gif' => 'Animated GIFs'];
	/**
	 * @param string $url
	 * @param string $pattern
	 * @param string $name
	 *
	 * @return ImageProviderItem|bool
	 */
	private static function _testProvider($url, $pattern, $name){
		$match = [];
		if (preg_match(new RegExp("^(?:https?://(?:www\\.)?)?$pattern"), $url, $match))
			return new ImageProviderItem($name,$match[1]);
		return false;
	}

	/**
	 * @param string $url
	 *
	 * @return ImageProviderItem
	 * @throws UnsupportedProviderException
	 */
	public static function getProvider($url){
		foreach (self::$_providerRegexes as $pattern => $name){
			$test = self::_testProvider($url, $pattern, $name);
			if ($test !== false)
				return $test;
		}
		throw new UnsupportedProviderException();
	}

	private static function _checkImageAllowed($url, $ctype = null){
		if (empty($ctype)){
			if (empty($url))
				throw new \Exception("Resource URL ($url) is empty, please try again.");
			$ctype = get_headers($url, 1)['Content-Type'];
		}
		if (empty(self::$_allowedMimeTypes[$ctype]))
			throw new \Exception((!empty(self::$_blockedMimeTypes[$ctype])?self::$_blockedMimeTypes[$ctype].' are':"Content type \"$ctype\" is").' not allowed, please use a different image.');
	}

	/**
	 * Sets $this->fullsize and $this->preview on success
	 * @param int|string $id
	 * @return void
	 */
	public function setUrls($id):void {
		switch ($this->provider){
			case 'imgur':
				$this->fullsize = "https://i.imgur.com/$id.png";
				$this->preview = "https://i.imgur.com/{$id}m.png";
				self::_checkImageAllowed($this->fullsize);
			break;
			case 'derpibooru':
				$Data = @file_get_contents("http://derpibooru.org/$id.json");

				if (empty($Data))
					throw new \Exception('The requested image could not be found on Derpibooru');
				$Data = JSON::decode($Data);

				if (isset($Data['duplicate_of'])){
					$this->setUrls($Data['duplicate_of']);
					return;
				}

				if (!isset($Data['is_rendered'])){
					error_log("Invalid Derpibooru response for ID $id\n".var_export($Data,true));
					throw new \Exception('Derpibooru returned an invalid API response. This issue has been logged, please <a class="send-feedback">remind us</a> to take a look.');
				}

				if (!$Data['is_rendered'])
					throw new \Exception('The image was found but it hasn’t been rendered yet. Please wait for it to render and try again shortly.');

				$this->fullsize = $Data['representations']['full'];
				$this->preview = $Data['representations']['small'];

				self::_checkImageAllowed($this->fullsize, $Data['mime_type']);
			break;
			case 'puush':
				$path = "http://puu.sh/{$id}";
				$image = @file_get_contents($path);

				if (empty($image) || $image === 'That puush could not be found.')
					throw new \Exception('The requested image could not be found on Puu.sh');
				if ($image === 'You do not have access to view that puush.')
					throw new \Exception('The requested image is a private Puu.sh and the token is missing from the URL');

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
					$CachedDeviation = DeviantArt::getCachedDeviation($id,$this->provider);

					if (isset($CachedDeviation->preview) && !DeviantArt::isImageAvailable($CachedDeviation->preview)){
						$preview = CoreUtils::aposEncode($CachedDeviation->preview);
						throw new \Exception("The preview image appears to be unavailable. Please make sure <a href='$preview'>this link</a> works and try again, or re-submit the deviation if this persists.");
					}
					if (isset($CachedDeviation->fullsize) && !DeviantArt::isImageAvailable($CachedDeviation->fullsize)){
						$fullsize = CoreUtils::aposEncode($CachedDeviation->fullsize);
						throw new \Exception("The submission appears to be unavailable. Please make sure <a href='$fullsize'>this link</a> works and try again, or re-submit the deviation if this persists.");
					}
				}
				catch(CURLRequestException $e){
					if ($e->getCode() === 404)
						throw new \Exception('The requested image could not be found');
					throw new \Exception($e->getMessage());
				}

				if (empty($CachedDeviation))
					throw new \Exception("{$this->provider} submission information could not be fetched for $id");

				$this->preview = $CachedDeviation->preview;
				$this->fullsize = $CachedDeviation->fullsize;
				$this->title = $CachedDeviation->title;
				$this->author = $CachedDeviation->author;

				if ($this->preview !== null)
					self::_checkImageAllowed($this->preview);
				if ($this->fullsize !== null)
					self::_checkImageAllowed($this->fullsize);
			break;
			case 'lightshot':
				$page = @file_get_contents("http://prntscr.com/$id");
				if (empty($page))
					throw new \Exception('The requested page could not be found');
				if (!preg_match(new RegExp('<img\s+class="image__pic[^"]*"\s+src="http://i\.imgur\.com/([A-Za-z\d]+)\.'), $page, $_match))
					throw new \Exception('The requested image could not be found');

				$this->provider = 'imgur';
				$this->setUrls($_match[1]);
			break;
			default:
				throw new \Exception("The image could not be retrieved due to a missing handler for the provider \"{$this->provider}\"");
		}

		if (isset($this->preview))
			$this->preview = URL::makeHttps($this->preview);
		if (isset($this->fullsize))
			$this->fullsize = URL::makeHttps($this->fullsize);

		$this->id = $id;
	}
}
