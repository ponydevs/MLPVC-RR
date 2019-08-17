<?php

use App\View;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase {
  public function testProcessName() {
    $result = View::processName('\App\Controllers\TestController::method');
    $this->assertEquals(['test', 'method'], $result);
    $result = View::processName('App\Controllers\TestController::method');
    $this->assertEquals(['test', 'method'], $result);
    $result = View::processName('TestController::method');
    $this->assertEquals(['test', 'method'], $result);
    $result = View::processName('TestController::methodList');
    $this->assertEquals(['test', 'method-list'], $result);
    $result = View::processName('TestController::listMethod');
    $this->assertEquals(['test', 'list-method'], $result);
    $result = View::processName('LongTestController::arbitrarilyLongCamelCaseString');
    $this->assertEquals(['longtest', 'arbitrarily-long-camel-case-string'], $result);

    $this->expectException(RuntimeException::class);
    View::processName('RandomClass::method');
    $this->expectException(RuntimeException::class);
    View::processName('arbitrary input');
  }
}
