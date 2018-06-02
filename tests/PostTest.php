<?php

use App\Models\Episode;
use App\Models\Post;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

define('VALID_UUID', Uuid::uuid4());

class PostTest extends TestCase {
	public function testGetOldID(){
		$Request = new Post([
			'id' => 1,
			'old_id' => 1,
			'requested_by' => VALID_UUID,
		]);
		$result = $Request->getOldID();
		self::assertEquals('request-1', $result);

		$Reservation = new Post([
			'id' => 1,
			'old_id' => 1,
			'reserved_by' => VALID_UUID,
		]);
		$result = $Reservation->getOldID();
		self::assertEquals('reservation-1', $result);
	}

	public function testToLink(){
		$Episode = new Episode([
			'season' => 1,
			'episode' => 1,
		]);
		$Request = new Post([
			'id' => 1,
			'requested_by' => VALID_UUID,
			'season' => $Episode->season,
			'episode' => $Episode->episode,
		]);
		$result = $Request->toURL($Episode);
		self::assertEquals('/episode/S1E1#post-1', $result);

		$Reservation = new Post([
			'id' => 1,
			'reserved_by' => VALID_UUID,
			'season' => $Episode->season,
			'episode' => $Episode->episode,
		]);
		$result = $Reservation->toURL($Episode);
		self::assertEquals('/episode/S1E1#post-1', $result);
	}

	public function testToAnchor(){
		$Episode = new Episode([
			'season' => 1,
			'episode' => 1,
		]);
		$Request = new Post([
			'id' => 1,
			'requested_by' => VALID_UUID,
			'season' => $Episode->season,
			'episode' => $Episode->episode,
		]);
		$result = $Request->toAnchor(null,$Episode);
		self::assertEquals("<a href='/episode/S1E1#post-1' >S1E1</a>", $result);
		$result = $Request->toAnchor('Custom Text<',$Episode);
		self::assertEquals("<a href='/episode/S1E1#post-1' >Custom Text&lt;</a>", $result);
		$result = $Request->toAnchor('Custom Text<',$Episode,true);
		self::assertEquals("<a href='/episode/S1E1#post-1' target=\"_blank\">Custom Text&lt;</a>", $result);
	}

	public function testIsTransferable(){
		$Request = new Post([
			'id' => 1,
			'requested_by' => VALID_UUID,
			'requested_at' => '2016-01-01T16:00:00Z',
		]);
		$result = $Request->isTransferable();
		self::assertTrue($result,"Posts that aren't reserved by anyone must be transferable");

		$Request->reserved_by = 'c0592f2b-5adc-49c1-be1d-56efc4bdad88';
		$Request->reserved_at = '2016-01-14T00:00:00Z';
		$now = strtotime('2016-01-15T00:00:00Z');

		$result = $Request->isTransferable($now);
		self::assertFalse($result, 'The post must not be transferable yet (+4d)');

		$Request->reserved_at = '2016-01-10T00:00:01Z';
		$result = $Request->isTransferable($now);
		self::assertFalse($result, 'The post must not be transferable yet (+1s)');

		$Request->reserved_at = '2016-01-10T00:00:00Z';
		$result = $Request->isTransferable($now);
		self::assertTrue($result, 'The post must be transferable by now (0s)');

		$Request->reserved_at = '2016-01-09T23:59:59Z';
		$result = $Request->isTransferable($now);
		self::assertTrue($result, 'The post must be transferable by now (-1s)');
	}

	public function testIsOverdue(){
		$now = strtotime('2016-01-25T01:00:00Z');
		$Reservation = new Post([
			'id' => 1,
			'reserved_by' => VALID_UUID,
		]);
		self::assertFalse($Reservation->isOverdue($now), 'Reservations must not become overdue');

		$Request = new Post([
			'id' => 1,
			'requested_by' => VALID_UUID,
			'requested_at' => '2015-01-01T00:00:00Z',
			'reserved_by' => VALID_UUID,
			'reserved_at' => '2016-01-25T00:00:00Z',
			'deviation_id' => 'dXXXXXX',
		]);
		$result = $Request->isOverdue($now);
		self::assertFalse($result, 'Finished requests must not become overdue');

		$Request->deviation_id = null;

		$result = $Request->isOverdue($now);
		self::assertFalse($result, 'Request must not be overdue yet (+3w)');

		$Request->reserved_at = '2016-01-04T01:00:01Z';
		$result = $Request->isOverdue($now);
		self::assertFalse($result, 'Request must not be overdue yet (+1s)');

		$Request->reserved_at = '2016-01-04T01:00:00Z';
		$result = $Request->isOverdue($now);
		self::assertEquals($now - \App\Time::IN_SECONDS['week']*3, $Request->reserved_at->getTimestamp(), 'reserved_at should match current time - 3 weeks');
		self::assertTrue($result, 'Request must be overdue by now (0s)');

		$Request->reserved_at = '2016-01-04T00:59:59';
		$result = $Request->isOverdue($now);
		self::assertTrue($result, 'Request must be overdue by now (-1s)');
	}

