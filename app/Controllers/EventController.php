<?php

namespace App\Controllers;
use App\Auth;
use App\CoreUtils;
use App\CSRFProtection;
use App\DB;
use App\Events;
use App\Exceptions\MismatchedProviderException;
use App\Exceptions\UnsupportedProviderException;
use App\HTTP;
use App\ImageProvider;
use App\Input;
use App\Models\Event;
use App\Pagination;
use App\Permission;
use App\Response;
use App\RegExp;
use App\Time;

class EventController extends Controller {
	public $do = 'event';

	/** @var bool */
	protected $_eventPage;
	public function __construct(){
		parent::__construct();

		$this->_eventPage = isset($_POST['EVENT_PAGE']);
	}

	/** @var Event */
	protected $event;
	protected function load_event($params){
		if (empty($params['id']))
			CoreUtils::notFound();

		$this->event = Event::find($params['id']);
		if (empty($this->event))
			CoreUtils::notFound();
	}

	public function view($params){
		$this->load_event($params);

		$heading = $this->event->name;
		$event_type = Event::EVENT_TYPES[$this->event->type];

		CoreUtils::fixPath($this->event->toURL());
		$js = [true];
		if (Permission::sufficient('staff'))
			$js[] = 'pages/event/list-manage';

		CoreUtils::loadPage(__METHOD__, [
			'heading' => $heading,
			'title' => "$heading - $event_type Event",
			'css' => [true],
			'js' => $js,
			'import' => [
				'event' => $this->event,
				'event_type' => $event_type,
			],
		]);
	}

	public function list(){
		$pagination = new Pagination('/events', 5, Event::count());

		CoreUtils::fixPath($pagination->toURI());
		$heading = 'Events';
		$title = "Page {$pagination->getPage()} - $heading";

		$events = Event::find('all', $pagination->getAssocLimit());

		$js = ['paginate'];
		if (Permission::sufficient('staff'))
			$js[] = 'pages/event/list-manage';

		CoreUtils::loadPage(__METHOD__, [
			'title' => $title,
			'heading' => $heading,
			'js' => $js,
			'css' => [true],
			'import' => [
				'events' => $events,
				'pagination' => $pagination,
			],
		]);
	}

	public function api($params){
		if (Permission::insufficient('staff'))
			Response::fail();

		switch ($this->action){
			case 'GET':
				$this->load_event($params);

				Response::done($this->event->to_array([
					'except' => ['id','desc_rend'],
				]));
			break;
			case 'POST':
			case 'PUT':
				if ($this->creating)
					$this->event = new Event();
				else {
					$this->load_event($params);

					if ($this->event->isFinalized())
						Response::fail('Finalized events cannot be deleted');
				}

				$name = (new Input('name','string',[
					Input::IN_RANGE => [2,64],
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Event name is missing',
						Input::ERROR_INVALID => 'Event name (@value) is invalid',
						Input::ERROR_RANGE => 'Event name must be between @min and @max characters long',
					]
				]))->out();
				CoreUtils::checkStringValidity($name, 'Event name', INVERSE_PRINTABLE_ASCII_PATTERN);
				$this->event->name = $name;

				$description = (new Input('description','text',[
					Input::IN_RANGE => [null, 3000],
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Event description is missing',
						Input::ERROR_INVALID => 'Event description (@value) is invalid',
						Input::ERROR_RANGE => 'Event description cannot be longer than @max characters',
					]
				]))->out();
				CoreUtils::checkStringValidity($description, 'Event description', INVERSE_PRINTABLE_ASCII_PATTERN);
				$this->event->desc_src = $description;
				/** @noinspection PhpUndefinedMethodInspection */
				$this->event->desc_rend = CoreUtils::parseMarkdown($description);

				if ($this->creating){
					$type = (new Input('type',function($value){
						if (empty(Event::EVENT_TYPES[$value]))
							return Input::ERROR_INVALID;
					},[
						Input::IS_OPTIONAL => true,
						Input::CUSTOM_ERROR_MESSAGES => [
							Input::ERROR_INVALID => 'Event type (@value) is invalid',
						],
					]))->out();
					$this->event->type = $type;
				}

