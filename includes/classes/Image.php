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
	 */
	static function CheckType($tmp, $allowedMimeTypes){
		$imageSize = getimagesize($tmp);
		if (is_array($allowedMimeTypes) && !in_array($imageSize['mime'], $allowedMimeTypes))
			Response::Fail("This type of image is now allowed: ".$imageSize['mime']);
		list($width,$height) = $imageSize;

		if ($width + $height === 0) Response::Fail('The uploaded file is not an image');

		return array($width, $height);
	}

	/**
	 * Check image size with a preset minimum
	 *
	 * @param string $path
	 * @param int $width
	 * @param int $height
	 * @param int $minwidth
	 * @param int $minheight
	 */
	static function CheckSize($path, $width, $height, $minwidth, $minheight){
		if ($width < $minwidth || $height < $minheight){
			unlink($path);
			Response::Fail('The image is too small in '.(
				$width < $minwidth
				?(
					$height < $minheight
					?'width and height'
					:'width'
				)
				:(
					$height < $minheight
					?'height'
					:''
				)
			).", please uploadd a bigger image.<br>The minimum size is {$minwidth}px by {$minheight}px.</p>");
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
	static function PreserveAlpha($img, &$background = null) {
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
	static function CreateTransparent($width, $height = null) {
		if (!isset($height))
			$height = $width;

		$png = Image::PreserveAlpha(imagecreatetruecolor($width, $height), $transparency);
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
	static function CreateWhiteBG($width, $height = null) {
		if (!isset($height))
			$height = $width;
		$png = imagecreatetruecolor($width, $height);

		$white = imagecolorallocate($png, 255, 255, 255);
		imagefill($png, 0, 0, $white);
		return $png;
	}

	/**
	 * Draw a an (optionally filled) squre on an $image
	 *
	 * @param resource   $image
	 * @param int        $x
	 * @param int        $y
	 * @param int|array  $size
	 * @param string     $fill
	 * @param string|int $outline
	 */
	static function DrawSquare($image, $x, $y, $size, $fill, $outline){
		if (!empty($fill) && is_string($fill)){
			$fill = CoreUtils::hex2Rgb($fill);
			$fill = imagecolorallocate($image, $fill[0], $fill[1], $fill[2]);
		}
		if (is_string($outline)){
			$outline = CoreUtils::hex2Rgb($outline);
			$outline = imagecolorallocate($image, $outline[0], $outline[1], $outline[2]);
		}

		if (is_array($size)){
			$x2 = $x + $size[0];
			$y2 = $y + $size[1];
		}
		else {
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
	 * @param resource   $image
	 * @param int        $x
	 * @param int        $y
	 * @param int|array  $size
	 * @param string     $fill
	 * @param string|int $outline
	 */
	static function DrawCircle($image, $x, $y, $size, $fill, $outline){
		if (!empty($fill)){
			$fill = CoreUtils::hex2Rgb($fill);
			$fill = imagecolorallocate($image, $fill[0], $fill[1], $fill[2]);
		}
		if (is_string($outline)){
			$outline = CoreUtils::hex2Rgb($outline);
			$outline = imagecolorallocate($image, $outline[0], $outline[1], $outline[2]);
		}

		if (is_array($size)){
			$width = $size[0];
			$height = $size[1];
			$x2 = $x + $width;
			$y2 = $y + $height;
		}
		else {
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
	static function Write($image, $text, $x, $fontsize, $fontcolor, &$origin, $FontFile, $box = null, $yOffset = 0){
		if (is_string($fontcolor))
			$fontcolor = imagecolorallocate($image, 0, 0, 0);

		if (empty($box)){
			$box = self::SaneGetTTFBox($fontsize, $FontFile, $text);
			$origin['y'] += $box['height'];
			$y = $origin['y'] - $box['bottom right']['y'];
		}
		else $y = $origin['y'] + $box['height'] - $box['bottom right']['y'];

		imagettftext($image, $fontsize, 0, $x, $y + $yOffset, $fontcolor, $FontFile, $text);

		return array($x, $y);
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
	static function SaneGetTTFBox($fontsize, $fontfile, $text){
		/*
		    imagettfbbox returns (x,y):
		    6,7--4,5
		     |    |
		     |    |
		    0,1--2,3
		*/
		$box = imagettfbbox($fontsize, 0, $fontfile, $text);

		$return =  array(
			'bottom left' => array('x' => $box[0], 'y' => $box[1]),
			'bottom right' => array('x' => $box[2], 'y' => $box[3]),
			'top right' => array('x' => $box[4], 'y' => $box[5]),
			'top left' => array('x' => $box[6], 'y' => $box[7]),
		);
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
	static function CopyExact($dest, $source, $x, $y, $w, $h){
		imagecopyresampled($dest, $source, $x, $y, $x, $y, $w, $h, $w, $h);
	}


	/**
	 * Output png file to browser
	 *
	 * @param resource $resource
	 * @param string   $path
	 * @param string   $FileRelPath
	 */
	static function OutputPNG($resource, $path, $FileRelPath){
		self::_output($resource, $path, $FileRelPath, function($fp,$fd){ imagepng($fd, $fp, 9, PNG_NO_FILTER); }, 'png');
	}

	/**
	 * Output svg file to browser
	 *
	 * @param string $svgdata
	 * @param string $path
	 * @param string $FileRelPath
	 */
	static function OutputSVG($svgdata, $path, $FileRelPath){
		self::_output($svgdata, $path, $FileRelPath, function($fp,$fd){ file_put_contents($fp, $fd); }, 'svg+xml');
	}

	/**
	 * @param resource|string $data
	 * @param string $path
	 * @param string $relpath
	 * @param callable $write_callback
	 * @param string $content_type
	 */
	private static function _output($data, $path, $relpath, $write_callback, $content_type){
		if (isset($data)){
			CoreUtils::createUploadFolder($path);
			$write_callback($path, $data);
		}

		CoreUtils::fixPath("$relpath?t=".filemtime($path));
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
	static function CalcRedraw(&$OutWidth, &$OutHeight, $WidthIncrease, $HeightIncrease, &$BaseImage, $origin){
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
			$NewBaseImage = Image::CreateTransparent($NewWidth, $NewHeight);
			Image::CopyExact($NewBaseImage, $BaseImage, 0, 0, $OutWidth, $OutHeight);
			imagedestroy($BaseImage);
			$BaseImage = $NewBaseImage;
			$OutWidth = $NewWidth;
			$OutHeight = $NewHeight;
		}
	}
}
