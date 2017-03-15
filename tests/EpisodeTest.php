<?php

use PHPUnit\Framework\TestCase;

class EpisodeTest extends TestCase {
	function testGetID(){
		// Single-part test
		$Episode = new \App\Models\Episode([
			'season' => 1,
			'episode' => 1,
		]);
		$result = $Episode->getID();
		self::assertEquals("S1E1", $result);
		$result = $Episode->getID(['pad' => true]);
		self::assertEquals("S01 E01", $result);

		// Two-parter test
		$Episode = new \App\Models\Episode([
			'season' => 1,
			'episode' => 1,
			'twoparter' => true,
		]);
		$result = $Episode->getID();
		self::assertEquals("S1E1-2", $result);
		$result = $Episode->getID(['pad' => true]);
		self::assertEquals("S01 E01-02", $result);

		// Movie test
		$Movie = new \App\Models\Episode([
			'season' => 0,
			'episode' => 1,
			'twoparter' => true,
		]);
		$result = $Movie->getID();
		self::assertEquals("Movie", $result);
		$result = $Movie->getID(['append_num' => true]);
		self::assertEquals("Movie#1", $result);
		self::assertNotEquals("Movie#1-2", $result);
	}

	function testMovieSafeTitle(){
		$Movie = new \App\Models\Episode([
			'season' => 0,
			'episode' => 1,
			'title' => "A#bc-d'?e",
		]);
		$result = $Movie->movieSafeTitle();
		self::assertEquals("A-bc-d-e", $result);
	}

	function testIs(){
		$EpisodeOne = new \App\Models\Episode([
			'season' => 1,
			'episode' => 1,
		]);
		$EpisodeTwo = new \App\Models\Episode([
			'season' => 1,
			'episode' => 5,
		]);
		$EpisodeThree = new \App\Models\Episode([
			'season' => 5,
			'episode' => 1,
		]);
		$EpisodeFour = new \App\Models\Episode([
			'season' => 1,
			'episode' => 1,
		]);
		$result = $EpisodeOne->is($EpisodeTwo);
		self::assertFalse($result);
		$result = $EpisodeOne->is($EpisodeThree);
		self::assertFalse($result);
		$result = $EpisodeOne->is($EpisodeFour);
		self::assertTrue($result);
		$result = $EpisodeTwo->is($EpisodeThree);
		self::assertFalse($result);
	}

	function testAddAiringData(){
		$airs = '2016-01-10T00:00:00Z';
		$Episode = new \App\Models\Episode([
			'airs' => $airs,
		]);
		self::assertNull($Episode->displayed);
		self::assertNull($Episode->aired);
		self::assertNull($Episode->willairts);
		self::assertNull($Episode->willair);
		$Episode->addAiringData(strtotime('2016-01-09T00:00:00Z'));
		self::assertEquals($Episode->willairts, strtotime('+30 minutes', strtotime($airs)), "Episode should be 'aired' 30 minutes after 'airs'");
		self::assertFalse($Episode->displayed);
		$Episode->addAiringData(strtotime('2016-01-09T00:00:01Z'));
		self::assertTrue($Episode->displayed);
		self::assertFalse($Episode->aired);
		$Episode->addAiringData(strtotime('2016-01-10T00:00:01Z'));
		self::assertFalse($Episode->aired, "Episode should not be immediately 'aired' after airs");
		$Episode->addAiringData(strtotime('2016-01-10T00:30:01Z'));
		self::assertTrue($Episode->aired, "Episode should be 'aired' 30 minutes after airs");


		$Episode = new \App\Models\Episode([
			'airs' => $airs,
			'twoparter' => true,
		]);
		$Episode->addAiringData(strtotime('2016-01-09T00:00:00Z'));
		self::assertEquals($Episode->willairts, strtotime('+60 minutes', strtotime($airs)), "Two-parter episode should be 'aired' 60 minutes after 'airs'");
		$Episode->addAiringData(strtotime('2016-01-10T01:00:01Z'));
		self::assertTrue($Episode->aired, "Two-parter episode should be 'aired' 60 minutes after airs");

		$Movie = new \App\Models\Episode([
			'season' => 0,
			'airs' => $airs,
		]);
		self::assertNull($Movie->displayed);
		self::assertNull($Movie->aired);
		self::assertNull($Movie->willairts);
		self::assertNull($Movie->willair);
		$Movie->addAiringData(strtotime('2016-01-09T00:00:00Z'));
		self::assertEquals($Movie->willairts, strtotime('+2 hours', strtotime($airs)), "Movie should be 'aired' 2 hours after 'airs'");
		self::assertFalse($Movie->displayed);
		$Movie->addAiringData(strtotime('2016-01-09T00:00:01Z'));
		self::assertTrue($Movie->displayed);
		self::assertFalse($Movie->aired);
		$Movie->addAiringData(strtotime('2016-01-10T00:00:01Z'));
		self::assertFalse($Movie->aired, "Movie should not be immediately 'aired' after airs");
		$Movie->addAiringData(strtotime('2016-01-10T02:00:01Z'));
		self::assertTrue($Movie->aired, "Movie should be 'aired' 2 hours after 'airs'");
	}

	function testFormatTitle(){
		require_once "includes/constants.php";

		$Episode = new \App\Models\Episode([
			'season' => 1,
			'episode' => 1,
			'title' => 'Yarr harr<',
		]);
		$result = $Episode->formatTitle();
		self::assertEquals('S01 E01: Yarr harr<', $result);
		$result = $Episode->formatTitle(true, 'title');
		self::assertEquals('Yarr harr&lt;', $result);
	}

	function testFormatURL(){
		$Episode = new \App\Models\Episode([
			'season' => 1,
			'episode' => 1,
		]);
		$result = $Episode->toURL();
		self::assertEquals("/episode/S1E1", $result);

		$Episode = new \App\Models\Episode([
			'season' => 1,
			'episode' => 1,
			'twoparter' => true,
		]);
		$result = $Episode->toURL();
		self::assertEquals("/episode/S1E1-2", $result);

		$Movie = new \App\Models\Episode([
			'season' => 0,
			'episode' => 1,
		]);
		$result = $Movie->toURL();
		self::assertEquals("/movie/1", $result);
		$Movie->title = 'Yarr  @@@ harr';
		$result = $Movie->toURL();
		self::assertEquals("/movie/1-Yarr-harr", $result);
	}

	function testFormatScore(){
		$Episode = new \App\Models\Episode();
		$Episode->score = 3.2;
		$Episode->formatScore();
		self::assertEquals('3.2', $Episode->score);
		$Episode->score = 1;
		$Episode->formatScore();
		self::assertEquals('1', $Episode->score, 'Episode score must not have redundant decimal places');
		$Episode->score = 1.12;
		$Episode->formatScore();
		self::assertEquals('1.1', $Episode->score, 'Episode score must not have more than one decimal place');
	}
}