				$entry_role = (new Input('entry_role',function($value){
					if (!\in_array($value, Event::REGULAR_ENTRY_ROLES) && empty(Event::SPECIAL_ENTRY_ROLES[$value]))
						return Input::ERROR_INVALID;
				},[
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Event entry role is missing',
						Input::ERROR_INVALID => 'Event entry role (@value) is invalid',
					],
				]))->out();
				$this->event->entry_role = $entry_role;

				if ($this->event->type === 'contest'){
					$vote_role = (new Input('vote_role',function($value){
						if (!\in_array($value, Event::REGULAR_ENTRY_ROLES))
							return Input::ERROR_INVALID;
					},[
						Input::CUSTOM_ERROR_MESSAGES => [
							Input::ERROR_MISSING => 'Event vote role is missing',
							Input::ERROR_INVALID => 'Event vote role (@value) is invalid',
						],
					]))->out();
					$this->event->vote_role = $vote_role;
				}
				else $this->event->vote_role = null;

				$max_entries = (new Input('max_entries',function(&$value, $range){
					/** @noinspection TypeUnsafeComparisonInspection */
					if ($value == 0 || preg_match(new RegExp('^unlimited$','i'),$value)){
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
				$this->event->max_entries = $max_entries;

				if ($this->creating){
					$this->event->added_by = Auth::$user->id;
					$this->event->added_at = date('c');
				}

				$starts_at = (new Input('starts_at','timestamp',[
					Input::IS_OPTIONAL => true,
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Event start time is missng',
						Input::ERROR_INVALID => 'Event start time (@value) is invalid',
					],
				]))->out();
				$this->event->starts_at = isset($starts_at) ? date('c', $starts_at) : $this->event->added_at;

				$ends_at = (new Input('ends_at','timestamp',[
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Event end time is missng',
						Input::ERROR_INVALID => 'Event end time (@value) is invalid',
					],
				]))->out();
				$this->event->ends_at = date('c', $ends_at);

				if (!$this->event->save())
					Response::dbError('Updating event failed');

				if ($this->creating)
					Response::done(['goto' => $this->event->toURL()]);
				else {
					if ($this->_eventPage)
						Response::done();
					// Respond with minimal details if on event list
					Response::done([
						'name' => $this->event->name,
						'newurl' => $this->event->toURL(),
					]);
				}
			break;
			case 'DELETE':
				$this->load_event($params);

				if ($this->event->isFinalized())
					Response::fail('Finalized events cannot be deleted');



				if (!DB::$instance->where('id',$this->event->id)->delete('events'))
					Response::dbError('Deleting event failed');

				Response::done();
			break;
			default:
				CoreUtils::notAllowed();
		}
	}

	public function finalize($params){
		if (Permission::insufficient('staff'))
			Response::fail();

		$this->load_event($params);

		if ($this->event->type !== 'collab')
			Response::fail('Only collaboration events can be finalized');

		if ($this->event->isFinalized())
			Response::fail('This event has already been finalized');

		if (!$this->event->hasEnded())
			Response::fail('The event can only be finalized after it ends');

		$this->event->finalized_at = date('c');
		$this->event->finalized_by = Auth::$user->id;

		$favme = (new Input('favme','url',[
			Input::CUSTOM_ERROR_MESSAGES => [
			    Input::ERROR_MISSING => 'The deviation link is missing',
			    Input::ERROR_INVALID => 'The deviation link (@value) is invalid',
			]
		]))->out();
		try {
			$Image = new ImageProvider($favme, ImageProvider::PROV_DEVIATION);
			$favme = $Image->id;
		}
		catch (MismatchedProviderException $e){
			Response::fail('The deviation must be on DeviantArt, '.$e->getActualProvider().' links are not allowed');
		}
		catch (\Exception $e){ Response::fail('Deviation link issue: '.$e->getMessage()); }
		if (!CoreUtils::isDeviationInClub($favme))
			Response::fail('The deviation must be in the group gallery');
		$this->event->result_favme = $favme;

		if (!$this->event->save())
			Response::dbError('Finalizing event failed');

		Response::done();
	}

	/**
	 * This method checks whether the current user can submit any more entries
	 *
	 * @param array $params
	 */
	public function checkEntries($params){
		if (!Auth::$signed_in)
			Response::fail();

		$this->load_event($params);

		if (!$this->event->checkCanEnter(Auth::$user))
			Response::fail('You cannot participate in this event.');
		if (!$this->event->hasStarted())
			Response::fail('This event hasn\'t started yet, so entries cannot be submitted.');

		if (!empty($this->event->max_entries)){
			$entrycnt = \count(Auth::$user->getEntriesFor($this->event, 'id'));
			if ($entrycnt >= $this->event->max_entries)
				Response::fail("You've used all of your entries for this event. If you want to change your entry, edit it instead.");
			$remain = $this->event->max_entries - $entrycnt;
			Response::success("You can submit $remain more ".CoreUtils::makePlural('entry', $remain));
		}
		Response::done();
	}

}
