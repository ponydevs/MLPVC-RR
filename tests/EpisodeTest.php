<?php

use PHPUnit\Framework\TestCase;

class EpisodeTest extends TestCase {
	public function testGetID(){
		// Single-part test
		$Episode = new \App\Models\Show([
			'season' => 1,
			'episode' => 1,
		]);
		self::assertFalse($Episode->twoparter);
		$result = $Episode->getID();
		self::assertEquals('S1E1', $result);
		$result = $Episode->getID(['pad' => true]);
		self::assertEquals('S01 E01', $result);

		// Two-parter test
		$Episode = new \App\Models\Show([
			'season' => 1,
			'episode' => 1,
			'twoparter' => true,
		]);
		self::assertTrue($Episode->twoparter);
		$result = $Episode->getID();
		self::assertEquals('S1E1-2', $result);
		$result = $Episode->getID(['pad' => true]);
		self::assertEquals('S01 E01-02', $result);

		// Movie test
		$Movie = new \App\Models\Show([
			'season' => 0,
			'episode' => 1,
			'twoparter' => true,
		]);
		$result = $Movie->getID();
		self::assertEquals('Movie#1', $result);
		self::assertNotEquals('Movie#1-2', $result);
	}

	public function testMovieSafeTitle(){
		$Movie = new \App\Models\Show([
			'season' => 0,
			'episode' => 1,
			'title' => "A#bc-d'?e",
		]);
		$result = $Movie->safeTitle();
		self::assertEquals('A-bc-d-e', $result);
	}

	public function testIs(){
		$EpisodeOne = new \App\Models\Show([
			'season' => 1,
			'episode' => 1,
		]);
		$EpisodeTwo = new \App\Models\Show([
			'season' => 1,
			'episode' => 5,
		]);
		$EpisodeThree = new \App\Models\Show([
			'season' => 5,
			'episode' => 1,
		]);
		$EpisodeFour = new \App\Models\Show([
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

	public function testAddAiringData(){
		$airs = '2016-01-10T00:00:00Z';
		$Episode = new \App\Models\Show([
			'airs' => $airs,
		]);

		$now = strtotime('2016-01-09T00:00:00Z');
		$willairts = $Episode->willHaveAiredBy();
		$displayed = $Episode->isDisplayed($now);
		self::assertEquals($willairts, strtotime('+30 minutes', strtotime($airs)), "Episode should be 'aired' 30 minutes after 'airs'");
		self::assertFalse($displayed);

		$now = strtotime('2016-01-09T00:00:01Z');
		$displayed = $Episode->isDisplayed($now);
		$aired = $Episode->hasAired($now);
		self::assertTrue($displayed);
		self::assertFalse($aired);

		$now = strtotime('2016-01-10T00:00:01Z');
		$aired = $Episode->hasAired($now);
		self::assertFalse($aired, "Episode should not be immediately 'aired' after airs");

		$now = strtotime('2016-01-10T00:30:01Z');
		$aired = $Episode->hasAired($now);
		self::assertTrue($aired, "Episode should be 'aired' 30 minutes after airs");


		$Episode = new \App\Models\Show([
			'airs' => $airs,
			'twoparter' => true,
		]);

		$willairts = $Episode->willHaveAiredBy();
		self::assertEquals($willairts, strtotime('+60 minutes', strtotime($airs)), "Two-parter episode should be 'aired' 60 minutes after 'airs'");

		$now = strtotime('2016-01-10T01:00:01Z');
		$aired = $Episode->hasAired($now);
		self::assertTrue($aired, "Two-parter episode should be 'aired' 60 minutes after airs");

		$Movie = new \App\Models\Show([
			'season' => 0,
			'airs' => $airs,
		]);

		$now = strtotime('2016-01-09T00:00:00Z');
		$willairts = $Movie->willHaveAiredBy();
		$displayed = $Movie->isDisplayed($now);
		self::assertEquals($willairts, strtotime('+2 hours', strtotime($airs)), "Movie should be 'aired' 2 hours after 'airs'");
		self::assertFalse($displayed);

		$now = strtotime('2016-01-09T00:00:01Z');
		$displayed = $Movie->isDisplayed($now);
		$aired = $Movie->hasAired($now);
		self::assertTrue($displayed);
		self::assertFalse($aired);

		$now = strtotime('2016-01-10T00:00:01Z');
		$aired = $Movie->hasAired($now);
		self::assertFalse($aired, "Movie should not be immediately 'aired' after airs");

		$now = strtotime('2016-01-10T02:00:01Z');
		$aired = $Movie->hasAired($now);
		self::assertTrue($aired, "Movie should be 'aired' 2 hours after 'airs'");
	}

	public function testFormatTitle(){
		$Episode = new \App\Models\Show([
			'season' => 1,
			'episode' => 1,
			'title' => 'Yarr harr<',
		]);
		$result = $Episode->formatTitle();
		self::assertEquals('S01 E01: Yarr harr<', $result);
		$result = $Episode->formatTitle(true, 'title');
		self::assertEquals('Yarr harr&lt;', $result);
	}

	public function testFormatURL(){
		$Episode = new \App\Models\Show([
			'season' => 1,
			'episode' => 1,
		]);
		$result = $Episode->toURL();
		self::assertEquals('/episode/S1E1', $result);

		$Episode = new \App\Models\Show([
			'season' => 1,
			'episode' => 1,
			'twoparter' => true,
		]);
		$result = $Episode->toURL();
		self::assertEquals('/episode/S1E1-2', $result);

		$Movie = new \App\Models\Show([
			'season' => 0,
			'episode' => 1,
		]);
		$result = $Movie->toURL();
		self::assertEquals('/movie/1', $result);
		$Movie->title = 'Yarr  @@@ harr';
		$result = $Movie->toURL();
		self::assertEquals('/movie/1-Yarr-harr', $result);
	}

	public function testFormatScore(){
		$Episode = new \App\Models\Show();
		$Episode->score = 3.2;
		self::assertEquals('3.2', $Episode->score);
		$Episode->score = 1;
		self::assertEquals('1', $Episode->score, 'Episode score must not have redundant decimal places');
		$Episode->score = 1.12;
		self::assertEquals('1.1', $Episode->score, 'Episode score must not have more than one decimal place');
	}
}
