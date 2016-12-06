<?php

namespace App;

class Request extends Post {
	/** @param array|object */
	public function __construct($iter = null){
		parent::__construct($this, $iter);

		$this->isRequest = true;
		$this->isReservation = !$this->isRequest;
	}
}
