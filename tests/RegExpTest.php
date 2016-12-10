<?php

use App\RegExp;
use PHPUnit\Framework\TestCase;

class RegExpTest extends TestCase {
	function testToString(){
		$result = (string) (new RegExp('^a[b-f]\D$','ui'));
		self::assertEquals('~^a[b-f]\D$~ui', $result);
	}

	function testJsExport(){
		$result = (new RegExp('^/a[b-f]\D$','ui'))->jsExport();
		self::assertEquals('/^\/a[b-f]\D$/i', $result);
	}

	function testEscapeBackslashes(){
		$result = RegExp::escapeBackslashes('\\');
		self::assertEquals('\\\\', $result);
	}
}
