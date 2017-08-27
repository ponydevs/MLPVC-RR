<?php

namespace App;

class Image {
	/**
	 * Checks image type and returns an array containing the image width and height
	 *
	 * @param string   $tmp
	 * @param string[] $allowedMimeTypes
	 *
	 * @return int[]
	 * @throws \RuntimeException
	 */
	public static function checkType($tmp, $allowedMimeTypes){
		$imageSize = getimagesize($tmp);
		if ($imageSize === false)
			throw new \RuntimeException("getimagesize could not read $tmp");
		/** @var $imageSize array */
		if (is_array($allowedMimeTypes) && !in_array($imageSize['mime'], $allowedMimeTypes, true))
			Response::fail('This type of image is now allowed: '.$imageSize['mime']);
		list($width,$height) = $imageSize;

		if ($width + $height === 0) Response::fail('The uploaded file is not an image');

		return [$width, $height];
	}

	/**
	 * Check image size with a preset minimum
	 *
	 * @param string $path
	 * @param int $width
	 * @param int $height
	 * @param array $min
	 * @param array $max
	 */
	public static function checkSize($path, $width, $height, $min, $max){
		$tooSmall = $width < $min[0] || $height < $min[1];
		$tooBig = $width > $max[0] || $height > $max[1];
		if ($tooSmall || $tooBig){
			CoreUtils::deleteFile($path);
			Response::fail('The image’s '.(
				($tooBig ? $width > $max[0] : $width < $min[0])
				?(
					($tooBig ? $height > $max[1] : $height < $min[1])
					?'width and height are'
					:'width is'
				)
				:(
					($tooBig ? $height > $max[1] : $height < $min[1])
					?'height is'
					:'dimensions are'
				)
			).' too '.($tooBig?'big':'small').', please upload a '.($tooBig?'smaller':'larger').' image.<br>The '.($tooBig?'maximum':'minimum').' size is '.($tooBig?$max[0]:$min[0]).'px wide by '.($tooBig?$max[1]:$min[1])."px tall, and you uploaded an image that’s {$width}px wide and {$height}px tall.</p>");
		}
	}

	/**
	 * Preseving alpha
	 *
	 * @param resource $img
	 * @param int      $background
	 *
	 * @return resource
	 */
	public static function preserveAlpha($img, &$background = null) {
		$background = imagecolorallocatealpha($img, 0, 0, 0, 127);
		imagecolortransparent($img, $background);
		imagealphablending($img, false);
		imagesavealpha($img, true);
		return $img;
	}

	/**
	 * Transparent Image creator
	 *
	 * @param int      $width
	 * @param int|null $height
	 *
	 * @return resource
	 */
	public static function createTransparent($width, $height = null){
		if ($height === null)
			$height = $width;

		$png = Image::preserveAlpha(imagecreatetruecolor($width, $height), $transparency);
		imagefill($png, 0, 0, $transparency);
		return $png;
	}

	/**
	 * White Image creator
	 *
	 * @param int      $width
	 * @param int|null $height
	 *
	 * @return resource
	 */
	public static function createWhiteBG($width, $height = null){
		if ($height === null)
			$height = $width;

		$png = imagecreatetruecolor($width, $height);
		$white = imagecolorallocate($png, 255, 255, 255);
		imagefill($png, 0, 0, $white);
		return $png;
	}

	/**
	 * Draw a an (optionally filled) squre on an $image
	 *
	 * @param resource    $image
	 * @param int         $x
	 * @param int         $y
	 * @param mixed       $size
	 * @param string|null $fill
	 * @param string|int  $outline
	 */
	public static function drawSquare($image, $x, $y, $size, $fill, $outline){
		if (!empty($fill) && is_string($fill)){
			$fill = RGBAColor::parse($fill);
			$fill = imagecolorallocate($image, $fill[0], $fill[1], $fill[2]);
		}
		if (is_string($outline)){
			$outline = RGBAColor::parse($outline);
			$outline = imagecolorallocate($image, $outline[0], $outline[1], $outline[2]);
		}

		if (is_array($size)){
			/** @var $size int[] */
			$x2 = $x + $size[0];
			$y2 = $y + $size[1];
		}
		else {
			/** @var $size int */
			$x2 = $x + $size;
			$y2 = $y + $size;
		}

		$x2--; $y2--;

		if (isset($fill))
			imagefilledrectangle($image, $x, $y, $x2, $y2, $fill);
		if (isset($outline))
			imagerectangle($image, $x, $y, $x2, $y2, $outline);
	}

	/**
	 * Draw a an (optionally filled) circle on an $image
	 *
	 * @param resource    $image
	 * @param int         $x
	 * @param int         $y
	 * @param mixed       $size
	 * @param string|null $fill
	 * @param string|int  $outline
	 */
	public static function drawCircle($image, $x, $y, $size, $fill, $outline){
		if (!empty($fill) && is_string($fill)){
			$fill = RGBAColor::parse($fill);
			$fill = imagecolorallocate($image, $fill[0], $fill[1], $fill[2]);
		}
		if (is_string($outline)){
			$outline = RGBAColor::parse($outline);
			$outline = imagecolorallocate($image, $outline[0], $outline[1], $outline[2]);
		}

		if (is_array($size)){
			/** @var $size int[] */
			[$width,$height] = $size;
			$x2 = $x + $width;
			$y2 = $y + $height;
		}
		else {
			/** @var $size int */
			$x2 = $x + $size;
			$y2 = $y + $size;
			$width = $height = $size;
		}
		$cx = CoreUtils::average($x,$x2);
		$cy = CoreUtils::average($y,$y2);

		if (isset($fill))
			imagefilledellipse($image, $cx, $cy, $width, $height, $fill);
		imageellipse($image, $cx, $cy, $width, $height, $outline);
	}

