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
use App\Models\EventEntry;
use App\Models\EventEntryVote;
use App\Pagination;
use App\Permission;
use App\Response;
use App\RegExp;
use App\Time;

class EventEntryController extends EventController {

	/** @var EventEntry */
	private $entry;
	private function load_event_entry($params, string $action){
		if (!Auth::$signed_in)
			Response::fail();

		if (!isset($params['entryid']))
			Response::fail('Entry ID is missing or invalid');

		$this->entry = EventEntry::find(\intval($params['entryid'], 10));
		if (empty($this->entry))
			Response::fail('The requested entry could not be found');
		if ($action === 'lazyload')
			return;

		if ($action === 'manage' && $this->entry->submitted_by !== Auth::$user->id && Permission::insufficient('staff'))
			Response::fail("You don't have permission to manage this entry");

		$this->load_event(['id' => $this->entry->event_id]);

		if ($action === 'vote'){
			if ($this->event->type !== 'contest')
				Response::fail('You can only vote on entries to contest events');
			if (!$this->event->hasEnded())
				Response::fail('This event has ended; entries can no longer be voted on');
			if (!$this->event->checkCanVote(Auth::$user))
				Response::fail('You are not allowed to vote on entries to this event');
			if ($this->entry->submitted_by === Auth::$user->id)
				Response::fail('You cannot vote on your own entries', ['disable' => true]);
		}

		if ($action !== 'view' && Permission::insufficient('staff') && $this->event->ends_at->getTimestamp() < time())
			Response::fail('This event has ended, entries can no longer be submitted or modified. Please ask a staff member if you need to make any changes.');
	}

	private function _processEntryData():array {
		$update = [];

		$link = (new Input('link','url',[
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Entry link is missing',
				Input::ERROR_INVALID => 'Entry link (@value) is invalid',
			]
		]))->out();
		try {
			$submission = new ImageProvider($link, [
				ImageProvider::PROV_FAVME,
				ImageProvider::PROV_DA,
				ImageProvider::PROV_STASH,
			], false, false);
		}
		catch (MismatchedProviderException|UnsupportedProviderException $e){
			Response::fail('Entry link must point to a deviation or Sta.sh submission');
		}
		catch (\Exception $e){
			Response::fail('Erroe while checking submission link: '.$e->getMessage());
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
				Response::fail('Preview image error: '.$e->getMessage());
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

	public function api($params){
		switch ($this->action){
			case 'GET':
				$this->load_event_entry($params, 'manage');

				Response::done([
					'link' => "http://{$this->entry->sub_prov}/{$this->entry->sub_id}",
					'title' => $this->entry->title,
					'prev_src' => $this->entry->prev_src,
				]);
			break;
			case 'POST':
				if (!Auth::$signed_in)
					Response::fail();

				$this->load_event($params);

				if (!$this->event->checkCanEnter(Auth::$user))
					Response::fail('You cannot participate in this event.');
				if (!$this->event->hasStarted())
					Response::fail('This event hasn\'t started yet, so entries cannot be submitted.');
				if ($this->event->hasEnded() && Permission::insufficient('staff'))
					Response::fail('This event has concluded, so no new entries can be submitted.');

				$insert = new EventEntry();
				foreach ($this->_processEntryData() as $k => $v)
					$insert->{$k} = $v;
				$insert->submitted_by = Auth::$user->id;
				$insert->event_id = $this->event->id;
				$insert->score = $this->event->type === 'contest' ? 0 : null;
				if (!$insert->save())
					Response::dbError('Saving entry failed');

				Response::done(['entrylist' => $this->event->getEntriesHTML(false, NOWRAP)]);
			break;
			case 'PUT':
				$this->load_event_entry($params, 'manage');

				$changes = [];
				foreach ($this->_processEntryData() as $k => $v){
					if ($v !== $this->entry->{$k})
						$changes[$k] = $v;
				}

				if (!empty($changes)){
					$changes['last_edited'] = date('c');
					$this->entry->update_attributes($changes);
				}

				Response::done(['entryhtml' => $this->entry->toListItemHTML($this->event, false, NOWRAP)]);
			break;
			case 'DELETE':
				$this->load_event_entry($params, 'manage');

				if (!$this->entry->delete())
					Response::dbError('Failed to delete entry');

				Response::done();
			break;
			default:
				CoreUtils::notAllowed();
		}
	}

	public function voteApi($params){
		switch ($this->action){
			case 'GET':
				$this->load_event_entry($params, 'view');

				Response::done([ 'voting' => $this->entry->getListItemVoting($this->event) ]);
			break;
			case 'POST':
				$this->load_event_entry($params, 'vote');

				$userVote = $this->entry->getUserVote(Auth::$user);

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

				$vote = new EventEntryVote();
				$vote->entry_id = $this->entry->id;
				$vote->user_id = Auth::$user->id;
				$vote->value = $value;
				if (!$vote->save())
					Response::dbError('Vote could not be recorded');

				$this->entry->updateScore();
				Response::done([ 'score' => $this->entry->getFormattedScore() ]);
			break;
			case 'DELETE':
				$this->load_event_entry($params, 'vote');

				$userVote = $this->entry->getUserVote(Auth::$user);
				if (empty($userVote))
					Response::fail('You haven\'t voted for this entry yet');
				$this->_checkWipeLockedInVote($userVote);

				$this->entry->updateScore();
				Response::done([ 'score' => $this->entry->getFormattedScore() ]);
			break;
			default:
				CoreUtils::notAllowed();
		}
	}

	private function _checkWipeLockedInVote(EventEntryVote $userVote){
		if ($userVote->isLockedIn($this->entry))
			Response::fail('You already voted on this post '.Time::tag($userVote->cast_at).'. Your vote is now locked in until the post is edited.');

		if (!DB::$instance->where('userid', Auth::$user->id)->where('entryid', $this->entry->id)->delete(EventEntryVote::$table_name))
			Response::dbError('Vote could not be removed');
	}

	public function lazyload($params){
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		$this->load_event_entry($params, 'lazyload');

		Response::done(['html' => $this->entry->getListItemPreview()]);
	}
}
