<?php

use PHPUnit\Framework\TestCase;

class PostTest extends TestCase {
	function testGetID(){
		$Request = new App\Request([
			'id' => 1,
		]);
		$result = $Request->getID();
		self::assertEquals('request-1', $result);

		$Reservation = new App\Reservation([
			'id' => 1,
		]);
		$result = $Reservation->getID();
		self::assertEquals('reservation-1', $result);
	}

	function testToLink(){
		$Request = new App\Request([
			'id' => 1,
			'season' => 1,
			'episode' => 1,
		]);
		$result = $Request->toLink();
		self::assertEquals('/episode/S1E1#request-1', $result);

		$Reservation = new App\Reservation([
			'id' => 1,
			'season' => 1,
			'episode' => 1,
		]);
		$result = $Reservation->toLink();
		self::assertEquals('/episode/S1E1#reservation-1', $result);
	}

	function testToAnchor(){
		$Request = new App\Request([
			'id' => 1,
			'season' => 1,
			'episode' => 1,
		]);
		$result = $Request->toAnchor();
		self::assertEquals("<a href='/episode/S1E1#request-1'>S1E1</a>", $result);
		$result = $Request->toAnchor('Custom Text<');
		self::assertEquals("<a href='/episode/S1E1#request-1'>Custom Text&lt;</a>", $result);
		$result = $Request->toAnchor('Custom Text<',null,true);
		self::assertEquals("<a href='/episode/S1E1#request-1' target='_blank'>Custom Text&lt;</a>", $result);
	}

	function testIsTransferable(){
		$now = strtotime('2016-01-15T00:00:00Z');
		$Request = new App\Request([
			'reserved_by' => 'c0592f2b-5adc-49c1-be1d-56efc4bdad88',
			'reserved_at' => '2016-01-14T22:00:00Z',
			'posted' => '2016-01-01T16:00:00Z',
		]);
		$result = $Request->isTransferable($now);
		self::assertFalse($result);
		$Request->reserved_at = '2016-01-10T00:00:01Z';
		$result = $Request->isTransferable($now);
		self::assertFalse($result);
		$Request->reserved_at = '2016-01-10T00:00:00Z';
		$result = $Request->isTransferable($now);
		self::assertTrue($result);
		$Request->reserved_at = '2016-01-09T23:59:59Z';
		$result = $Request->isTransferable($now);
		self::assertTrue($result);
	}

	function testIsOverdue(){
		// TODO Make test
	}

	function testProcessLabel(){
		// TODO Make test
	}
}
