<?php

use App\CoreUtils;
use App\DeviantArt;
use PHPUnit\Framework\TestCase;

class CoreUtilsTest extends TestCase {
	public function testQueryStringAssoc(){
		$result = CoreUtils::queryStringAssoc('?a=b&c=1');
		self::assertEquals([
			'a' => 'b',
			'c' => 1,
		], $result);
	}

	public function testAposEncode(){
		$result = CoreUtils::aposEncode("No Man's Lie");
		self::assertEquals('No Man&apos;s Lie', $result);
		$result = CoreUtils::aposEncode('"implying"');
		self::assertEquals('&quot;implying&quot;', $result);
	}

	public function testEscapeHTML(){
		$result = CoreUtils::escapeHTML("<script>alert('XSS')</script>");
		self::assertEquals("&lt;script&gt;alert('XSS')&lt;/script&gt;", $result);
		$result = CoreUtils::escapeHTML('<');
		self::assertEquals('&lt;', $result);
		$result = CoreUtils::escapeHTML('>');
		self::assertEquals('&gt;', $result);
	}

	public function testNotice(){
		$exception = false;
		try {
			CoreUtils::notice('invalid type','asd');
		}
		catch(Exception $e){ $exception = true; }
		self::assertTrue($exception, 'Invalid notice type must throw exception');

		$result = CoreUtils::notice('info','text');
		self::assertEquals("<div class='notice info'><p>text</p></div>", $result);

		$result = CoreUtils::notice('info','title','text');
		self::assertEquals("<div class='notice info'><label>title</label><p>text</p></div>", $result);

		$result = CoreUtils::notice('info','title',"mutliline\n\nnotice");
		self::assertEquals("<div class='notice info'><label>title</label><p>mutliline</p><p>notice</p></div>", $result);
	}

	public function testPad(){
		$result = CoreUtils::pad(1);
		self::assertEquals('01',$result);
		$result = CoreUtils::pad(10);
		self::assertEquals('10',$result);
	}

	public function testCapitalize(){
		$result = CoreUtils::capitalize('apple pie');
		self::assertEquals('Apple pie', $result);
		$result = CoreUtils::capitalize('apple pie', true);
		self::assertEquals('Apple Pie', $result);
		$result = CoreUtils::capitalize('APPLE PIE', true);
		self::assertEquals('Apple Pie', $result);
		$result = CoreUtils::capitalize('aPpLe pIe', true);
		self::assertEquals('Apple Pie', $result);
	}

	public function testGetMaxUploadSize(){
		$result = CoreUtils::getMaxUploadSize(['4M','10M']);
		self::assertEquals('4 MB', $result);
		$result = CoreUtils::getMaxUploadSize(['4k','4k']);
		self::assertEquals('4 KB', $result);
		$result = CoreUtils::getMaxUploadSize(['5G','5M']);
		self::assertEquals('5 MB', $result);
	}

	public function testExportVars(){
		$result = CoreUtils::exportVars([
			'a' => 1,
			'reg' => new \App\RegExp('^ab?c$','gui'),
			'b' => true,
			's' => 'string',
		]);
		/** @noinspection all */
		self::assertEquals('<script>var a=1,reg=/^ab?c$/gi,b=true,s="string"</script>', $result);
	}

	public function testSanitizeHtml(){
		$result = CoreUtils::sanitizeHtml('<script>alert("XSS")</script><a href="/#hax">Click me</a>');
		self::assertEquals('&lt;script&gt;alert("XSS")&lt;/script&gt;&lt;a href="/#hax"&gt;Click me&lt;/a&gt;',$result,'Attack attempt check');
		$result = CoreUtils::sanitizeHtml('Text<strong>Strong</strong><em>Emphasis</em>Text');
		self::assertEquals('Text<strong>Strong</strong><em>Emphasis</em>Text',$result,'Basic whitelist check');
		$result = CoreUtils::sanitizeHtml('Text<b>Old Bold</b><i>Old Italic</i>Text');
		self::assertEquals('Text<strong>Old Bold</strong><em>Old Italic</em>Text',$result,'Old tag to new tag transform check');
		$result = CoreUtils::sanitizeHtml('I like <code>while(true)window.open()</code> a lot<sup>*</sup>',['code']);
		self::assertEquals('I like <code>while(true)window.open()</code> a lot&lt;sup&gt;*&lt;/sup&gt;',$result,'Tag whitelist check');
	}

