<?php

namespace App\Controllers;
use App\CoreUtils;
use App\CSRFProtection;
use App\Events;
use App\Exceptions\MismatchedProviderException;
use App\ImageProvider;
use App\Input;
use App\Models\Event;
use App\Models\EventEntry;
use App\Models\EventEntryVote;
use App\Models\User;
use App\Permission;
use App\Response;
use App\RegExp;
use App\Time;

class EventController extends Controller {
	public $do = 'event';

	/** @var bool */
	private $_eventPage;
	function __construct(){
		parent::__construct();

		$this->_eventPage = isset($_POST['EVENT_PAGE']);
	}

	/** @var Event */
	private $_event;
	private function _getEvent($id){
		$Event = Event::get($id);
		if (empty($Event)){
			if (POST_REQUEST)
				Response::fail("Event not found");
			CoreUtils::notFound();
		}

		$this->_event = $Event;
	}

	function index($params){
		$this->_getEvent($params['id']);

		$heading = $this->_event->name;
		$EventType = Event::EVENT_TYPES[$this->_event->type];

		CoreUtils::fixPath($this->_event->toURL());
		$js = ['jquery.fluidbox',$this->do];
		if (Permission::sufficient('staff'))
			$js[] = 'events-manage';

		CoreUtils::loadPage([
			'heading' => $heading,
			'title' => "$heading - $EventType Event",
			'do-css',
			'js' => $js,
			'import' => [
				'Event' => $this->_event,
				'EventType' => $EventType,
			],
		], $this);
	}

	function _addEdit($params, $action){
		if (!Permission::sufficient('staff'))
			Response::fail();
		CSRFProtection::protect();

		global $currentUser, $Database, $Database;

		$editing = $action === 'set';
		if ($editing)
			$this->_getEvent($params['id']);

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
		$update['desc_src'] = $description;
		/** @noinspection PhpUndefinedMethodInspection */
		$update['desc_rend'] = \Parsedown::instance()->setUrlsLinked(false)->setBreaksEnabled(true)->setMarkupEscaped(true)->text($description);

		if (!$editing){
			$type = (new Input('type',function($value){
				if (empty(Event::EVENT_TYPES[$value]))
					return Input::ERROR_INVALID;
			},[
				Input::IS_OPTIONAL => true,
				Input::CUSTOM_ERROR_MESSAGES => [
					Input::ERROR_INVALID => 'Event type (@value) is invalid',
				],
			]))->out();
			$update['type'] = $type;
		}

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

		$vote_role = (new Input('vote_role',function($value){
			if (!in_array($value, Event::REGULAR_ENTRY_ROLES))
				return Input::ERROR_INVALID;
		},[
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Event vote role is missing',
				Input::ERROR_INVALID => 'Event vote role (@value) is invalid',
			],
		]))->out();
		$update['vote_role'] = $vote_role;

