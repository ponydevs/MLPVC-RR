<?php

namespace App;

/**
 * A flexible class for parsing/storing color values
 */
class RGBAColor {
	/** @var int */
	public $red, $green, $blue;
	/** @var float */
	public $alpha;

	const COMPONENTS = ['red', 'green', 'blue'];

	/**
	 * Maps patterns to a boolean indicating whether the results can be used directly (without hex->dec conversion)
	 */
	const PATTERNS = [
		'/#([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})?/i' => false,
		'/#([a-f\d])([a-f\d])([a-f\d])/i' => false,
		'/rgba?\(\s*(\d+),\s*(\d+),\s*(\d+)(?:,\s*([10]|0?\.\d+))?\s*\)/i' => true,
	];

	public function __construct(int $r, int $g, int $b, float $a = 1){
		$this->red = $r;
		$this->green = $g;
		$this->blue = $b;
		$this->alpha = $a;
	}

	public function setRed(int $r){
		$this->red = $r;

		return $this;
	}

	public function setGreen(int $g){
		$this->green = $g;

		return $this;
	}

	public function setBlue(int $b){
		$this->blue = $b;

		return $this;
	}

	public function setAlpha(float $a){
		$this->alpha = $a;

		return $this;
	}

	public function isTransparent(){
		return $this->alpha !== 1.0;
	}

	/**
	 * Returns the brightness of the color using the YIQ weighing
	 *
	 * @return int Brightness ranging from 0 to 255
	 */
	public function yiq():int {
	    return (($this->red*299)+($this->green*587)+($this->blue*114))/1000;
	}

	public function toHex():string {
		return '#'.strtoupper(CoreUtils::pad(base_convert($this->red, 10, 16)).CoreUtils::pad(base_convert($this->green, 10, 16)).CoreUtils::pad(base_convert($this->blue, 10, 16)));
	}

	public function toHexa():string {
		return $this->toHex().strtoupper(CoreUtils::pad(base_convert(round($this->alpha*255),10,16)));
	}

	public function toRGB():string {
		return "rgb({$this->red},{$this->green},{$this->blue})";
	}

	public function toRGBA():string {
		return "rgba({$this->red},{$this->green},{$this->blue},{$this->alpha})";
	}

	public function __toString():string {
		return $this->isTransparent() ? $this->toRGBA() : $this->toHex();
	}

	/**
	 * @param bool $alpha
	 *
	 * @return self
	 */
	public function invert($alpha = false):self {
		$this->red = 255 - $this->red;
		$this->green = 255 - $this->green;
		$this->blue = 255 - $this->blue;
		if ($alpha)
			$this->alpha = 1 - $this->alpha;

		return $this;
	}

	public static function forEachColorIn(string &$input, callable $callback){
		foreach (self::PATTERNS as $pattern => $_)
			$input = preg_replace_callback($pattern, function($match) use ($callback, $pattern){
				return $callback(self::_parseWith($match[0], $pattern));
			}, $input);
	}

	/**
	 * @param string $color
	 * @param string $pattern
	 *
	 * @return self|null
	 */
	private static function _parseWith(string $color, string $pattern):?self {
		if (!preg_match($pattern, $color, $matches))
			return null;

		$values = array_slice($matches, 1, 4);

		if (!self::PATTERNS[$pattern]){
			if (strlen($values[0]) === 1)
				$values = array_map(function($el){ return $el.$el; }, $values);
			$values[0] = intval($values[0], 16);
			$values[1] = intval($values[1], 16);
			$values[2] = intval($values[2], 16);
			if (!empty($values[3]))
				$values[3] = intval($values[3], 16)/255;
		}

		return new self(...$values);
	}

	/**
	 * @param string $color
	 *
	 * @return self|null
	 */
	public static function parse(string $color):?self {
		foreach (self::PATTERNS as $pattern => $_){
			$result = self::_parseWith($color, $pattern);
			if ($result === null)
				continue;

			return $result;
		}

		return null;
	}
}
