<?php

use App\CGUtils;
use PHPUnit\Framework\TestCase;
use SeinopSys\RGBAColor;

class CGUtilsTest extends TestCase {
  public function testRoundHex() {
    $result = CGUtils::roundHex('#010302');
    self::assertEquals('#000000', $result, 'Should round edge values');
    $result = CGUtils::roundHex('#040501');
    self::assertEquals('#040500', $result, 'Should round only edge values');

    $result = CGUtils::roundHex('#fcfdfe');
    self::assertEquals('#FFFFFF', $result, 'Should round edge values');
    $result = CGUtils::roundHex('#f0fafd');
    self::assertEquals('#F0FAFF', $result, 'Should round only edge values');

    $result = CGUtils::roundHex('#adbcf0');
    self::assertEquals('#ADBCF0', $result, 'Should leave normal colors alone');
    $result = CGUtils::roundHex('#8a9f33');
    self::assertEquals('#8A9F33', $result, 'Should leave normal colors alone');
  }

  public function testGenerateGimpPalette() {
    $c = RGBAColor::parse('#0122ff');
    $ts = strtotime('2018-09-10T19:19:19+02:00');
    $colors = [
      [$c->red, $c->green, $c->blue, 'Mane & Tail | Outline'],
      [0, 0, 0, 'Mane & Tail | Fill'],
      [10, 100, 255, 'Coat | Outline'],
      [255, 255, 255, 'Glasses | Fill (90% opacity)'],
      [30, 30, 30, 'Hat | Thingies (<4 pieces)'],
    ];
    $file = CGUtils::generateGimpPalette('Test & test', $colors, $ts);

    $expected = <<<GPL
			GIMP Palette
			Name: Test & test
			Columns: 6
			#
			# Exported at: 2018-09-10 17:19:19 GMT
			#
			  1  34 255 Mane &amp; Tail | Outline
			  0   0   0 Mane &amp; Tail | Fill
			 10 100 255 Coat | Outline
			255 255 255 Glasses | Fill (90% opacity)
			 30  30  30 Hat | Thingies (&lt;4 pieces)
			
			GPL;

    self::assertEquals($expected, $file);
  }
}
