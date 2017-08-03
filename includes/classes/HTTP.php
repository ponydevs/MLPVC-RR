<?php

namespace App;

use App\Exceptions\CURLRequestException;

class HTTP {
	/**
	 * Simulate a user visiting the URL from a browser as closely as we can
	 *
	 * @param string      $url
	 * @param array|null  $cookies
	 * @param string|null $referrer
	 * @param bool        $skipBody
	 *
	 * @return array
	 */
	public static function legitimateRequest($url, $cookies = null, $referrer = null, bool $skipBody = false){
		$r = curl_init();
		$curl_opt = [
			CURLOPT_HTTPHEADER => [
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
				'Accept-Encoding: gzip, deflate, sdch',
				'Accept-Language: hu,en-GB;q=0.8,en;q=0.6',
				'Connection: keep-alive',
			],
			CURLOPT_HEADER => true,
			CURLOPT_URL => $url,
			CURLOPT_BINARYTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.5678.91 Safari/537.36'
		];
		if (isset($referrer))
			$curl_opt[CURLOPT_REFERER] = $referrer;
		if (is_array($cookies))
			$curl_opt[CURLOPT_COOKIE] = implode('; ', $cookies);
		if ($skipBody === true)
			$curl_opt[CURLOPT_NOBODY] = $skipBody;
		curl_setopt_array($r, $curl_opt);

		$response = curl_exec($r);
		$responseCode = curl_getinfo($r, CURLINFO_HTTP_CODE);
		$headerSize = curl_getinfo($r, CURLINFO_HEADER_SIZE);

		$responseHeaders = rtrim(CoreUtils::substring($response, 0, $headerSize));
		$response = CoreUtils::substring($response, $headerSize);
		$curlError = curl_error($r);
		curl_close($r);

		if ($responseCode < 200 || $responseCode >= 300)
			throw new CURLRequestException(rtrim("cURL fail for URL \"$url\" (HTTP $responseCode); $curlError",' ;'), $responseCode);

		global $http_response_header;
		$http_response_header = array_map('rtrim',explode("\n",$responseHeaders));

		if (preg_match(new RegExp('Content-Encoding:\s?gzip'), $responseHeaders))
			$response = gzdecode($response);
		return [
			'responseHeaders' => $responseHeaders,
			'response' => $response,
		];
	}

	/**
	 * Finds where the specified url redirects to
	 *
	 * @param string      $url
	 * @param string|null $referrer
	 *
	 * @return string|null
	 */
	public static function findRedirectTarget($url, $referrer = null){
		global $http_response_header;

		$cookies = [];
		if (!empty($http_response_header))
			foreach ($http_response_header as $header){
				if (!preg_match(new RegExp('^([^:]+): (.*)$'), $header, $parts) || $parts[1] !== 'Set-Cookie')
					continue;

				preg_match(new RegExp('\s*([^=]+=[^;]+)(?:;|$)'), $parts[2], $cookie);
				$cookies[] = $cookie[1];
			};

		$request = self::legitimateRequest($url, $cookies, $referrer, true);
		return preg_match(new RegExp('Location:\s+([^\r\n]+)'), $request['responseHeaders'], $_match) ? CoreUtils::trim($_match[1]) : null;
	}


	private static $PUSHED_ASSETS = [];
	/**
	 * Suggest files to client via HTTP/2 Server Push
	 *
	 * @param string $url
	 */
	public static function pushResource($url){
		self::$PUSHED_ASSETS[] = $url;
		$headerContent = [];
		foreach(self::$PUSHED_ASSETS as $asset)
			$headerContent[] = "<$url>; rel=preload";
		header('Link: '.implode(',', $headerContent));
	}

	public static $STATUS_CODES = [
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Moved Temporarily',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Time-out',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Large',
		415 => 'Unsupported Media Type',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Time-out',
		505 => 'HTTP Version not supported',
	];

	/**
	 * Sends an HTTP status code header with the response
	 *
	 * @param int  $code HTTP status code
	 * @param bool $die  Halt script execution afterwards
	 *
	 * @throws \Exception
	 */
	public static function statusCode($code, $die = false){
		if (!isset(self::$STATUS_CODES[$code])){
			throw new \Exception("Unknown status code: $code");
		}

		header($_SERVER['SERVER_PROTOCOL']." $code ".self::$STATUS_CODES[$code]);
		if ($die === AND_DIE)
			die();
	}

	const
		REDIRECT_PERM = 301,
		REDIRECT_TEMP = 302;

	/**
	 * Redirection
	 *
	 * @param string $url  Redirection target URL
	 * @param int    $http HTTP status code
	 */
	public static function redirect($url = '/', $http = self::REDIRECT_PERM){
		header("Location: $url", true, $http);
		$urlenc = CoreUtils::aposEncode($url);
		die("Click <a href='$urlenc'>here</a> if you aren’t redirected.<script>location.replace(".JSON::encode($url).')</script>');
	}
}
