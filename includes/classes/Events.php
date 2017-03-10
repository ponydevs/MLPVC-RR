<?php

namespace App;

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
			foreach ($Events as $event)
				$HTML .= "<li><a href='{$event->toLink()}'>{$event->name}</a></li>";
		}

		return $wrap ? "<ul id='event-list'>$HTML</ul>" : $HTML;
	}
}
