<?php

namespace App;

/**
 * A custom JSON class wraper for native json_* functions
 * with defaults that make sense
 */
class JSON {
	const AS_OBJECT = false;
	public static function decode($json, $assoc = true, $depth = 512, $options = JSON_BIGINT_AS_STRING){
		return json_decode($json, $assoc, $depth, $options);
	}
	public static function encode($value, $options = JSON_UNESCAPED_SLASHES, $depth = 512){
		return json_encode($value, $options, $depth);
	}
}
