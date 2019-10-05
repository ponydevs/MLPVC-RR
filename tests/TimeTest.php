<?php

use App\Time;
use PHPUnit\Framework\TestCase;

class TimeTest extends TestCase {
  public function testDifference() {
    $now = strtotime('2016-12-03T12:03:56Z');
    $target = strtotime('2015-11-01T11:00:00Z');
    $result = Time::difference($now, $target);
    self::assertTrue($result['past'], '$target is in the past');
    self::assertEquals(1, $result['year'], 'Year diff calculation fails');
    self::assertEquals(1, $result['month'], 'Month diff calculation fails');
    self::assertEquals(2, $result['day'], 'Day diff calculation fails');
    self::assertEquals(1, $result['hour'], 'Hour diff calculation fails');
    self::assertEquals(3, $result['minute'], 'Minute diff calculation fails');
    self::assertEquals(56, $result['second'], 'Seoond diff calculation fails');
  }

  public function testFormat() {
    $now = strtotime('2016-12-03T12:03:56Z');
    $result = Time::format($now);
    self::assertEquals('2016-12-03T12:03:56Z', $result, 'ISO format fails');
    $result = Time::format($now, Time::FORMAT_FULL);
    self::assertEquals('Saturday, 3rd December 2016, 12:03:56 GMT', $result, 'Full format fails');
    $result = Time::format($now, Time::FORMAT_READABLE, strtotime('+1 day', $now));
    self::assertEquals('a day ago', $result, 'Readable format fails (1 day)');
    $result = Time::format($now, Time::FORMAT_READABLE, strtotime('+1 day +5 months', $now));
    self::assertEquals('5 months ago', $result, 'Readable format fails (1 day 5 months)');
  }

  public function testTag() {
    $now = strtotime('2016-12-03T12:03:56Z');
    $result = Time::tag($now, false, Time::TAG_ALLOW_DYNTIME, $now);
    self::assertEquals("<time datetime='2016-12-03T12:03:56Z' title='Saturday, 3rd December 2016, 12:03:56 GMT'>a few seconds ago</time>", $result);
    $result = Time::tag($now, Time::TAG_EXTENDED, Time::TAG_ALLOW_DYNTIME, $now);
    self::assertEquals("<time datetime='2016-12-03T12:03:56Z'>Saturday, 3rd December 2016, 12:03:56 GMT</time>", $result);
    $result = Time::tag($now, Time::TAG_EXTENDED, Time::TAG_NO_DYNTIME, $now);
    self::assertEquals("<time datetime='2016-12-03T12:03:56Z' class='nodt'>Saturday, 3rd December 2016, 12:03:56 GMT</time>", $result);
    $result = Time::tag($now, Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME, $now);
    self::assertEquals("<time datetime='2016-12-03T12:03:56Z' class='no-dynt-el'>Saturday, 3rd December 2016, 12:03:56 GMT</time>", $result);
  }
}
