<?php

namespace App\Models;

use App\Models\Post;

class Request extends Post {
	/** @param string */
	public
		$type,
		$requested_by;

	/** @param array|object */
	public function __construct($iter = null){
		parent::__construct($this, $iter);

		$this->isRequest = true;
		$this->isReservation = !$this->isRequest;
	}
}
