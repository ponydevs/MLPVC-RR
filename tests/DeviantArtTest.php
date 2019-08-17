<?php

use App\DeviantArt;
use PHPUnit\Framework\TestCase;

class DeviantArtTest extends TestCase {
  public function testNormalizeStashID() {
    $result = DeviantArt::nomralizeStashID('76dfg312kla');
    self::assertEquals('076dfg312kla', $result);
    $result = DeviantArt::nomralizeStashID('76dfg312kla4');
    self::assertEquals('76dfg312kla4', $result);
    $result = DeviantArt::nomralizeStashID('000adfg312kla4');
    self::assertEquals('0adfg312kla4', $result);
  }
}
