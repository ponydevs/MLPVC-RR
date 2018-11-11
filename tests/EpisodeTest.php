<?php

use PHPUnit\Framework\TestCase;

class EpisodeTest extends TestCase {
	public function testGetID(){
		// Single-part test
		$episode = new \App\Models\Show([
			'season' => 1,
			'episode' => 1,
			'type' => 'episode',
		]);
		self::assertFalse($episode->twoparter);
		$result = $episode->getID();
		self::assertEquals('S1E1', $result);
		$result = $episode->getID(['pad' => true]);
		self::assertEquals('S01 E01', $result);

		// Two-parter test
		$episode = new \App\Models\Show([
			'season' => 1,
			'episode' => 1,
			'twoparter' => true,
			'type' => 'episode',
		]);
		self::assertTrue($episode->twoparter);
		$result = $episode->getID();
		self::assertEquals('S1E1-2', $result);
		$result = $episode->getID(['pad' => true]);
		self::assertEquals('S01 E01-02', $result);

		// Movie test
		$movie = new \App\Models\Show([
			'id' => 1,
			'no' => 1,
			'twoparter' => true,
			'type' => 'movie',
		]);
		$result = $movie->getID();
		self::assertEquals('Movie#1', $result);
		self::assertNotEquals('Movie#1-2', $result);
	}

	public function testMovieSafeTitle(){
		$movie = new \App\Models\Show([
			'title' => "A#bc-d'?e",
		]);
		$result = $movie->safeTitle();
		self::assertEquals('A-bc-d-e', $result);
	}

	public function testIs(){
		$episode_one = new \App\Models\Show([
			'id' => 1,
			'season' => 1,
			'episode' => 1,
			'type' => 'episode',
		]);
		$episode_two = new \App\Models\Show([
			'id' => 2,
			'season' => 1,
			'episode' => 5,
			'type' => 'episode',
		]);
		$episode_three = new \App\Models\Show([
			'id' => 3,
			'season' => 5,
			'episode' => 1,
			'type' => 'episode',
		]);
		$episode_four = new \App\Models\Show([
			'id' => 1,
			'season' => 1,
			'episode' => 1,
			'type' => 'episode',
		]);
		$result = $episode_one->is($episode_two);
		self::assertFalse($result);
		$result = $episode_one->is($episode_three);
		self::assertFalse($result);
		$result = $episode_one->is($episode_four);
		self::assertTrue($result);
		$result = $episode_two->is($episode_three);
		self::assertFalse($result);
	}

	public function testAddAiringData(){
		$airs = '2016-01-10T00:00:00Z';
		$episode = new \App\Models\Show([
			'airs' => $airs,
			'type' => 'episode',
		]);

		$now = strtotime('2016-01-09T00:00:00Z');
		$willairts = $episode->willHaveAiredBy();
		$displayed = $episode->isDisplayed($now);
		self::assertEquals($willairts, strtotime('+30 minutes', strtotime($airs)), "Episode should be 'aired' 30 minutes after 'airs'");
		self::assertFalse($displayed);

		$now = strtotime('2016-01-09T00:00:01Z');
		$displayed = $episode->isDisplayed($now);
		$aired = $episode->hasAired($now);
		self::assertTrue($displayed);
		self::assertFalse($aired);

		$now = strtotime('2016-01-10T00:00:01Z');
		$aired = $episode->hasAired($now);
		self::assertFalse($aired, "Episode should not be immediately 'aired' after airs");

		$now = strtotime('2016-01-10T00:30:01Z');
		$aired = $episode->hasAired($now);
		self::assertTrue($aired, "Episode should be 'aired' 30 minutes after airs");


		$episode = new \App\Models\Show([
			'airs' => $airs,
			'twoparter' => true,
			'type' => 'episode',
		]);

		$willairts = $episode->willHaveAiredBy();
		self::assertEquals($willairts, strtotime('+60 minutes', strtotime($airs)), "Two-parter episode should be 'aired' 60 minutes after 'airs'");

		$now = strtotime('2016-01-10T01:00:01Z');
		$aired = $episode->hasAired($now);
		self::assertTrue($aired, "Two-parter episode should be 'aired' 60 minutes after airs");

		$Movie = new \App\Models\Show([
			'airs' => $airs,
			'type' => 'movie',
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
			'type' => 'episode',
		]);
		$result = $Episode->formatTitle();
		self::assertEquals('S01 E01: Yarr harr<', $result);
		$result = $Episode->formatTitle(true, 'title');
		self::assertEquals('Yarr harr&lt;', $result);
	}

	public function testFormatURL(){
		$episode = new \App\Models\Show([
			'season' => 1,
			'episode' => 1,
			'type' => 'episode',
		]);
		$result = $episode->toURL();
		self::assertEquals('/episode/S1E1', $result);

		$episode = new \App\Models\Show([
			'season' => 1,
			'episode' => 1,
			'twoparter' => true,
			'type' => 'episode',
		]);
		$result = $episode->toURL();
		self::assertEquals('/episode/S1E1-2', $result);

		$movie = new \App\Models\Show([
			'id' => 1,
			'type' => 'movie',
		]);
		$result = $movie->toURL();
		self::assertEquals('/movie/1', $result);
		$movie->title = 'Yarr  @@@ harr';
		$result = $movie->toURL();
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
