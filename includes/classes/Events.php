<?php

namespace App;

use App\Models\User;

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
			foreach ($Events as $event){
				$start = Time::tag(strtotime($event->starts_at), true, Time::TAG_STATIC_DYNTIME);
				$end = Time::tag(strtotime($event->ends_at), true, Time::TAG_STATIC_DYNTIME);
				$added_at = Time::tag(strtotime($event->added_at));
				$added_by = Permission::sufficient('staff') ? ' by '.Users::get($event->added_by)->getProfileLink(User::LINKFORMAT_TEXT) : '';
				$HTML .= <<<HTML
<li>
	<a href='{$event->toURL()}' class="event-name">{$event->name}</a>
	<ul>
		<li>Start: $start</li>
		<li>End: $end</li>
		<li>Added $added_at$added_by</li>
	</ul>
</li>
HTML;
			}
		}

		return $wrap ? "<ul id='event-list'>$HTML</ul>" : $HTML;
	}
}
