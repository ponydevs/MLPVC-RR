<?php

use App\URL;
use PHPUnit\Framework\TestCase;

class URLTest extends TestCase {
  public function testMakeHttps() {
    $result = URL::makeHttps('http://example.com/');
    self::assertEquals('https://example.com/', $result, 'Transform http protocol to https');
    $result = URL::makeHttps('https://example.com/');
    self::assertEquals('https://example.com/', $result, 'Leave https protocol as-is');
    $result = URL::makeHttps('ftp://example.com/');
    self::assertEquals('ftp://example.com/', $result, 'Do not transform protocols other than http');
    $result = URL::makeHttps('/img/logo.png');
    self::assertEquals('/img/logo.png', $result, 'Do not transform relative links');
  }
}
