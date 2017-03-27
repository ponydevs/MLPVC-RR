<?php

namespace App\Models;

use App\Time;

class EventEntryVote extends AbstractFillable {
	/** @var int */
	public
		$entryid,
		$value;
	/** @var string */
	public
		$userid,
		$cast_at;
	/** @param array|object */
	public function __construct($iter = null){
		parent::__construct($this, $iter);
	}

	/**
	 * Checks if the vote is locked in, requires the event's last edit timestamp
	 *
	 * @param EventEntry $entry
	 * @param int|null   $now
	 *
	 * @return bool
	 */
	public function isLockedIn(EventEntry $entry, $now = null):bool {
		$entryEditTS = strtotime($entry->last_edited);
		$voteCastTS = strtotime($this->cast_at);
		return ($now??time()) - $voteCastTS > Time::IN_SECONDS['hour'] && $entryEditTS < $voteCastTS;
	}
}
