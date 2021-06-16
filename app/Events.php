<?php

namespace App;

use Exception;

class Events {
  public static function get($limit = null, string $columns = '*') {
    return DB::$instance->get('events', $limit, $columns);
  }

  /**
   * @param \App\Models\Event[] $Events
   * @param bool                $wrap
   *
   * @return string
   * @throws Exception
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
        $added_at = Time::tag($event->created_at);
        $added_by = $isStaff ? ' by '.$event->creator->toAnchor() : '';
        $name = CoreUtils::escapeHTML($event->name);
        $HTML .= <<<HTML
					<li id="event-{$event->id}">
						<strong class="title"><a href='{$event->toURL()}' class="event-name">$name</a></strong>
						<span class="added">Added $added_at$added_by</span>
						<ul>
							<li><strong>Collaboration</li>
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
