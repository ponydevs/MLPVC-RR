<?php

namespace App\Exceptions;

class UnsupportedProviderException extends \Exception {
	function __construct(){
		$this->message = "Unsupported provider. Try uploading your image to <a href='http://sta.sh' target='_blank' rel='noopener'>Sta.sh</a>";
	}
}
