<?php

namespace App\Models;

use App\Models\Post;

class Reservation extends Post {
	/** @param array|object */
	public function __construct($iter = null){
		parent::__construct($this, $iter);

		$this->isRequest = false;
		$this->isReservation = !$this->isRequest;
	}
}
