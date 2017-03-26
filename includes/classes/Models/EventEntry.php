<?php

namespace App\Models;

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
		$title;
	/** @param array|object */
	public function __construct($iter = null){
		parent::__construct($this, $iter);
	}

	public function updateScore(){
		global $Database;
		if (is_null($this->score))
			return;

		$score = $Database->disableAutoClass()->where('entryid', $this->entryid)->getOne('events__entries__votes', 'SUM(value) as score');
		$Database->where('entryid', $this->entryid)->update('events__entries',$score);
		$this->score = $score['score'];
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

	public function toListItemHTML(Event $event, bool $wrap = true):string {
		global $signedIn, $currentUser;

		$submission = DeviantArt::getCachedDeviation($this->sub_id, $this->sub_prov);
		$filetype = $submission->type;
		$preview = isset($this->prev_thumb) && isset($this->prev_full)
			? self::_getPreviewDiv($this->prev_full, $this->prev_thumb, $filetype)
			: '';
		$title = CoreUtils::escapeHTML($this->title);
		$submitter = Users::get($this->submitted_by)->getProfileLink();
		$submit_tag = Time::tag(strtotime($this->submitted_at));

		$sub_prov_favme = $this->sub_prov === 'fav.me';
		if ($sub_prov_favme || Permission::sufficient('staff')){
			$title = "<a href='http://{$this->sub_prov}/{$this->sub_id}' target='_blank' rel='noopener'>$title</a>";
		}
		if ($sub_prov_favme && empty($preview)){
			if (isset($submission->preview) && isset($submission->fullsize))
				$preview = self::_getPreviewDiv($submission->fullsize, $submission->preview, $filetype);
		}

		if ($event->type === 'contest' && $signedIn && $event->checkCanVote($currentUser)){
			$userVote = $this->getUserVote($currentUser);
			if (!empty($userVote)){
				$vd = ' disabled';
				$uvc = $userVote->value === 1 ? ' clicked' : '';
				$dvc = $userVote->value === -1 ? ' clicked' : '';
			}
			else $vd = $uvc = $dvc = '';

			$voting = <<<HTML
<div class='voting'>
	<button class='typcn typcn-arrow-sorted-up upvote$uvc'$vd title='Upvote'></button>
	<span class='score'>{$this->getFormattedScore()}</span>
	<button class='typcn typcn-arrow-sorted-down downvote$dvc'$vd title='Downvote'></button>
</div>
HTML;
		}
		else $voting = '';

		$actions = $signedIn && ($currentUser->id === $this->submitted_by || Permission::sufficient('staff')) && time() < strtotime($event->ends_at)
			? '<button class="blue typcn typcn-pencil edit-entry">Edit</button><button class="red typcn typcn-times delete-entry">Withdraw</button>'
			: '';

		if (!empty($actions))
			$actions = "<div class='actions'>$actions</div>";

		$HTML = <<<HTML
$voting
$preview
<div class="details">
	<span class="label">{$title}</span>
	<span class="submitter">Made by {$submitter}<br>Submitted {$submit_tag}</span>
	$actions
</div>
HTML;
		return $wrap ? "<li id='entry-{$this->entryid}'>$HTML</li>" : $HTML;
	}
}
