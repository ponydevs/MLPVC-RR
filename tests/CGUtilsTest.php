<?php

use App\CGUtils;
use PHPUnit\Framework\TestCase;

class CGUtilsTest extends TestCase {
	public function testRoundHex(){
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

	public function testExpandEpisodeTagName(){
		$in = 's 5 e 1';
		$result = CGUtils::expandEpisodeTagName($in);
		self::assertEquals($in, $result, 'Should return invalid input unaltered');
		$in = 'movie 4';
		$result = CGUtils::expandEpisodeTagName($in);
		self::assertEquals($in, $result, 'Should return invalid input unaltered');

		$result = CGUtils::expandEpisodeTagName('s5e1');
		self::assertEquals('S05 E01', $result);
		$result = CGUtils::expandEpisodeTagName('s05e11');
		self::assertEquals('S05 E11', $result);
		$result = CGUtils::expandEpisodeTagName('s8e26');
		self::assertEquals('S08 E26', $result, 'Only parse seasons up to 9 and episodes up to 26');
		$result = CGUtils::expandEpisodeTagName('s9e26');
		self::assertEquals('S09 E26', $result, 'Only parse seasons up to 9 and episodes up to 26');
		$result = CGUtils::expandEpisodeTagName('s10e26');
		self::assertEquals('s10e26', $result, 'Only parse seasons up to 9');
		$result = CGUtils::expandEpisodeTagName('s8e27');
		self::assertEquals('s8e27', $result, 'Only parse episodes up to 26');
		$result = CGUtils::expandEpisodeTagName('s99e99');
		self::assertEquals('s99e99', $result, 'Only parse seasons up to 9 and episodes up to 26');
		$result = CGUtils::expandEpisodeTagName('movie#3');
		self::assertEquals('Movie #3', $result);
		$result = CGUtils::expandEpisodeTagName('movie#678231');
		self::assertEquals('Movie #678231', $result, 'Parse unlimited movie numbers');
	}
}