	public function testProcessLabel(){
		$Post = new Post([
			'label' => 'Fluttershy (entire scene)',
		]);
		$result = $Post->processLabel();
		self::assertEquals('Fluttershy (<strong class="color-darkblue">entire scene</strong>)', $result, 'Must pass regular transformation');
		$Post->label = 'Fluttershy (ENTIRE SCENE)';
		$result = $Post->processLabel();
		self::assertEquals('Fluttershy (<strong class="color-darkblue">Entire Scene</strong>)', $result, 'Only initial caps should be preserved');
		$Post->label = 'Fluttershy (FULL SCENE)';
		$result = $Post->processLabel();
		self::assertEquals('Fluttershy (<strong class="color-darkblue">Full Scene</strong>)', $result, 'Only initial caps should be preserved (ALL CAPS)');
		$Post->label = 'Fluttershy (full SCENE)';
		$result = $Post->processLabel();
		self::assertEquals('Fluttershy (<strong class="color-darkblue">full Scene</strong>)', $result, 'Only initial caps should be preserved (2nd word ALL CAPS)');
		$Post->label = 'Fluttershy (full bodied version)';
		$result = $Post->processLabel();
		self::assertEquals('Fluttershy (<strong class="color-darkblue">full body</strong> version)', $result, 'Transformation of "full bodied version" fails');
		$Post->label = 'Fluttershy (full-body)';
		$result = $Post->processLabel();
		self::assertEquals('Fluttershy (<strong class="color-darkblue">full body</strong>)', $result, 'Transformation of "full-body" fails');
		$Post->label = 'Fluttershy (full bodied)';
		$result = $Post->processLabel();
		self::assertEquals('Fluttershy (<strong class="color-darkblue">full body</strong>)', $result, 'Transformation of "full bodied" fails');
		$Post->label = 'Fluttershy (face-only)';
		$result = $Post->processLabel();
		self::assertEquals('Fluttershy (<strong class="color-darkblue">face only</strong>)', $result, 'Transformation of "face-only" fails');

		$Post = new Post([
			'label' => 'Fluttershy\'s cottage',
		]);
		$result = $Post->processLabel();
		self::assertEquals('Fluttershy&rsquo;s cottage', $result, 'Transformation of single apostrophe fails');

		$Post = new Post([
			'label' => 'Rainbow Dash: \'\'I want to be a Wonderbolt',
		]);
		$result = $Post->processLabel();
		self::assertEquals('Rainbow Dash: "I want to be a Wonderbolt', $result, 'Transformation of double apostrophe fails');

		$Post = new Post([
			'label' => 'Rainbow Dash: "I want to be a Wonderbolt"',
		]);
		$result = $Post->processLabel();
		self::assertEquals('Rainbow Dash: &ldquo;I want to be a Wonderbolt&rdquo;', $result, 'Transformation of pairs of quotation marks fails');
		$Post = new Post([
			'label' => 'Rainbow Dash: "I want to be a Wonderbolt',
		]);
		$result = $Post->processLabel();
		self::assertEquals('Rainbow Dash: "I want to be a Wonderbolt', $result, 'Transformation of a single quotation mark fails');

		$Post = new Post([
			'label' => 'So... whaddaya say?',
		]);
		$result = $Post->processLabel();
		self::assertEquals('So&hellip; whaddaya say?', $result, 'Transformation of three periods fails');

		$Post = new Post([
			'label' => '[cuteness intensifies]',
		]);
		$result = $Post->processLabel();
		self::assertEquals('<span class="intensify">cuteness intensifies</span>', $result, 'Transformation of [{s} instensifies] fails');
		$Post = new Post([
			'label' => '[two words intensifies]',
		]);
		$result = $Post->processLabel();
		self::assertEquals('<span class="intensify">two words intensifies</span>', $result, 'Transformation of [{s1} {sN} instensifies] fails');
	}
}
