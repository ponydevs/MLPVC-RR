<?php

namespace App\Exceptions;

class JSONParseException extends \Exception {
	function __construct($message, $code){
		parent::__construct($message, $code);
	}
}
