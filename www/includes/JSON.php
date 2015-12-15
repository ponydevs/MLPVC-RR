<?php
	/**
	 * A custom JSON class wraper for native json_* functions
	 * with defaults that make sense
	 */
	class JSON {
		static $AsObject = false;
		public static function Decode($json, $assoc = true, $depth = 512, $options = JSON_BIGINT_AS_STRING){
			return json_decode($json, $assoc, $depth, $options);
		}
		public static function Encode($value, $options = JSON_UNESCAPED_SLASHES, $depth = 512){
			return json_encode($value, $options, $depth);
		}
	}
