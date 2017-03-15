<?php

namespace App\Models;

class EventEntry extends AbstractFillable {
	/** @var int */
	public
		$entryid,
		$eventid;
	/** @var string */
	public
		$favme,
		$submitted_by,
		$submitted_at;
	/** @param array|object */
	public function __construct($iter = null){
		parent::__construct($this, $iter);

		sd('Class conversion works');
	}
}
