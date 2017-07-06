<?php

namespace App\Controllers;
use App\CoreUtils;
use App\Events;
use App\Pagination;
use App\Permission;

class EventsController extends Controller {
	public $do = 'events';

	function list(){
		global $Database;

		$Pagination = new Pagination("events", 20, $Database->count('events'));

		CoreUtils::fixPath("/events/{$Pagination->page}");
		$heading = "Events";
		$title = "Page $Pagination->page - $heading";

		$Events = Events::get($Pagination->getLimit());

		if (isset($_GET['js']))
			$Pagination->respond(Events::getListHTML($Events, NOWRAP), '#event-list');

		$js = ['paginate'/*, $this->do*/];
		if (Permission::sufficient('staff'))
			$js[] = "{$this->do}-manage";

		CoreUtils::loadPage([
			'title' => $title,
			'heading' => $heading,
			'js' => $js,
			'css' => ['events'],
			'import' => [
				'Events' => $Events,
				'Pagination' => $Pagination,
				'PRINTABLE_ASCII_PATTERN' => PRINTABLE_ASCII_PATTERN,
			],
		], $this);
	}
}
