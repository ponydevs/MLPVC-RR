<?php

namespace App;

class URL {
	/**
	 * Makes an absolute URL HTTPS
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	static function MakeHttps($url){
		return regex_replace(new RegExp('^(https?:)?//'),'https://',$url);
	}
}
