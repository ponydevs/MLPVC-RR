<?php

namespace App\Controllers;
use App\CoreUtils;
use App\CSRFProtection;
use App\Events;
use App\Input;
use App\Models\Event;
use App\Permission;
use App\Response;
use App\Time;

class EventController extends Controller {
	public $do = 'event';

	private $_event;
	private function _getEvent($params){
		$Event = Event::get($params['id']);
		if (empty($Event)){
			if (POST_REQUEST)
				Response::fail("Event not found");
			CoreUtils::notFound();
		}

		$this->_event = $Event;
	}

	function index($params){
		$this->_getEvent($params);

		$heading = $this->_event->name;

		CoreUtils::loadPage([
			'heading' => $heading,
			'title' => "$heading - Event",
			'do-css',
			'import' => [
				'Event' => $this->_event,
			],
		], $this);
	}

	function _addEdit($params, $action){
		// TODO Remove this restriction
		if (Permission::insufficient('staff'))
			CoreUtils::notFound();

		CSRFProtection::protect();

		if (!Permission::sufficient('staff'))
			Response::fail();

		global $currentUser, $Database, $Database;

		$editing = $action === 'set';
		if ($editing)
			$this->_getEvent($params);

		$update = [];

		$name = (new Input('name','string',[
			Input::IN_RANGE => [2,64],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Event name is missing',
				Input::ERROR_INVALID => 'Event name (@value) is invalid',
				Input::ERROR_RANGE => 'Event name must be between @min and @max characters long',
			]
		]))->out();
		CoreUtils::checkStringValidity($name, 'Event name', INVERSE_PRINTABLE_ASCII_PATTERN);
		$update['name'] = $name;

		$description = (new Input('description','text',[
			Input::IN_RANGE => [null, 3000],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Event description is missing',
				Input::ERROR_INVALID => 'Event description (@value) is invalid',
				Input::ERROR_RANGE => 'Event description cannot be longer than @max characters',
			]
		]))->out();
		CoreUtils::checkStringValidity($description, 'Event description', INVERSE_PRINTABLE_ASCII_PATTERN);
		$update['description'] = CoreUtils::sanitizeHtml($description,['h3','h4','h5','h6','p','a'],['a.href']);

		$type = (new Input('type',function($value){
			if (empty(Event::EVENT_TYPES[$value]))
				return Input::ERROR_INVALID;
		},[
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Event type is missing',
				Input::ERROR_INVALID => 'Event type (@value) is invalid',
			],
		]))->out();
		if ($type === 'contest')
			Response::fail('The selected event type is not supported yet.');
		$update['type'] = $type;

		$entry_role = (new Input('entry_role',function($value){
			if (!in_array($value, Event::REGULAR_ENTRY_ROLES) && empty(Event::SPECIAL_ENTRY_ROLES[$value]))
				return Input::ERROR_INVALID;
		},[
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Event entry role is missing',
				Input::ERROR_INVALID => 'Event entry role (@value) is invalid',
			],
		]))->out();
		$update['entry_role'] = $entry_role;


		if (!$editing){
			$update['added_by'] = $currentUser->id;
			$update['added_at'] = date('c');
		}

		$starts_at = (new Input('starts_at','timestamp',[
			Input::IS_OPTIONAL => true,
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Event start time is missng',
				Input::ERROR_INVALID => 'Event start time (@value) is invalid',
			],
		]))->out();
		$update['starts_at'] = isset($starts_at) ? date('c', $starts_at) : $update['added_at'];

		$ends_at = (new Input('ends_at','timestamp',[
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Event end time is missng',
				Input::ERROR_INVALID => 'Event end time (@value) is invalid',
			],
		]))->out();
		$update['ends_at'] = date('c', $ends_at);

		if ($editing){
			if (!$Database->where('id', $this->_event->id)->update('events', $update))
				Response::dbError('Updating event failed');
		}
		else $insertid = $Database->insert('events', $update, 'id');

		$NewEvent = Event::get($editing ? $this->_event->id : $insertid);
		Response::done(['url' => $NewEvent->toURL()]);
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
	function end($params){ }
	*/
}
