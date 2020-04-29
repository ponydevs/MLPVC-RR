<?php

namespace App;

use App\Exceptions\CURLRequestException;
use Exception;

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
        'Accept-Encoding: gzip, deflate, br',
        'Accept-Language: en-US,en;q=0.5',
        'Connection: keep-alive',
      ],
      CURLOPT_HEADER => true,
      CURLOPT_BINARYTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:75.0) Gecko/20100101 Firefox/75.0',
    ];
    if (isset($referrer))
      $curl_opt[CURLOPT_REFERER] = $referrer;
    if (!empty($cookies)){
      $curl_opt[CURLOPT_COOKIE] = '';
      foreach ($cookies as $name => $value)
        $curl_opt[CURLOPT_COOKIE] .= "$name=$value; ";
      $curl_opt[CURLOPT_COOKIE] = rtrim($curl_opt[CURLOPT_COOKIE], '; ');
    }
    if ($skipBody === true)
      $curl_opt[CURLOPT_NOBODY] = $skipBody;
    curl_setopt_array($r, $curl_opt);

    $response = curl_exec($r);
    $response_code = curl_getinfo($r, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($r, CURLINFO_HEADER_SIZE);

    $response_headers = rtrim(mb_substr($response, 0, $header_size));
    $response = mb_substr($response, $header_size);
    $curl_error = curl_error($r);
    curl_close($r);

    if ($response_code < 200 || $response_code >= 300)
      throw new CURLRequestException("cURL fail for URL \"$url\". Response headers:\n$response_headers", $response_code, $curl_error);

    global $http_response_header;
    $http_response_header = array_map('rtrim', explode("\n", $response_headers));

    if (preg_match(new RegExp('Content-Encoding:\s?gzip', 'i'), $response_headers))
      $response = gzdecode($response);

    return [
      'responseHeaders' => $response_headers,
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
        [$name, $value] = explode('=', $cookie[1], 2);
        $cookies[$name] = $value;
      }

    $request = self::legitimateRequest($url, $cookies, $referrer, true);

    return preg_match(new RegExp('Location:\s+([^\r\n]+)'), $request['responseHeaders'], $_match) ? CoreUtils::trim($_match[1]) : null;
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
   * @throws Exception
   */
  public static function statusCode($code, $die = false):void {
    http_response_code($code);
    if ($die === AND_DIE)
      die();
  }

  /**
   * Redirection
   *
   * @param string $url  Redirection target URL
   * @param int    $code HTTP status code
   */
  private static function _redirect(string $url, int $code):void {
    header("Location: $url", true, $code);
    $url_enc = CoreUtils::aposEncode($url);
    die("<h1>HTTP $code ".self::STATUS_CODES[$code]."</h1><p>Click <a href='$url_enc'>here</a> if you aren't redirected.</p>");
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
    Twig::display('soft_redirect', ['message' => $message]);
  }

}
