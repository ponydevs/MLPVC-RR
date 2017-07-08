<?php

namespace App;

use App\Models\Event;
use App\Models\User;

class Events {
	public static function get($limit = null, string $columns = '*'){


		return \App\DB::get('events',$limit,$columns);
	}

	/**
	 * @param \App\Models\Event[] $Events
	 * @param bool $wrap
	 *
	 * @return string
	 */
	public static function getListHTML(array $Events, bool $wrap = true):string {
		$HTML = '';
		if (!empty($Events)){
			$isStaff = Permission::sufficient('staff');
			foreach ($Events as $event){
				$start_ts = strtotime($event->starts_at);
				$end_ts = strtotime($event->ends_at);
				$start = Time::tag($start_ts, Time::TAG_EXTENDED);
				$end = Time::tag($end_ts, Time::TAG_EXTENDED);
				$diff = Time::difference($start_ts, $end_ts);
				$dur = Time::differenceToString($diff, true);
				$added_at = Time::tag(strtotime($event->added_at));
				$added_by = $isStaff ? ' by '.User::find($event->added_by)->getProfileLink() : '';
				$admin = $isStaff && !$event->isFinalized() ? '<button class="blue typcn typcn-pencil edit-event" title="Edit"></button><button class="darkblue typcn typcn-image finalize-event" title="Finalize"></button><button class="red typcn typcn-trash delete-event" title="Delete"></button>' : '';
				$type = Event::EVENT_TYPES[$event->type];
				$name = CoreUtils::escapeHTML($event->name);
				$HTML .= <<<HTML
<li id="event-{$event->id}">
	<strong class="title"><a href='{$event->toURL()}' class="event-name">$name</a>$admin</strong>
	<span class="added">Added $added_at$added_by</span>
	<ul>
		<li><strong>Type:</strong> {$type}</li>
		<li><strong>Start:</strong> $start (<span class="dynt-el"></Span>)</li>
		<li><strong>End:</strong> $end (<span class="dynt-el"></Span>)</li>
		<li><strong>Duration:</strong> $dur</li>
	</ul>
</li>
HTML;
			}
		}

		return $wrap ? "<ul id='event-list'>$HTML</ul>" : $HTML;
	}
}
