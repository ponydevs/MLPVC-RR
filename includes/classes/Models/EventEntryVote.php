<?php

namespace App\Models;

use ActiveRecord\Model;
use App\Time;

/**
 * @property int $entryid
 * @property int $value
 * @property string $userid
 * @property string $cast_at
 * @method static EventEntryVote find_by_entry_id_and_user_id(int $entr_yid, string $user_id)
 */
class EventEntryVote extends Model {
	static $table_name = 'events__entries__votes';

	static $primary_key = ['entry_id','user_id'];

	static $belongs_to = [
		['user'],
		['entry', 'class' => 'EventEntry'],
	];

	/**
	 * Checks if the vote is locked in, requires the event's last edit timestamp
	 *
	 * @param EventEntry $entry
	 * @param int|null   $now
	 *
	 * @return bool
	 */
	public function isLockedIn(EventEntry $entry, ?int $now = null):bool {
		$entryEditTS = strtotime($entry->last_edited);
		$voteCastTS = strtotime($this->cast_at);
		return ($now??time()) - $voteCastTS >= Time::IN_SECONDS['hour'] && $entryEditTS < $voteCastTS;
	}
}
