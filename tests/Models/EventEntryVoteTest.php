<?php

namespace Models;

use App\Models\EventEntry;
use App\Models\EventEntryVote;
use PHPUnit\Framework\TestCase;

class EventEntryVoteTest extends TestCase {
	function testIsLockedIn(){
		$entry = new EventEntry([ 'last_edited' => '2017-01-20T10:00:00Z' ]);
		$entryVote = new EventEntryVote([ 'cast_at' => '2017-01-20T12:00:00Z' ]);
		$lockedIn = $entryVote->isLockedIn($entry, strtotime('2017-01-20T13:00:00Z'));
		self::assertEquals(true, $lockedIn, 'Votes should be locked in after an hour if the entry isn\'t edited');

		$entry = new EventEntry([ 'last_edited' => '2017-01-20T15:40:00Z' ]);
		$entryVote = new EventEntryVote([ 'cast_at' => '2017-01-20T10:30:00Z' ]);
		$lockedIn = $entryVote->isLockedIn($entry, strtotime('2017-01-20T15:10:00Z'));
		self::assertEquals(false, $lockedIn, 'Votes should be changable if the entry is edited after the vote is cast');
	}
}
