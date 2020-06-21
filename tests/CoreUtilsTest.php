<?php

use App\CoreUtils;
use App\RegExp;
use PHPUnit\Framework\TestCase;

class CoreUtilsTest extends TestCase {
  public function testQueryStringAssoc():void {
    $result = CoreUtils::queryStringAssoc('?a=b&c=1');
    self::assertEquals([
      'a' => 'b',
      'c' => 1,
    ], $result);
  }

  public function testAposEncode():void {
    $result = CoreUtils::aposEncode("No Man's Lie");
    self::assertEquals('No Man&apos;s Lie', $result);
    $result = CoreUtils::aposEncode('"implying"');
    self::assertEquals('&quot;implying&quot;', $result);
  }

  public function testEscapeHTML():void {
    $result = CoreUtils::escapeHTML("<script>alert('XSS')</script>");
    self::assertEquals("&lt;script&gt;alert('XSS')&lt;/script&gt;", $result);
    $result = CoreUtils::escapeHTML('<');
    self::assertEquals('&lt;', $result);
    $result = CoreUtils::escapeHTML('>');
    self::assertEquals('&gt;', $result);
  }

  public function testPad():void {
    $result = CoreUtils::pad(1);
    self::assertEquals('01', $result);
    $result = CoreUtils::pad(10);
    self::assertEquals('10', $result);
  }

  public function testCapitalize():void {
    $result = CoreUtils::capitalize('apple pie');
    self::assertEquals('Apple pie', $result);
    $result = CoreUtils::capitalize('apple pie', true);
    self::assertEquals('Apple Pie', $result);
    $result = CoreUtils::capitalize('APPLE PIE', true);
    self::assertEquals('Apple Pie', $result);
    $result = CoreUtils::capitalize('aPpLe pIe', true);
    self::assertEquals('Apple Pie', $result);
  }

  public function testGetMaxUploadSize():void {
    $result = CoreUtils::getMaxUploadSize(['4M', '10M']);
    self::assertEquals('4 MB', $result);
    $result = CoreUtils::getMaxUploadSize(['4k', '4k']);
    self::assertEquals('4 KB', $result);
    $result = CoreUtils::getMaxUploadSize(['5G', '5M']);
    self::assertEquals('5 MB', $result);
  }

  public function testExportVars():void {
    $result = CoreUtils::exportVars([
      'a' => 1,
      'reg' => new RegExp('^ab?c$', 'gui'),
      'b' => true,
      's' => 'string',
    ]);
    self::assertEquals('<aside class="datastore">{"a":1,"reg":"/^ab?c$/gi","b":true,"s":"string"}</aside>'."\n", $result);
  }

  public function testSanitizeHtml():void {
    $result = CoreUtils::sanitizeHtml('<script>alert("XSS")</script><a href="/#hax">Click me</a>');
    self::assertEquals('&lt;script&gt;alert("XSS")&lt;/script&gt;&lt;a href="/#hax"&gt;Click me&lt;/a&gt;', $result, 'Attack attempt check');
    $result = CoreUtils::sanitizeHtml('Text<strong>Strong</strong><em>Emphasis</em>Text');
    self::assertEquals('Text<strong>Strong</strong><em>Emphasis</em>Text', $result, 'Basic whitelist check');
    $result = CoreUtils::sanitizeHtml('Text<b>Old Bold</b><i>Old Italic</i>Text');
    self::assertEquals('Text<strong>Old Bold</strong><em>Old Italic</em>Text', $result, 'Old tag to new tag transform check');
    $result = CoreUtils::sanitizeHtml('I like <code>while(true)window.open()</code> a lot<sup>*</sup>', ['code']);
    self::assertEquals('I like <code>while(true)window.open()</code> a lot&lt;sup&gt;*&lt;/sup&gt;', $result, 'Tag whitelist check');
  }

  public function testArrayToNaturalString():void {
    $result = CoreUtils::arrayToNaturalString([1]);
    self::assertEquals('1', $result);
    $result = CoreUtils::arrayToNaturalString([1, 2]);
    self::assertEquals('1 and 2', $result);
    $result = CoreUtils::arrayToNaturalString([1, 2, 3]);
    self::assertEquals('1, 2 and 3', $result);
    $result = CoreUtils::arrayToNaturalString([1, 2, 3, 4]);
    self::assertEquals('1, 2, 3 and 4', $result);
  }

