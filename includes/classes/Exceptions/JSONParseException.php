<?php

namespace App\Exceptions;

use Exception;

class JSONParseException extends \Exception {
	function __construct($message, $code){
		parent::__construct($message, $code);
	}
}
