<?php

namespace App\Exceptions;

class JSONParseException extends \Exception {
	public function __construct($message, $code){
		parent::__construct($message, $code);
	}
}
