<?php

use App\Models\Post;
use App\Models\Show;
use App\Time;
use PHPUnit\Framework\TestCase;

class PostTest extends TestCase {
  public function testGetIdString() {
    $request = new Post([
      'id' => 1,
      'requested_by' => 1,
    ]);
    $result = $request->getIdString();
    self::assertEquals('post-1', $result);

    $reservation = new Post([
      'id' => 1,
      'reserved_by' => 1,
    ]);
    $result = $reservation->getIdString();
    self::assertEquals('post-1', $result);
  }

  public function testToLink() {
    $episode = new Show([
      'id' => 1,
      'season' => 1,
      'episode' => 1,
      'type' => 'episode',
    ]);

    $request = new Post([
      'id' => 1,
      'requested_by' => 1,
      'show_id' => $episode->id,
    ]);
    $result = $request->toURL($episode);
    self::assertEquals('/episode/S1E1#post-1', $result);

    $reservation = new Post([
      'id' => 1,
      'reserved_by' => 1,
      'show_id' => $episode->id,
    ]);
    $result = $reservation->toURL($episode);
    self::assertEquals('/episode/S1E1#post-1', $result);
  }

  public function testToAnchor() {
    $episode = new Show([
      'id' => 1,
      'season' => 1,
      'episode' => 1,
      'type' => 'episode',
    ]);
    $request = new Post([
      'id' => 1,
      'requested_by' => 1,
      'show_id' => $episode->id,
    ]);
    $result = $request->toAnchor(null, $episode);
    self::assertEquals("<a href='/episode/S1E1#post-1' >S1E1</a>", $result);
    $result = $request->toAnchor('Custom Text<', $episode);
    self::assertEquals("<a href='/episode/S1E1#post-1' >Custom Text&lt;</a>", $result);
    $result = $request->toAnchor('Custom Text<', $episode, true);
    self::assertEquals("<a href='/episode/S1E1#post-1' target=\"_blank\">Custom Text&lt;</a>", $result);
  }

  public function testIsOverdue() {
    $now = strtotime('2016-01-25T01:00:00Z');
    $eeservation = new Post([
      'id' => 1,
      'reserved_by' => 1,
    ]);
    self::assertFalse($eeservation->isOverdue($now), 'Reservations must not become overdue');

    $request = new Post([
      'id' => 1,
      'requested_by' => 1,
      'requested_at' => '2015-01-01T00:00:00Z',
      'reserved_by' => 1,
      'reserved_at' => '2016-01-25T00:00:00Z',
      'deviation_id' => 'dXXXXXX',
    ]);
    $result = $request->isOverdue($now);
    self::assertFalse($result, 'Finished requests must not become overdue');

    $request->deviation_id = null;

    $result = $request->isOverdue($now);
    self::assertFalse($result, 'Request must not be overdue yet (+3w)');

    $request->reserved_at = '2016-01-04T01:00:01Z';
    $result = $request->isOverdue($now);
    self::assertFalse($result, 'Request must not be overdue yet (+1s)');

    $request->reserved_at = '2016-01-04T01:00:00Z';
    $result = $request->isOverdue($now);
    self::assertEquals($now - Time::IN_SECONDS['week'] * 3, $request->reserved_at->getTimestamp(), 'reserved_at should match current time - 3 weeks');
    self::assertTrue($result, 'Request must be overdue by now (0s)');

    $request->reserved_at = '2016-01-04T00:59:59';
    $result = $request->isOverdue($now);
    self::assertTrue($result, 'Request must be overdue by now (-1s)');
  }

  public function testProcessLabel() {
    $post = new Post([
      'label' => 'Fluttershy (entire scene)',
    ]);
    $result = $post->processLabel();
    self::assertEquals('Fluttershy (<strong class="color-darkblue">entire scene</strong>)', $result, 'Must pass regular transformation');
    $post->label = 'Fluttershy (ENTIRE SCENE)';
    $result = $post->processLabel();
    self::assertEquals('Fluttershy (<strong class="color-darkblue">Entire Scene</strong>)', $result, 'Only initial caps should be preserved');
    $post->label = 'Fluttershy (FULL SCENE)';
    $result = $post->processLabel();
    self::assertEquals('Fluttershy (<strong class="color-darkblue">Full Scene</strong>)', $result, 'Only initial caps should be preserved (ALL CAPS)');
    $post->label = 'Fluttershy (full SCENE)';
    $result = $post->processLabel();
    self::assertEquals('Fluttershy (<strong class="color-darkblue">full Scene</strong>)', $result, 'Only initial caps should be preserved (2nd word ALL CAPS)');
    $post->label = 'Fluttershy (full bodied version)';
    $result = $post->processLabel();
    self::assertEquals('Fluttershy (<strong class="color-darkblue">full body</strong> version)', $result, 'Transformation of "full bodied version" fails');
    $post->label = 'Fluttershy (full-body)';
    $result = $post->processLabel();
    self::assertEquals('Fluttershy (<strong class="color-darkblue">full body</strong>)', $result, 'Transformation of "full-body" fails');
    $post->label = 'Fluttershy (full bodied)';
    $result = $post->processLabel();
    self::assertEquals('Fluttershy (<strong class="color-darkblue">full body</strong>)', $result, 'Transformation of "full bodied" fails');
    $post->label = 'Fluttershy (face-only)';
    $result = $post->processLabel();
    self::assertEquals('Fluttershy (<strong class="color-darkblue">face only</strong>)', $result, 'Transformation of "face-only" fails');
    $post->label = 'All characters (separate vectors)';
    $result = $post->processLabel();
    self::assertEquals('All characters (<strong class="color-darkblue">separate vectors</strong>)', $result, 'Transformation of "separate vectors" fails');

    $post = new Post([
      'label' => 'Fluttershy\'s cottage',
    ]);
    $result = $post->processLabel();
    self::assertEquals('Fluttershy&rsquo;s cottage', $result, 'Transformation of single apostrophe fails');

    $post = new Post([
      'label' => 'Rainbow Dash: \'\'I want to be a Wonderbolt',
    ]);
    $result = $post->processLabel();
    self::assertEquals('Rainbow Dash: "I want to be a Wonderbolt', $result, 'Transformation of double apostrophe fails');

    $post = new Post([
      'label' => 'Rainbow Dash: "I want to be a Wonderbolt"',
    ]);
    $result = $post->processLabel();
    self::assertEquals('Rainbow Dash: &ldquo;I want to be a Wonderbolt&rdquo;', $result, 'Transformation of pairs of quotation marks fails');
    $post = new Post([
      'label' => 'Rainbow Dash: "I want to be a Wonderbolt',
    ]);
    $result = $post->processLabel();
    self::assertEquals('Rainbow Dash: "I want to be a Wonderbolt', $result, 'Transformation of a single quotation mark fails');

    $post = new Post([
      'label' => 'So... whaddaya say?',
    ]);
    $result = $post->processLabel();
    self::assertEquals('So&hellip; whaddaya say?', $result, 'Transformation of three periods fails');

    $post = new Post([
      'label' => '[cuteness intensifies]',
    ]);
    $result = $post->processLabel();
    self::assertEquals('<span class="intensify">cuteness intensifies</span>', $result, 'Transformation of [{s} instensifies] fails');
    $post = new Post([
      'label' => '[two words intensifies]',
    ]);
    $result = $post->processLabel();
    self::assertEquals('<span class="intensify">two words intensifies</span>', $result, 'Transformation of [{s1} {sN} instensifies] fails');
  }
}
