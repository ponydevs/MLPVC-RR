<?php

namespace App\Models;

use App\Auth;
use App\CoreUtils;
use App\DeviantArt;
use App\ImageProvider;
use App\Permission;
use App\Time;
use App\Users;

class EventEntry extends AbstractFillable {
	/** @var int */
	public
		$entryid,
		$eventid,
		$score;
	/** @var string */
	public
		$prev_src,
		$prev_full,
		$prev_thumb,
		$sub_prov,
		$sub_id,
		$submitted_by,
		$submitted_at,
		$title,
		$last_edited;
	/** @param array|object */
	public function __construct($iter = null){
		parent::__construct($this, $iter);
	}

	public function updateScore(){
		global $Database;
		if (is_null($this->score))
			return;

		$score = $Database->disableAutoClass()->where('entryid', $this->entryid)->getOne('events__entries__votes', 'COALESCE(SUM(value),0) as score');
		$Database->where('entryid', $this->entryid)->update('events__entries',$score);
		$this->score = $score['score'];

		try {
			CoreUtils::socketEvent('entry-score',[
				'entryid' => $this->entryid,
			]);
		}
		catch (\Exception $e){
			error_log("SocketEvent Error\n".$e->getMessage()."\n".$e->getTraceAsString());
		}
	}

	public function getUserVote(User $user):?EventEntryVote {
		global $Database;

		/** @noinspection PhpIncompatibleReturnTypeInspection */
		return $Database->where('entryid', $this->entryid)->where('userid', $user->id)->getOne('events__entries__votes');
	}

	public function getFormattedScore(){
		return $this->score < 0 ? '−'.(-$this->score) : $this->score;
	}

	private static function _getPreviewDiv(string $fullsize, string $preview, ?string $filetype = null):string {
		$type = isset($filetype) ? "<span class='filetype'>$filetype</span>" : '';
		return "<div class='preview'><a href='{$fullsize}' target='_blank' rel='noopener'><img src='{$preview}' alt='event entry preview'></a>$type</div>";
	}

	public function getListItemVoting(Event $event):string {
		if ($event->type === 'contest' && Auth::$signed_in && $event->checkCanVote(Auth::$user)){
			$userVote = $this->getUserVote(Auth::$user);
			$userVoted = !empty($userVote) && $userVote->isLockedIn($this);
			$vd = $userVoted || $this->submitted_by === Auth::$user->id ? ' disabled' : '';
			if (!empty($userVote)){
				$uvc = $userVote->value === 1 ? ' clicked' : '';
				$dvc = $userVote->value === -1 ? ' clicked' : '';
			}
			else $uvc = $dvc = '';

			return <<<HTML
<div class='voting'>
	<button class='typcn typcn-arrow-sorted-up upvote$uvc'$vd title='Upvote'></button>
	<span class='score' title="Score">{$this->getFormattedScore()}</span>
	<button class='typcn typcn-arrow-sorted-down downvote$dvc'$vd title='Downvote'></button>
</div>
HTML;
		}

		return '';
	}

	public function toListItemHTML(Event $event, bool $wrap = true):string {
		$submission = DeviantArt::getCachedDeviation($this->sub_id, $this->sub_prov);
		$filetype = $submission->type;
		$preview = isset($this->prev_thumb) && isset($this->prev_full)
			? self::_getPreviewDiv($this->prev_full, $this->prev_thumb, $filetype)
			: '';
		$title = CoreUtils::escapeHTML($this->title);
		$submitter = Users::get($this->submitted_by)->getProfileLink();
		$submit_tag = Time::tag(strtotime($this->submitted_at));
		$edited_tag = $this->last_edited !== $this->submitted_at ? '<br><span class="shorten edited">Last edited </span><span class="typcn typcn-pencil" title="Last edited"></span>'.Time::tag(strtotime($this->last_edited)) :'';

		$sub_prov_favme = $this->sub_prov === 'fav.me';
		if ($sub_prov_favme || Permission::sufficient('staff')){
			$title = "<a href='http://{$this->sub_prov}/{$this->sub_id}' target='_blank' rel='noopener'>$title</a>";
		}
		if ($sub_prov_favme && empty($preview)){
			if (isset($submission->preview) && isset($submission->fullsize))
				$preview = self::_getPreviewDiv($submission->fullsize, $submission->preview, $filetype);
		}

		$voting = $this->getListItemVoting($event);

		$actions = Auth::$signed_in && (Auth::$user->id === $this->submitted_by || Permission::sufficient('staff'))
			? '<button class="blue typcn typcn-pencil edit-entry" title="Edit"></button><button class="red typcn typcn-times delete-entry" title="Withdraw"></button>'
			: '';

		if (!empty($actions))
			$actions = "<div class='actions'>$actions</div>";

		$HTML = <<<HTML
$voting
$preview
<div class="details">
	<span class="label">{$title}</span>
	<span class="submitter"><span class="shorten submitter">By </span><span class="typcn typcn-user" title="By"></span>{$submitter}<br><span class="shorten time">Submitted </span><span class="typcn typcn-time" title="Submitted"></span>{$submit_tag}{$edited_tag}</span>
	$actions
</div>
HTML;
		return $wrap ? "<li id='entry-{$this->entryid}'>$HTML</li>" : $HTML;
	}
}