		$max_entries = (new Input('max_entries',function(&$value, $range){
			if (preg_match(new RegExp('^unlimited$','i'),$value) || $value == 0){
				$value = null;
				return Input::ERROR_NONE;
			}
			if (!is_numeric($value))
				return Input::ERROR_INVALID;
			if (Input::checkNumberRange($value, $range, $code))
				return $code;
		},[
			Input::IN_RANGE => [1,null],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Event maximum entry count is missing',
				Input::ERROR_INVALID => 'Event maximum entry count (@value) is invalid',
				Input::ERROR_RANGE => 'Event maximum entry count must be greater than or equal to @min',
			],
		]))->out();
		$update['max_entries'] = $max_entries;

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
		else $update['id'] = $Database->insert('events', $update, 'id');

		$NewEvent = new Event($update);
		if ($editing){
			if ($this->_eventPage)
				Response::done([
					'name' => $NewEvent->name,
					'newurl' => $NewEvent->toURL(),
				]);
			Response::done();
		}
		else {
			Response::done(['goto' => $NewEvent->toURL()]);
		}
	}

	function get($params){
		if (!Permission::sufficient('staff'))
			Response::fail();
		CSRFProtection::protect();

		$this->_getEvent($params['id']);

		$resp = $this->_event->toArray();
		unset($resp['id']);
		unset($resp['desc_rend']);
		Response::done($resp);
	}

	function set($params){
		$this->_addEdit($params, 'set');
	}

	function add($params){
		$this->_addEdit($params, 'add');
	}

	function delete($params){
		if (!Permission::sufficient('staff'))
			Response::fail();
		CSRFProtection::protect();

		$this->_getEvent($params['id']);

		global $Database;

		if (!$Database->where('id',$this->_event->id)->delete('events'))
			Response::dbError('Deleting event failed');

		Response::done();
	}

	function checkEntries($params){
		global $signedIn, $currentUser, $Database;

		if (!$signedIn)
			Response::fail();
		CSRFProtection::protect();

		$this->_getEvent($params['id']);

		if (!$this->_event->checkCanEnter($currentUser))
			Response::fail('You cannot participate in this event.');
		if (!$this->_event->hasStarted())
			Response::fail('This event hasn\'t started yet, so entries cannot be submitted.');

		if (!empty($this->_event->max_entries)){
			$entrycnt = count($currentUser->getEntriesFor($this->_event, 'entryid'));
			if ($entrycnt >= $this->_event->max_entries)
				Response::fail("You've used all of your entries for this event. If you want to change your entry, edit it instead.");
			$remain = $this->_event->max_entries - $entrycnt;
			Response::success("You can submit $remain more ".CoreUtils::makePlural('entry', $remain));
		}
		Response::done();
	}

	private function _addSetEntry(){
		global $currentUser;

		$update = [];

		$link = (new Input('link','url',[
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Entry link is missing',
				Input::ERROR_INVALID => 'Entry link (@value) is invalid',
			]
		]))->out();
		try {
			$submission = new ImageProvider($link, ['sta.sh','fav.me','dA']);
		}
		catch (MismatchedProviderException $e){
			Response::fail('Entry link must point to a deviation or Sta.sh submission');
		}
		$update['sub_id'] = $submission->id;
		$update['sub_prov'] = $submission->provider;

		$title = (new Input('title','string',[
			Input::IN_RANGE => [2,64],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Entry title is missing',
				Input::ERROR_INVALID => 'Entry title (@valie) is invalid',
			]
		]))->out();
		CoreUtils::checkStringValidity($title, 'Entry title', INVERSE_PRINTABLE_ASCII_PATTERN);
		$update['title'] = $title;

		$prev_src = (new Input('prev_src','url',[
			Input::IS_OPTIONAL => true,
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_INVALID => 'Preview (@value) is invalid',
			]
		]))->out();
		if (isset($prev_src)){
			try {
				$prov = new ImageProvider($prev_src);
			}
			catch (\Exception $e){
				Response::fail($e->getMessage());
			}
			$update['prev_src'] = $prev_src;
			$update['prev_full'] = $prov->fullsize;
			$update['prev_thumb'] = $prov->preview;
		}
		else {
			$update['prev_src'] = null;
			$update['prev_full'] = null;
			$update['prev_thumb'] = null;
		}

		return $update;
	}

	public function addEntry($params){
		global $signedIn, $currentUser, $Database;

		if (!$signedIn)
			Response::fail();
		CSRFProtection::protect();

		$this->_getEvent($params['id']);

		if (!$this->_event->checkCanEnter($currentUser))
			Response::fail('You cannot participate in this event.');
		if (!$this->_event->hasStarted())
			Response::fail('This event hasn\'t started yet, so entries cannot be submitted.');
		if ($this->_event->hasEnded())
			Response::fail('This event has concluded, so no new entries can be submitted.');

		$insert = $this->_addSetEntry();
		$insert['submitted_by'] = $currentUser->id;
		$insert['eventid'] = $this->_event->id;
		$insert['score'] = $this->_event->type === 'contest' ? 0 : null;
		if (!$Database->insert('events__entries', $insert))
			Response::dbError('Saving entry failed');

		Response::done(['entrylist' => $this->_event->getEntriesHTML(NOWRAP)]);
	}

	/** @var EventEntry */
	private $_entry;
	private function _entryPermCheck($params, string $action = 'manage'){
		global $currentUser, $signedIn, $Database;

		if (!$signedIn)
			Response::fail();
		CSRFProtection::protect();

		if (!isset($params['entryid']))
			Response::fail('Entry ID is missing or invalid');

		$this->_entry = $Database->where('entryid', intval($params['entryid'], 10))->getOne('events__entries');
		if (empty($this->_entry) || ($action === 'manage' && !Permission::sufficient('staff') && $this->_entry->submitted_by !== $currentUser->id))
			Response::fail('The requested entry could not be found or you are not allowed to edit it');

		$this->_getEvent($this->_entry->eventid);

		if ($action === 'vote'){
			if (!$this->_event->checkCanVote($currentUser))
				Response::fail('You are not allowed to vote on entries to this event');
			if ($this->_event->type !== 'contest')
				Response::fail('You can only vote on entries to contest events');
			if ($this->_entry->submitted_by === $currentUser->id)
				Response::fail('You cannot vote on your own entries', ['disable' => true]);
		}

		if ($action !== 'view'){
			$endts = strtotime($this->_event->ends_at);
			if ($endts < time())
				Response::fail('This event has ended, entries can no longer be submitted or modified.');
		}
	}

	public function getEntry($params){
		$this->_entryPermCheck($params);

		Response::done([
			'link' => "http://{$this->_entry->sub_prov}/{$this->_entry->sub_id}",
			'title' => $this->_entry->title,
			'prev_src' => $this->_entry->prev_src,
		]);
	}

	public function setEntry($params){
		global $Database;

		$this->_entryPermCheck($params);

		$update = $this->_addSetEntry();

		$changes = [];
		foreach ($update as $k => $v){
			if ($update[$k] !== $this->_entry->{$k})
				$changes[$k] = $v;
		}

		if (!empty($changes)){
			// Do not change edit time if only entry title is changed
			if (!(count($changes) === 1 && array_key_exists('title', $changes)))
				$changes['last_edited'] = date('c');
			if (!$Database->where('entryid', $this->_entry->entryid)->update('events__entries', $changes))
				Response::fail('Nothing has been changed');
		}

		/** @var $entry EventEntry */
		$entry = $Database->where('entryid', $this->_entry->entryid)->getOne('events__entries');
		Response::done(['entryhtml' => $entry->toListItemHTML($this->_event, NOWRAP)]);
	}

	public function delEntry($params){
		global $Database;

		$this->_entryPermCheck($params);

		if (!$Database->where('entryid', $this->_entry->entryid)->delete('events__entries'))
			Response::dbError('Failed to delete entry');

		Response::done();
	}

	public function voteEntry($params){
		global $currentUser, $Database;

		$this->_entryPermCheck($params, 'vote');

		$userVote = $this->_entry->getUserVote($currentUser);

		$value = (new Input('value','vote',[
			Input::IN_RANGE => [-1, 1],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Vote value is missing',
				Input::ERROR_INVALID => 'Vote value (@value) is invalid',
				Input::ERROR_RANGE => 'Vote value must be @min or @max',
			]
		]))->out();
		if (!empty($userVote)){
			if ($userVote->value === $value)
				Response::fail('You already voted for this entry', ['disable' => true]);

			$this->_checkWipeLockedInVote($userVote);
		}

		if (!$Database->insert('events__entries__votes',[
			'entryid' => $this->_entry->entryid,
			'userid' => $currentUser->id,
			'value' => $value,
		]))
			Response::dbError('Vote could not be recorded');

		$this->_entry->updateScore();
		Response::done([ 'score' => $this->_entry->getFormattedScore() ]);
	}

	public function getvoteEntry($params){
		global $currentUser, $Database;

		$this->_entryPermCheck($params, 'view');

		Response::done([ 'voting' => $this->_entry->getListItemVoting($this->_event) ]);
	}

	private function _checkWipeLockedInVote(EventEntryVote $userVote){
		global $Database, $currentUser;

		if ($userVote->isLockedIn($this->_entry))
			Response::fail('You already voted on this post '.Time::tag($userVote->cast_at).'. Your vote is now locked in until the post is edited.');

		if (!$Database->where('userid', $currentUser->id)->where('entryid', $this->_entry->entryid)->delete('events__entries__votes'))
			Response::dbError('Vote could not be removed');
	}

	public function unvoteEntry($params){
		global $currentUser, $Database;

		$this->_entryPermCheck($params, 'vote');

		$userVote = $this->_entry->getUserVote($currentUser);
		if (empty($userVote))
			Response::fail('You haven\'t voted for this entry yet');
		$this->_checkWipeLockedInVote($userVote);

		$this->_entry->updateScore();
		Response::done([ 'score' => $this->_entry->getFormattedScore() ]);
	}
}
