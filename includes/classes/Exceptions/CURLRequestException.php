<?php

namespace App\Exceptions;

class CURLRequestException extends \Exception {
	public function __construct($errMsg, $errCode){
		$this->message = $errMsg;
		$this->code = $errCode;
	}
}