	public function testArrayToNaturalString(){
		$result = CoreUtils::arrayToNaturalString([1]);
		self::assertEquals('1', $result);
		$result = CoreUtils::arrayToNaturalString([1,2]);
		self::assertEquals('1 and 2', $result);
		$result = CoreUtils::arrayToNaturalString([1,2,3]);
		self::assertEquals('1, 2 and 3', $result);
		$result = CoreUtils::arrayToNaturalString([1,2,3,4]);
		self::assertEquals('1, 2, 3 and 4', $result);
	}

	public function testCheckStringValidity(){
		$result = CoreUtils::checkStringValidity('Oh my~!', 'Exclamation', '[^A-Za-z!\s]', true);
		self::assertEquals('Exclamation (Oh my~!) contains an invalid character: ~', $result);
		$result = CoreUtils::checkStringValidity('A_*cbe>#', 'String', '[^A-Za-z]', true);
		self::assertEquals('String (A_*cbe&gt;#) contains the following invalid characters: _, *, &gt; and #', $result);
	}

	public function testPosess(){
		$result = CoreUtils::posess('David');
		self::assertEquals('David’s', $result);
		$result = CoreUtils::posess('applications');
		self::assertEquals('applications’', $result);
	}

	public function testMakePlural(){
		$result = CoreUtils::makePlural('apple',2);
		self::assertEquals('apples', $result);
		$result = CoreUtils::makePlural('apple',1);
		self::assertEquals('apple', $result);
		$result = CoreUtils::makePlural('apple',2,true);
		self::assertEquals('2 apples', $result);
		$result = CoreUtils::makePlural('apple',1,true);
		self::assertEquals('1 apple', $result);
		$result = CoreUtils::makePlural('staff member',2,true);
		self::assertEquals('2 staff members', $result);
		$result = CoreUtils::makePlural('entry',10,true);
		self::assertEquals('10 entries', $result);
	}

	public function testBrowserNameToClass(){
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

	public function testTrim(){
		$result = CoreUtils::trim('I    like    spaces');
		self::assertEquals('I like spaces', $result);
	}

	public function testAverage(){
		$result = CoreUtils::average(1);
		self::assertEquals(1, $result);
		$result = CoreUtils::average(1,2);
		self::assertEquals(1.5, $result);
		$result = CoreUtils::average(1,2,3);
		self::assertEquals(2, $result);
	}

	public function testCutoff(){
		$result = CoreUtils::cutoff('This is a long string', 10);
		self::assertEquals(10, CoreUtils::length($result));
		self::assertEquals('This is a…', $result);
	}

	public function testYiq(){
		$result = CoreUtils::yiq('#ffffff');
		self::assertEquals(255, $result);
		$result = CoreUtils::yiq('#808080');
		self::assertEquals(128, $result);
		$result = CoreUtils::yiq('#000000');
		self::assertEquals(0, $result);
	}

	public function testSet(){
		$array = [];
		CoreUtils::set($array, 'key', 'value');
		self::assertArrayHasKey('key', $array);
		self::assertEquals('value', $array['key']);

		$object = new stdClass();
		CoreUtils::set($object, 'key', 'value');
		self::assertObjectHasAttribute('key', $object);
		self::assertEquals('value', $object->key);
	}

	public function testSha256(){
		$data = 'a3d5f3e5a67f38cd6e7ad8cfe41245acf';
		$hash = CoreUtils::sha256($data);
		self::assertEquals('fcb0c71edf2df18c7d39accbbb46083d511ea091d7ec56727a6a9931d40f46d8',$hash);
	}

	// If httpstat.us ever goes down these tests may fail
	public function testIsURLAvailable(){
		$url = 'http://httpstat.us/200';
		$resp = CoreUtils::isURLAvailable($url);
		self::assertEquals(true,$resp);
		$resp = CoreUtils::isURLAvailable($url, [200]);
		self::assertEquals(true,$resp);

		$url = 'http://httpstat.us/503';
		$resp = CoreUtils::isURLAvailable($url);
		self::assertEquals(false,$resp);
		$resp = CoreUtils::isURLAvailable($url, [404]);
		self::assertEquals(true,$resp);
	}
}
