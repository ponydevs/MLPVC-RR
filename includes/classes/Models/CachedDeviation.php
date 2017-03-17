<?php

namespace App\Models;

class CachedDeviation extends AbstractFillable {
	/** @var string */
	public
		$provider,
		$id,
		$title,
		$author,
		$preview,
		$fullsize,
		$updated_on,
		$type;

	/** @param array|object */
	public function __construct($iter = null){
		parent::__construct($this, $iter);
	}
}