	/**
	 * Writes on an image
	 *
	 * @param resource   $image
	 * @param string     $text
	 * @param int        $x
	 * @param int        $fontsize
	 * @param int        $fontcolor
	 * @param array|null $origin
	 * @param string     $FontFile
	 * @param array      $box
	 * @param int        $yOffset
	 *
	 * @return array
	 */
	public static function writeOn($image, $text, $x, $fontsize, $fontcolor, &$origin, $FontFile, $box = null, $yOffset = 0){
		if (is_string($fontcolor))
			$fontcolor = imagecolorallocate($image, 0, 0, 0);

		if (empty($box)){
			$box = self::saneGetTTFBox($fontsize, $FontFile, $text);
			$origin['y'] += $box['height'];
			$y = $origin['y'] - $box['bottom right']['y'];
		}
		else $y = $origin['y'] + $box['height'] - $box['bottom right']['y'];

		imagettftext($image, $fontsize, 0, $x, $y + $yOffset, $fontcolor, $FontFile, $text);

		return [$x, $y];
	}


	/**
	 * imagettfbbox wrapper with sane output
	 *
	 * @param int    $fontsize
	 * @param string $fontfile
	 * @param string $text
	 *
	 * @return array
	 */
	public static function saneGetTTFBox($fontsize, $fontfile, $text){
		/*
		    imagettfbbox returns (x,y):
		    6,7--4,5
		     |    |
		     |    |
		    0,1--2,3
		*/
		$box = imagettfbbox($fontsize, 0, $fontfile, $text);

		$return =  [
			'bottom left' => ['x' => $box[0], 'y' => $box[1]],
			'bottom right' => ['x' => $box[2], 'y' => $box[3]],
			'top right' => ['x' => $box[4], 'y' => $box[5]],
			'top left' => ['x' => $box[6], 'y' => $box[7]],
		];
		$return['width'] = abs($return['bottom right']['x'] - $return['top left']['x']);
		$return['height'] = abs($return['bottom right']['y'] - $return['top left']['y']);

		return $return;
	}

	/**
	 * Copies the source image to the destination image at the exact same positions
	 *
	 * @param resource $dest
	 * @param resource $source
	 * @param int      $x
	 * @param int      $y
	 * @param int      $w
	 * @param int      $h
	 */
	public static function copyExact($dest, $source, $x, $y, $w, $h){
		imagecopyresampled($dest, $source, $x, $y, $x, $y, $w, $h, $w, $h);
	}


	/**
	 * Output png file to browser
	 *
	 * @param resource $resource
	 * @param string   $path
	 * @param string   $FileRelPath
	 */
	public static function outputPNG($resource, $path, $FileRelPath){
		self::_output($resource, $path, $FileRelPath, function($fp,$fd){ imagepng($fd, $fp, 9, PNG_NO_FILTER); }, 'png');
	}

	/**
	 * Output svg file to browser
	 *
	 * @param string|null $svgdata
	 * @param string      $path
	 * @param string      $FileRelPath
	 */
	public static function outputSVG($svgdata, $path, $FileRelPath){
		self::_output($svgdata, $path, $FileRelPath, function($fp,$fd){ File::put($fp, $fd); }, 'svg+xml');
	}

	/**
	 * @param resource|string $data
	 * @param string $path
	 * @param string $relpath
	 * @param callable $write_callback
	 * @param string $content_type
	 */
	private static function _output($data, $path, $relpath, $write_callback, $content_type){
		if ($data !== null){
			CoreUtils::createFoldersFor($path);
			$write_callback($path, $data);
			if (file_exists($path))
				File::chmod($path);
		}

		$filePortion = strtok($relpath,'?');
		$fpl = CoreUtils::length($filePortion);
		$params = CoreUtils::length($relpath) > $fpl ? '&'.CoreUtils::substring($relpath, $fpl+1) : '';
		CoreUtils::fixPath("$filePortion?t=".filemtime($path).$params);
		header("Content-Type: image/$content_type");
		readfile($path);
		exit;
	}

	/**
	 * Calculate and recreate the base image in case its size need to be increased
	 *
	 * @param int      $OutWidth
	 * @param int      $OutHeight
	 * @param int      $WidthIncrease
	 * @param int      $HeightIncrease
	 * @param resource $BaseImage
	 * @param array    $origin
	 */
	public static function calcRedraw(&$OutWidth, &$OutHeight, $WidthIncrease, $HeightIncrease, &$BaseImage, $origin){
		$Redraw = false;
		if ($origin['x']+$WidthIncrease > $OutWidth){
			$Redraw = true;
			$origin['x'] += $WidthIncrease;
		}
		if ($origin['y']+$HeightIncrease > $OutHeight){
			$Redraw = true;
			$origin['y'] += $HeightIncrease;
		}
		if ($Redraw){
			$NewWidth = max($origin['x'],$OutWidth);
			$NewHeight = max($origin['y'],$OutHeight);
			// Create new base image since height will increase, and copy contents of old one
			$NewBaseImage = Image::createTransparent($NewWidth, $NewHeight);
			Image::copyExact($NewBaseImage, $BaseImage, 0, 0, $OutWidth, $OutHeight);
			imagedestroy($BaseImage);
			$BaseImage = $NewBaseImage;
			$OutWidth = $NewWidth;
			$OutHeight = $NewHeight;
		}
	}
}
