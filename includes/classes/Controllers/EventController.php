<?php

namespace App\Controllers;
use App\CoreUtils;
use App\CSRFProtection;
use App\Events;
use App\Input;
use App\Models\Event;
use App\Permission;
use App\Response;

class EventController extends Controller {
	public $do = 'event';

	private $_event;
	private function _getEvent($params){
		$Event = Event::get($params['id']);
		if (empty($Event))
			Response::fail("Event not found");

		$this->_event = $Event;
	}

	function _addEdit($params, $action){
		// TODO Remove this restriction
		if (Permission::insufficient('developer'))
			CoreUtils::notFound();

		CSRFProtection::protect();

		if (!Permission::sufficient('staff'))
			Response::fail();

		global $currentUser, $Database, $Database;

		$editing = $action === 'set';
		if ($editing)
			$this->_getEvent($params);

		$name = (new Input('name','string',[
			Input::IN_RANGE => [2,64],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Event name is missing',
				Input::ERROR_INVALID => 'Event name must be between @min and @max characters long',
			]
		]))->out();

		Response::fail('This feature is not fully implemented yet!');
	}

	function set($params){
		$this->_addEdit($params, 'set');
	}

	function add($params){
		$this->_addEdit($params, 'add');
	}

	// TODO Implement
	/*
	function get($params){ }
	function delete($params){ }
	*/
}
