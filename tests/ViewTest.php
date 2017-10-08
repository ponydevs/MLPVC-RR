<?php

use App\View;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase {
	public function testProcessName(){
		$result = View::processName('\App\Controllers\TestController::method');
		$this->assertEquals(['test','method'], $result);
		$result = View::processName('App\Controllers\TestController::method');
		$this->assertEquals(['test','method'], $result);
		$result = View::processName('TestController::method');
		$this->assertEquals(['test','method'], $result);
		$result = View::processName('TestController::methodList');
		$this->assertEquals(['test','method-list'], $result);
		$result = View::processName('TestController::listMethod');
		$this->assertEquals(['test','listmethod'], $result);

		$this->expectException(\RuntimeException::class);
		View::processName('RandomClass::method');
		$this->expectException(\RuntimeException::class);
		View::processName('arbitrary input');
	}
}
