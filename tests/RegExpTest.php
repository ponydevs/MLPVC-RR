<?php

use App\RegExp;
use PHPUnit\Framework\TestCase;

class RegExpTest extends TestCase {
  public function testToString() {
    $result = (string)(new RegExp('^a[b-f]\D$', 'ui'));
    self::assertEquals('~^a[b-f]\D$~ui', $result);
  }

  public function testJsExport() {
    $result = (new RegExp('^/a[b-f]\D$', 'ui'))->jsExport();
    self::assertEquals('/^\/a[b-f]\D$/i', $result);
  }

  public function testEscapeBackslashes() {
    $result = RegExp::escapeBackslashes('\\');
    self::assertEquals('\\\\', $result);
  }
}
