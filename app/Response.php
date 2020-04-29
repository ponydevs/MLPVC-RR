<?php

namespace App;

use RuntimeException;
use function is_array;

class Response {
  public static function fail(string $message = '', $data = [], bool $prettyPrint = false):void {
    if (empty($message)){
      $message = Auth::$signed_in ? 'Insufficient permissions.'
        : '<p>You are not signed in (or your session expired).</p><p class="align-center"><button class="typcn green btn-da da-login" id="turbo-sign-in" data-url="/da-auth/begin">Sign back in</button></p>';
    }

    self::_respond(false, $message, $data, $prettyPrint);
  }

  public static function failApi(string $message = '', $data = [], bool $prettyPrint = false):void {
    if (empty($message)){
      $message = Auth::$signed_in
        ? 'You do not have permission to access the requested resource'
        : 'The requested resource requires authentication';
    }

    self::_respond(false, $message, $data, $prettyPrint);
  }

  public static function dbError(string $message = '', bool $pretty_print = false):void {
    if (!empty($message))
      $message .= ': ';
    $message .= rtrim('Error while saving to database: '.DB::$instance->getLastError(), ': ');

    self::_respond(false, $message, [], $pretty_print);
  }

  public static function success(string $message, $data = [], bool $pretty_print = false):void {
    self::_respond(true, $message, $data, $pretty_print);
  }

  public static function done(array $data = [], ?string $cache_key = null, ?int $cache_for_seconds = null):void {
    if ($cache_key !== null) {
      if ($cache_for_seconds === null)
        throw new RuntimeException("Cache duration for key $cache_key is null");
      $data['cachedOn'] = date('c');
      $data['cachedFor'] = $cache_for_seconds;
    }
    self::_respond(true, '', $data, false, $cache_key, $cache_for_seconds);
  }

  public static function doneCached(string $data):void {
    self::_respondWith(unserialize($data, [false]));
  }

  private static function _respondWith(string $data, ?string $cache_key = null, ?int $cache_for_seconds = null):void {
    if ($cache_key !== null) {
      RedisHelper::set($cache_key, serialize($data), $cache_for_seconds);
    }
    echo $data;
    exit;
  }

  private static function _respond(bool $status, string $message, $data, bool $prettyPrint, ?string $cache_key = null, ?int $cache_for = null):void {
    header('Content-Type: application/json');
    $response = ['status' => $status];
    if (!empty($message))
      $response['message'] = $message;
    if (!empty($data) && is_array($data))
      $response = array_merge($data, $response);
    $mask = JSON_UNESCAPED_SLASHES;
    if ($prettyPrint)
      $mask |= JSON_PRETTY_PRINT;
    self::_respondWith(JSON::encode($response, $mask), $cache_key, $cache_for);
  }
}
