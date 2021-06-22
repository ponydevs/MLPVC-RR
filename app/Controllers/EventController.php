<?php

namespace App\Controllers;

use App\Auth;
use App\CoreUtils;
use App\Models\Event;
use App\Permission;
use App\Response;

class EventController extends Controller {
  public function __construct() {
    parent::__construct();
  }

  protected ?Event $event;

  protected function load_event($params) {
    if (empty($params['id']))
      CoreUtils::notFound();

    $this->event = Event::find($params['id']);
    if (empty($this->event))
      CoreUtils::notFound();
  }

  public function view($params) {
    $this->load_event($params);

    $heading = $this->event->name;

    CoreUtils::fixPath($this->event->toURL());

    CoreUtils::loadPage(__METHOD__, [
      'heading' => $heading,
      'title' => "$heading - Collaboration Event",
      'css' => [true],
      'js' => [true],
      'import' => [
        'event' => $this->event,
      ],
    ]);
  }

  public function list() {
    CoreUtils::fixPath('/events');
    $heading = 'Events';

    $events = Event::find('all');

    CoreUtils::loadPage(__METHOD__, [
      'title' => $heading,
      'heading' => $heading,
      'js' => ['paginate'],
      'css' => [true],
      'import' => [
        'events' => $events,
      ],
    ]);
  }

  public function api($params) {
    if (Permission::insufficient('staff'))
      Response::fail();

    switch ($this->action){
      case 'GET':
        Response::fail('Fetching event details is currently not allowed.');
      break;
      case 'POST':
      case 'PUT':
        Response::fail(($this->creating ? 'Creating new' : 'Editing existing').' events is currently not allowed.');
      break;
      case 'DELETE':
        Response::fail('Deleting events is currently not allowed.');
      break;
      default:
        CoreUtils::notAllowed();
    }
  }

  public function finalize() {
    if (Permission::insufficient('staff'))
      Response::fail();

    Response::fail("Events can't be finalized currently.");
  }

  /**
   * This method checks whether the current user can submit any more entries
   */
  public function checkEntries() {
    if (!Auth::$signed_in)
      Response::fail();

    Response::fail("Events can't receive entries currently.");
  }

}
