<?php

namespace App\Models;

class EpisodeVote extends AbstractFillable {
	/** @var int */
	public
		$season,
		$episode,
		$vote;
	/** @var string */
	public $user;

	/** @param array|object */
	public function __construct($iter = null){
		parent::__construct($this, $iter);
	}
}
