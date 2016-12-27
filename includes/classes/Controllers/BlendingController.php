<?php

namespace App\Controllers;
use App\CoreUtils;
use App\RegExp;

class BlendingController extends Controller {
	public $do = 'blending';

	function index(){
		global $HEX_COLOR_REGEX, $Color;

		$HexPattern = preg_replace(new RegExp('^/(.*)/.*$'),'$1',$HEX_COLOR_REGEX->jsExport());
		CoreUtils::loadPage(array(
			'title' => "$Color Blending Calculator",
			'do-css', 'do-js',
			'import' => [
				'HexPattern' => $HexPattern,
			]
		), $this);
	}
}
