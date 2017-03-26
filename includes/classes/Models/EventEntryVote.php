<?php

namespace App\Models;

class EventEntryVote extends AbstractFillable {
	/** @var int */
	public
		$entryid,
		$value;
	/** @var string */
	public
		$userid;
	/** @param array|object */
	public function __construct($iter = null){
		parent::__construct($this, $iter);
	}
}