  public function testCheckStringValidity():void {
    $result = CoreUtils::checkStringValidity('Oh my~!', 'Exclamation', '[^A-Za-z!\s]', true);
    self::assertEquals('Exclamation (Oh my~!) contains an invalid character: ~', $result);
    $result = CoreUtils::checkStringValidity('A_*cbe>#', 'String', '[^A-Za-z]', true);
    self::assertEquals('String (A_*cbe&gt;#) contains the following invalid characters: _, *, &gt; and #', $result);
  }

  public function testPosess():void {
    $result = CoreUtils::posess('David');
    self::assertEquals("David's", $result);
    $result = CoreUtils::posess('applications');
    self::assertEquals("applications'", $result);
  }

  public function testMakePlural():void {
    $result = CoreUtils::makePlural('apple', 2);
    self::assertEquals('apples', $result);
    $result = CoreUtils::makePlural('apple', 1);
    self::assertEquals('apple', $result);
    $result = CoreUtils::makePlural('apple', 2, true);
    self::assertEquals('2 apples', $result);
    $result = CoreUtils::makePlural('apple', 1, true);
    self::assertEquals('1 apple', $result);
    $result = CoreUtils::makePlural('staff member', 2, true);
    self::assertEquals('2 staff members', $result);
    $result = CoreUtils::makePlural('entry', 10, true);
    self::assertEquals('10 entries', $result);
  }

  public function testMakeSingular():void {
    $result = CoreUtils::makeSingular('Administrators');
    self::assertEquals('Administrator', $result);
    $result = CoreUtils::makeSingular('Staff');
    self::assertEquals('Staff', $result);
    $result = CoreUtils::makeSingular('Assistants');
    self::assertEquals('Assistant', $result);
  }

  public function testBrowserNameToClass():void {
    $result = CoreUtils::browserNameToClass('Chrome');
    self::assertEquals('chrome', $result);
    $result = CoreUtils::browserNameToClass('Edge');
    self::assertEquals('edge', $result);
    $result = CoreUtils::browserNameToClass('Firefox');
    self::assertEquals('firefox', $result);
    $result = CoreUtils::browserNameToClass('Internet Explorer');
    self::assertEquals('internetexplorer', $result);
    $result = CoreUtils::browserNameToClass('IE Mobile');
    self::assertEquals('iemobile', $result);
    $result = CoreUtils::browserNameToClass('Opera');
    self::assertEquals('opera', $result);
    $result = CoreUtils::browserNameToClass('Opera Mini');
    self::assertEquals('operamini', $result);
    $result = CoreUtils::browserNameToClass('Safari');
    self::assertEquals('safari', $result);
    $result = CoreUtils::browserNameToClass('Vivaldi');
    self::assertEquals('vivaldi', $result);
  }

  public function testTrim():void {
    $result = CoreUtils::trim('I    like    spaces');
    self::assertEquals('I like spaces', $result);
  }

  public function testAverage():void {
    $result = CoreUtils::average([1]);
    self::assertEquals(1, $result);
    $result = CoreUtils::average([1, 2]);
    self::assertEquals(1.5, $result);
    $result = CoreUtils::average([1, 2, 3]);
    self::assertEquals(2, $result);
  }

  public function testCutoff():void {
    $result = CoreUtils::cutoff('This is a long string', 10);
    self::assertEquals(10, mb_strlen($result));
    self::assertEquals('This is a…', $result);
  }

  public function testSet():void {
    $array = [];
    CoreUtils::set($array, 'key', 'value');
    self::assertArrayHasKey('key', $array);
    self::assertEquals('value', $array['key']);

    $object = new stdClass();
    CoreUtils::set($object, 'key', 'value');
    self::assertObjectHasAttribute('key', $object);
    self::assertEquals('value', $object->key);
  }

  public function testSha256():void {
    $data = 'a3d5f3e5a67f38cd6e7ad8cfe41245acf';
    $hash = CoreUtils::sha256($data);
    self::assertEquals('fcb0c71edf2df18c7d39accbbb46083d511ea091d7ec56727a6a9931d40f46d8', $hash);
  }

  public function testMergeQuery():void {
    $result = CoreUtils::mergeQuery('', '?t=12');
    self::assertEquals('?t=12', $result);
    $result = CoreUtils::mergeQuery('?a=b', '?a=c');
    self::assertEquals('?a=c', $result);
    $result = CoreUtils::mergeQuery('?a=b&c=d', '?c=e');
    self::assertEquals('?a=b&c=e', $result);
    $result = CoreUtils::mergeQuery('?test&a=b', '?a=c');
    self::assertEquals('?test&a=c', $result);
    $result = CoreUtils::mergeQuery('?a=b&c=d', '?c=e', ['a']);
    self::assertEquals('?c=e', $result);
    $result = CoreUtils::mergeQuery('?test&a=b', '?a=c', ['test']);
    self::assertEquals('?a=c', $result);
  }

