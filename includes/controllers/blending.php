<?php

use App\CoreUtils;
use App\RegExp;

$HexPattern = preg_replace(new RegExp('^/(.*)/.*$'),'$1',$HEX_COLOR_REGEX->jsExport());
CoreUtils::loadPage(array(
	'title' => "$Color Blending Calculator",
	'do-css', 'do-js',
));
