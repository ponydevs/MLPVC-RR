<?php

	namespace Exceptions;

	class cURLRequestException extends \Exception {
		public function __construct($errMsg, $errCode){
			$this->message = $errMsg;
			$this->code = $errCode;
		}
	}