  public function testAppendFragment():void {
    $result = CoreUtils::appendFragment('/a');
    self::assertEquals('', $result, 'Should return empty string if there is no fragment');
    $result = CoreUtils::appendFragment('/a#c');
    self::assertEquals('#c', $result);
    $result = CoreUtils::appendFragment('/a?b#c');
    self::assertEquals('#c', $result);
    $result = CoreUtils::appendFragment('/?c=#');
    self::assertEquals('', $result, 'Should return empty string if empty parameter is encountered');
  }

  public function testAbsoluteUrl():void {
    $result = 'https://example.com/path';
    CoreUtils::toAbsoluteUrl($result);
    self::assertEquals('https://example.com/path', $result);
    $result = '/test';
    CoreUtils::toAbsoluteUrl($result);
    self::assertEquals(ABSPATH.'test', $result);
    $result = '//example.com/path';
    CoreUtils::toAbsoluteUrl($result);
    self::assertEquals('//example.com/path', $result);
    $result = '/a/b/c';
    CoreUtils::toAbsoluteUrl($result);
    self::assertEquals(ABSPATH.'a/b/c', $result);
    $result = '/a/b/c#d';
    CoreUtils::toAbsoluteUrl($result);
    self::assertEquals(ABSPATH.'a/b/c#d', $result);
  }

  public function testStartsWith() {
    self::assertEquals(true, CoreUtils::startsWith('abcd', 'ab'));
    self::assertEquals(true, CoreUtils::startsWith(' abc', ' '));
    self::assertEquals(true, CoreUtils::startsWith('0123', '01'));

    self::assertEquals(false, CoreUtils::startsWith('abcd', 'cd'));
    self::assertEquals(false, CoreUtils::startsWith(' abc', 'c'));
    self::assertEquals(false, CoreUtils::startsWith('0123', '23'));
  }

  public function testEndsWith() {
    self::assertEquals(false, CoreUtils::endsWith('abcd', 'ab'));
    self::assertEquals(false, CoreUtils::endsWith(' abc', ' '));
    self::assertEquals(false, CoreUtils::endsWith('0123', '01'));

    self::assertEquals(true, CoreUtils::endsWith('abcd', 'cd'));
    self::assertEquals(true, CoreUtils::endsWith(' abc', 'c'));
    self::assertEquals(true, CoreUtils::endsWith('0123', '23'));
  }

  public function testContains() {
    self::assertEquals(true, CoreUtils::contains('abcd', 'ab'));
    self::assertEquals(true, CoreUtils::contains(' abc', 'ab'));
    self::assertEquals(true, CoreUtils::contains('0123', '12'));

    self::assertEquals(false, CoreUtils::contains('abcd', 'z'));
    self::assertEquals(false, CoreUtils::contains(' abc', 'cfd'));
    self::assertEquals(false, CoreUtils::contains('0123', '789'));
  }

  public function testTsDiff() {
    $now = strtotime('2018-10-21T11:40:30Z');
    self::assertEquals(10, CoreUtils::tsDiff(new ActiveRecord\DateTime('2018-10-21T11:40:20', new DateTimeZone('UTC')), $now));
    self::assertEquals(-10, CoreUtils::tsDiff(new ActiveRecord\DateTime('2018-10-21T11:40:40', new DateTimeZone('UTC')), $now));
  }

  public function testGenerateCacheKey() {
    self::assertEquals('apple_f_v1', CoreUtils::generateCacheKey(1, 'apple', false));
    self::assertEquals('apple_f_f_f_f_v2', CoreUtils::generateCacheKey(2, 'apple', false, false, false, false));
    self::assertEquals('f_apple_t_v3', CoreUtils::generateCacheKey(3, false, 'apple', true));
    self::assertEquals('apple_5.2_v1', CoreUtils::generateCacheKey(1, 'apple', 5.2));
    self::assertEquals('apple_300_v1', CoreUtils::generateCacheKey(1, 'apple', 300));
    self::assertEquals('what_the_f_v1', CoreUtils::generateCacheKey(1, 'what', 'the', false));
    self::assertEquals('in_the_name_of_null_v1', CoreUtils::generateCacheKey(1, 'in the name of', null));
  }
}
