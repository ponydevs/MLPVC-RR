<?php

namespace Models;

use ActiveRecord\DateTime;
use App\Models\EventEntry;
use App\Models\EventEntryVote;
use PHPUnit\Framework\TestCase;

class EventEntryVoteTest extends TestCase {
  public function testIsLockedIn() {
    $entry = new EventEntry(['updated_at' => new DateTime('2017-01-20T10:00:00Z')]);
    $entryVote = new EventEntryVote(['created_at' => new DateTime('2017-01-20T12:00:00Z')]);
    $lockedIn = $entryVote->isLockedIn($entry, strtotime('+59 minutes', $entryVote->created_at->getTimestamp()));
    self::assertEquals(false, $lockedIn, "Votes shouldn't be locked in after an hour if the entry isn't edited");
    $lockedIn = $entryVote->isLockedIn($entry, strtotime('+1 hour', $entryVote->created_at->getTimestamp()));
    self::assertEquals(true, $lockedIn, 'Votes should be locked in after an hour if the entry isn\'t edited');
    $lockedIn = $entryVote->isLockedIn($entry, strtotime('+1 day', $entryVote->created_at->getTimestamp()));
    self::assertEquals(true, $lockedIn, 'Votes should be locked in after an hour if the entry isn\'t edited');

    $entry = new EventEntry(['updated_at' => new DateTime('2017-01-20T15:40:00Z')]);
    // Simulates that vote was cast 20 minutes before the post was edited
    $entryVote = new EventEntryVote(['created_at' => new DateTime(date('c', strtotime('-20 minutes', strtotime($entry->updated_at))))]);
    // Forcibly check if the post is locked in 10 minutes after the edit
    $lockedIn = $entryVote->isLockedIn($entry, strtotime('+10 minutes', $entry->updated_at->getTimestamp()));
    self::assertEquals(false, $lockedIn, 'Votes should be changeable if the entry is edited after the vote is cast');
  }
}
