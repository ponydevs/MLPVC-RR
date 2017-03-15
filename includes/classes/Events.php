<?php

namespace App;

use App\Models\User;
use App\Models\Event;

class Events {
	static function get($limit = null, string $columns = '*'){
		global $Database;

		return $Database->get('events',$limit,$columns);
	}

	/**
	 * @param \App\Models\Event[] $Events
	 * @param bool $wrap
	 *
	 * @return string
	 */
	static function getListHTML(array $Events, bool $wrap = true):string {
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
				$added_by = $isStaff ? ' by '.Users::get($event->added_by)->getProfileLink(User::LINKFORMAT_TEXT) : '';
				$admin = $isStaff ? '<button class="blue typcn typcn-pencil" disabled title="Edit"></button><button class="orange typcn typcn-media-stop" disabled title="End"></button><button class="red typcn typcn-trash delete-event" title="Delete"></button>' : '';
				$type = Event::EVENT_TYPES[$event->type];
				$HTML .= <<<HTML
<li id="event-{$event->id}">
	<strong class="title"><a href='{$event->toURL()}' class="event-name">{$event->name}</a>$admin</strong>
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
