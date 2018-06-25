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
	public static function legitimateRequest($url, $cookies = null, $referrer = null, bool $skipBody = false):array {
		$r = curl_init($url);
		$curl_opt = [
			CURLOPT_HTTPHEADER => [
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
				'Accept-Encoding: gzip, deflate, sdch',
				'Accept-Language: hu,en-GB;q=0.8,en;q=0.6',
				'Connection: keep-alive',
			],
			CURLOPT_HEADER => true,
			CURLOPT_BINARYTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.5678.91 Safari/537.36'
		];
		if (isset($referrer))
			$curl_opt[CURLOPT_REFERER] = $referrer;
		if (!empty($cookies)){
			$curl_opt[CURLOPT_COOKIE] = '';
			foreach ($cookies as $name => $value)
				$curl_opt[CURLOPT_COOKIE] .= "$name=$value; ";
			$curl_opt[CURLOPT_COOKIE] = rtrim($curl_opt[CURLOPT_COOKIE],'; ');
		}
		if ($skipBody === true)
			$curl_opt[CURLOPT_NOBODY] = $skipBody;
		curl_setopt_array($r, $curl_opt);

		$response = curl_exec($r);
		$responseCode = curl_getinfo($r, CURLINFO_HTTP_CODE);
		$headerSize = curl_getinfo($r, CURLINFO_HEADER_SIZE);

		$responseHeaders = rtrim(mb_substr($response, 0, $headerSize));
		$response = mb_substr($response, $headerSize);
		$curlError = curl_error($r);
		curl_close($r);

		if ($responseCode < 200 || $responseCode >= 300)
			throw new CURLRequestException("cURL fail for URL \"$url\"", $responseCode, $curlError);

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
	public static function findRedirectTarget($url, $referrer = null):?string {
		global $http_response_header;

		$cookies = [];
		if (!empty($http_response_header))
			foreach ($http_response_header as $header){
				if (!preg_match(new RegExp('^([^:]+): (.*)$'), $header, $parts) || $parts[1] !== 'Set-Cookie')
					continue;

				preg_match(new RegExp('\s*([^=]+=[^;]+)(?:;|$)'), $parts[2], $cookie);
				[$name, $value] = explode('=',$cookie[1],2);
				$cookies[$name] = $value;
			}

		$request = self::legitimateRequest($url, $cookies, $referrer, true);
		return preg_match(new RegExp('Location:\s+([^\r\n]+)'), $request['responseHeaders'], $_match) ? CoreUtils::trim($_match[1]) : null;
	}


	private static $PUSHED_ASSETS = [];
	/**
	 * Suggest files to client via HTTP/2 Server Push
	 *
	 * @param string $url
	 */
	public static function pushResource($url):void {
		self::$PUSHED_ASSETS[] = $url;
		$headerContent = [];
		foreach(self::$PUSHED_ASSETS as $asset)
			$headerContent[] = "<$url>; rel=preload";
		header('Link: '.implode(',', $headerContent));
	}

	public const STATUS_CODES = [
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
		429 => 'Too Many Requests',
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
	public static function statusCode($code, $die = false):void {
		if (!isset(self::STATUS_CODES[$code])){
			throw new \Exception("Unknown status code: $code");
		}

		header($_SERVER['SERVER_PROTOCOL']." $code ".self::STATUS_CODES[$code]);
		if ($die === AND_DIE)
			die();
	}

	/**
	 * Redirection
	 *
	 * @param string $url  Redirection target URL
	 * @param int    $code HTTP status code
	 */
	private static function _redirect(string $url = '/', int $code):void {
		header("Location: $url", true, $code);
		$urlenc = CoreUtils::aposEncode($url);
		die("<h1>HTTP $code ".self::STATUS_CODES[$code]."</h1><p>Click <a href='$urlenc'>here</a> if you aren't redirected.</p>");
	}

	/**
	 * Redirection
	 *
	 * @param string $url Redirection target URL
	 */
	public static function tempRedirect(string $url):void {
		self::_redirect($url, 302);
	}

	/**
	 * Redirection
	 *
	 * @param string $url Redirection target URL
	 */
	public static function permRedirect(string $url):void {
		self::_redirect($url, 301);
	}

	/**
	 * Redirection
	 *
	 * @param string $url     Redirection target URL
	 * @param string $message Message display in <h1>
	 */
	public static function softRedirect(string $url = '/', string $message = 'Redirecting'):void {
		header("Refresh: 0;url=$url");
		$page = file_get_contents(INCPATH.'views/softRedirect.html');
		echo str_replace('{{MESSAGE}}', "$message&hellip;", $page);
		exit;
	}

}
