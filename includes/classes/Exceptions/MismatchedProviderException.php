<?php

namespace App\Exceptions;

class MismatchedProviderException extends \Exception {
	private $actualProvider;
	function __construct($actualProvider){
		$this->actualProvider = $actualProvider;
	}
	function getActualProvider(){ return $this->actualProvider; }
}
