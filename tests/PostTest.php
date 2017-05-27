<?php

use PHPUnit\Framework\TestCase;

class PostTest extends TestCase {
	function testGetID(){
		$Request = new \App\Models\Request([
			'id' => 1,
		]);
		$result = $Request->getID();
		self::assertEquals('request-1', $result);

		$Reservation = new \App\Models\Reservation([
			'id' => 1,
		]);
		$result = $Reservation->getID();
		self::assertEquals('reservation-1', $result);
	}

	function testToLink(){
		$Request = new \App\Models\Request([
			'id' => 1,
			'season' => 1,
			'episode' => 1,
		]);
		$result = $Request->toLink();
		self::assertEquals('/episode/S1E1#request-1', $result);

		$Reservation = new \App\Models\Reservation([
			'id' => 1,
			'season' => 1,
			'episode' => 1,
		]);
		$result = $Reservation->toLink();
		self::assertEquals('/episode/S1E1#reservation-1', $result);
	}

	function testToAnchor(){
		$Request = new \App\Models\Request([
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
		$Request = new \App\Models\Request([
			'reserved_at' => '2016-01-14T22:00:00Z',
			'posted' => '2016-01-01T16:00:00Z',
		]);
		$result = $Request->isTransferable($now);
		self::assertTrue($result,"Posts that aren't reserved by anyone must be transferable");

		$Request->reserved_by = 'c0592f2b-5adc-49c1-be1d-56efc4bdad88';
		$result = $Request->isTransferable($now);
		self::assertFalse($result, "The post must not be transferable yet");
		$Request->reserved_at = '2016-01-10T00:00:01Z';
		$result = $Request->isTransferable($now);
		self::assertFalse($result, "The post must not be transferable yet");
		$Request->reserved_at = '2016-01-10T00:00:00Z';
		$result = $Request->isTransferable($now);
		self::assertTrue($result, "The post must be transferable by now");
		$Request->reserved_at = '2016-01-09T23:59:59Z';
		$result = $Request->isTransferable($now);
		self::assertTrue($result, "The post must be transferable by now");
	}

	function testIsOverdue(){
		$now = strtotime('2016-01-25T01:00:00Z');
		$Reservation = new \App\Models\Reservation();
		self::assertFalse($Reservation->isOverdue($now), "Reservations must not become overdue");

		$Request = new \App\Models\Request([
			'reserved_by' => 'c0592f2b-5adc-49c1-be1d-56efc4bdad88',
			'reserved_at' => '2016-01-25T00:00:00Z',
			'posted' => '2015-01-01T00:00:00Z',
		]);
		$result = $Request->isOverdue($now);
		self::assertFalse($result, "Request must not be overdue yet");
		$Request->reserved_at = '2016-01-04T01:00:01Z';
		$result = $Request->isOverdue($now);
		self::assertFalse($result, "Request must not be overdue yet");
		$Request->reserved_at = '2016-01-04T01:00:00Z';
		$result = $Request->isOverdue($now);
		self::assertTrue($result, "Request must be overdue by now");
	}

	function testProcessLabel(){
		$Request = new App\Models\Request([
			'label' => 'Fluttershy (entire scene)',
		]);
		$result = $Request->processLabel();
		self::assertEquals('Fluttershy (<strong class="color-darkblue">entire scene</strong>)', $result, 'Must pass regular transformation');
		$Request->label = 'Fluttershy (ENTIRE SCENE)';
		$result = $Request->processLabel();
		self::assertEquals('Fluttershy (<strong class="color-darkblue">Entire Scene</strong>)', $result, 'Only initial caps should be preserved');
		$Request->label = 'Fluttershy (FULL SCENE)';
		$result = $Request->processLabel();
		self::assertEquals('Fluttershy (<strong class="color-darkblue">Full Scene</strong>)', $result, 'Only initial caps should be preserved (ALL CAPS)');
		$Request->label = 'Fluttershy (full SCENE)';
		$result = $Request->processLabel();
		self::assertEquals('Fluttershy (<strong class="color-darkblue">full Scene</strong>)', $result, 'Only initial caps should be preserved (2nd word ALL CAPS)');
		$Request->label = 'Fluttershy (full bodied version)';
		$result = $Request->processLabel();
		self::assertEquals('Fluttershy (<strong class="color-darkblue">full body</strong> version)', $result, 'Transformation of "full bodied version" fails');
		$Request->label = 'Fluttershy (full-body)';
		$result = $Request->processLabel();
		self::assertEquals('Fluttershy (<strong class="color-darkblue">full body</strong>)', $result, 'Transformation of "full-body" fails');
		$Request->label = 'Fluttershy (full bodied)';
		$result = $Request->processLabel();
		self::assertEquals('Fluttershy (<strong class="color-darkblue">full body</strong>)', $result, 'Transformation of "full bodied" fails');
		$Request->label = 'Fluttershy (face-only)';
		$result = $Request->processLabel();
		self::assertEquals('Fluttershy (<strong class="color-darkblue">face only</strong>)', $result, 'Transformation of "face-only" fails');

		$Request = new App\Models\Request([
			'label' => 'Fluttershy\'s cottage',
		]);
		$result = $Request->processLabel();
		self::assertEquals('Fluttershy&rsquo;s cottage', $result, 'Transformation of single apostrophe fails');

		$Request = new App\Models\Request([
			'label' => 'Rainbow Dash: \'\'I want to be a Wonderbolt',
		]);
		$result = $Request->processLabel();
		self::assertEquals('Rainbow Dash: "I want to be a Wonderbolt', $result, 'Transformation of double apostrophe fails');

		$Request = new App\Models\Request([
			'label' => 'Rainbow Dash: "I want to be a Wonderbolt"',
		]);
		$result = $Request->processLabel();
		self::assertEquals('Rainbow Dash: &ldquo;I want to be a Wonderbolt&rdquo;', $result, 'Transformation of pairs of quotation marks fails');
		$Request = new App\Models\Request([
			'label' => 'Rainbow Dash: "I want to be a Wonderbolt',
		]);
		$result = $Request->processLabel();
		self::assertEquals('Rainbow Dash: "I want to be a Wonderbolt', $result, 'Transformation of a single quotation mark fails');

		$Request = new App\Models\Request([
			'label' => 'So... whaddaya say?',
		]);
		$result = $Request->processLabel();
		self::assertEquals('So&hellip; whaddaya say?', $result, 'Transformation of three periods fails');

		$Request = new App\Models\Request([
			'label' => '[cuteness intensifies]',
		]);
		$result = $Request->processLabel();
		self::assertEquals('<span class="intensify">cuteness intensifies</span>', $result, 'Transformation of [{s} instensifies] fails');
		$Request = new App\Models\Request([
			'label' => '[two words intensifies]',
		]);
		$result = $Request->processLabel();
		self::assertEquals('<span class="intensify">two words intensifies</span>', $result, 'Transformation of [{s1} {sN} instensifies] fails');
	}
}
