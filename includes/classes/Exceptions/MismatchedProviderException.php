<?php

namespace App\Exceptions;

class MismatchedProviderException extends \Error {
	private $actualProvider;
	public function __construct($actualProvider){
		parent::__construct();
		$this->actualProvider = $actualProvider;
	}
	public function getActualProvider(){ return $this->actualProvider; }
}
