<?php

use App\RGBAColor;
use PHPUnit\Framework\TestCase;

class RGBAColorTest extends TestCase {
	public function testParse(){
		$result = RGBAColor::parse('#000000');
		self::assertEquals(0, $result->red);
		self::assertEquals(0, $result->green);
		self::assertEquals(0, $result->blue);
		self::assertEquals(1, $result->alpha);

		$result = RGBAColor::parse('#ffffff');
		self::assertEquals(255, $result->red);
		self::assertEquals(255, $result->green);
		self::assertEquals(255, $result->blue);
		self::assertEquals(1, $result->alpha);

		$result = RGBAColor::parse('#fff');
		self::assertEquals(255, $result->red);
		self::assertEquals(255, $result->green);
		self::assertEquals(255, $result->blue);
		self::assertEquals(1, $result->alpha);

		$result = RGBAColor::parse('#000');
		self::assertEquals(0, $result->red);
		self::assertEquals(0, $result->green);
		self::assertEquals(0, $result->blue);
		self::assertEquals(1, $result->alpha);

		$result = RGBAColor::parse('#ffffff00');
		self::assertEquals(0, $result->alpha);

		$result = RGBAColor::parse('#ffffffff');
		self::assertEquals(1, $result->alpha);

		$result = RGBAColor::parse('#EA428B');
		self::assertEquals(234, $result->red);
		self::assertEquals(66, $result->green);
		self::assertEquals(139, $result->blue);
		self::assertEquals(1, $result->alpha);

		$result = RGBAColor::parse('rgb(10, 50, 77)');
		self::assertEquals(10, $result->red);
		self::assertEquals(50, $result->green);
		self::assertEquals(77, $result->blue);
		self::assertEquals(1, $result->alpha);

		$result = RGBAColor::parse('rgba(10, 50, 77, 0)');
		self::assertEquals(10, $result->red);
		self::assertEquals(50, $result->green);
		self::assertEquals(77, $result->blue);
		self::assertEquals(0, $result->alpha);

		$result = RGBAColor::parse('rgba(10, 50, 77, 0.5)');
		self::assertEquals(10, $result->red);
		self::assertEquals(50, $result->green);
		self::assertEquals(77, $result->blue);
		self::assertEquals(.5, $result->alpha);

		$result = RGBAColor::parse('rgba(10,50,77,1)');
		self::assertEquals(10, $result->red);
		self::assertEquals(50, $result->green);
		self::assertEquals(77, $result->blue);
		self::assertEquals(1, $result->alpha);
	}

	public function testYiq(){
		/** @noinspection NullPointerExceptionInspection */
		$result = RGBAColor::parse('#ffffff')->yiq();
		self::assertEquals(255, $result);
		/** @noinspection NullPointerExceptionInspection */
		$result = RGBAColor::parse('#808080')->yiq();
		self::assertEquals(128, $result);
		/** @noinspection NullPointerExceptionInspection */
		$result = RGBAColor::parse('#000000')->yiq();
		self::assertEquals(0, $result);
	}

	public function testToHex(){
		$result = new RGBAColor(234, 66, 139);
		self::assertEquals('#EA428B', $result->toHex());

		$result = new RGBAColor(234, 66, 139, .5);
		self::assertEquals('#EA428B', $result->toHex());
	}

	public function testToRGB(){
		$result = new RGBAColor(234, 66, 139);
		self::assertEquals('rgb(234,66,139)', $result->toRGB());

		$result = new RGBAColor(234, 66, 139, .5);
		self::assertEquals('rgb(234,66,139)', $result->toRGB());
	}

	public function testToRGBA(){
		$result = new RGBAColor(234, 66, 139, 1);
		self::assertEquals('rgba(234,66,139,1)', $result->toRGBA());

		$result = new RGBAColor(234, 66, 139, .5);
		self::assertEquals('rgba(234,66,139,0.5)', $result->toRGBA());
	}

	public function testToString(){
		$result = new RGBAColor(234, 66, 139, 1);
		self::assertEquals('#EA428B', (string)$result);

		$result = new RGBAColor(234, 66, 139, .5);
		self::assertEquals('rgba(234,66,139,0.5)', (string)$result);

		$result = RGBAColor::parse('#b8e1fd');
		self::assertEquals('#B8E1FD', (string)$result);
	}

	public function testInvert(){
		$result = new RGBAColor(0, 255, 0, 1);
		self::assertEquals('#FF00FF', $result->invert()->toHex());

		$result = new RGBAColor(255, 0, 255, .5);
		self::assertEquals('rgba(0,255,0,0.5)', (string)$result->invert());
	}
}
