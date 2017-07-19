<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\Auth;
use App\CoreUtils;
use App\DB;
use App\DeviantArt;
use App\Permission;
use App\RegExp;
use App\Time;

/**
 * @property int      $id
 * @property int      $event_id
 * @property int      $score
 * @property string   $prev_src
 * @property string   $prev_full
 * @property string   $prev_thumb
 * @property string   $sub_prov
 * @property string   $sub_id
 * @property string   $submitted_by
 * @property string   $title
 * @property DateTime $submitted_at
 * @property DateTime $last_edited
 * @property User     $submitter    (Via relations)
 * @property Event    $event        (Via relations)
 */
class EventEntry extends NSModel {
	public static $table_name = 'events__entries';

	public static $belongs_to = [
		['submitter', 'class' => 'User', 'foreign_key' => 'submitted_by'],
		['event'],
	];
	public static $has_many = [
		['votes', 'class' => 'EventEntryVote', 'foreign_key' => 'entry_id'],
	];

	public function updateScore(){
		if ($this->score === null)
			return;

		$score = DB::$instance->disableAutoClass()->where('entryid', $this->id)->getOne('events__entries__votes', 'COALESCE(SUM(value),0) as score');
		DB::$instance->where('entryid', $this->id)->update('events__entries',$score);
		$this->score = $score['score'];

		try {
			CoreUtils::socketEvent('entry-score',[
				'entryid' => $this->id,
			]);
		}
		catch (\Exception $e){
			error_log("SocketEvent Error\n".$e->getMessage()."\n".$e->getTraceAsString());
		}
	}

	public function getUserVote(User $user):?EventEntryVote {
		return EventEntryVote::find_by_entry_id_and_user_id($this->id, $user->id);
	}

	public function getFormattedScore(){
		return $this->score < 0 ? '−'.(-$this->score) : $this->score;
	}

	private static function _getPreviewDiv(string $fullsize, string $preview, ?string $filetype = null):string {
		$type = '';
		if ($filetype !== null){
			if ($filetype === 'zip')
				$filetype = 'svg<span class="star-info" title="ZIP archives are assumed to be SVG files">*</span>';
			$type = "<span class='filetype'>$filetype</span>";
		}
		return "<div class='preview'><a href='{$fullsize}' target='_blank' rel='noopener'><img src='{$preview}' alt='event entry preview'></a>$type</div>";
	}

	public function getListItemVoting(Event $event):string {
		if ($event->type === 'contest' && Auth::$signed_in){
			$userVote = $this->getUserVote(Auth::$user);
			$userVoted = !empty($userVote) && $userVote->isLockedIn($this);
			$canvote = $event->checkCanVote(Auth::$user);
			$vd = !$canvote || $userVoted || $this->submitted_by === Auth::$user->id ? 'disabled' : '';
			if (!empty($userVote)){
				$uvc = $userVote->value === 1 ? ' clicked' : '';
				$dvc = $userVote->value === -1 ? ' clicked' : '';
			}
			else $uvc = $dvc = '';

			return <<<HTML
<div class='voting'>
	<button class='typcn typcn-arrow-sorted-up upvote$uvc' $vd title='Upvote'></button>
	<span class='score' title="Score">{$this->getFormattedScore()}</span>
	<button class='typcn typcn-arrow-sorted-down downvote$dvc' $vd title='Downvote'></button>
</div>
HTML;
		}

		return '';
	}

	public function getListItemPreview($submission = null):?string {
		if ($submission === null)
			$submission = DeviantArt::getCachedDeviation($this->sub_id, $this->sub_prov);
		if ($this->sub_prov === 'fav.me'){
			if ($submission->preview !== null && $submission->fullsize !== null)
				return self::_getPreviewDiv($submission->fullsize, $submission->preview, $submission->type);
		}
		if ($this->prev_thumb !== null && $this->prev_full !== null)
			 return self::_getPreviewDiv($this->prev_full, $this->prev_thumb, $submission->type);
		return '';
	}

	public function toListItemHTML(Event $event = null, bool $lazyload = false, bool $wrap = true):string {
		if ($event === null)
			$event = $this->event;
		$title = CoreUtils::escapeHTML($this->title);
		$submitter = $this->submitter;
		$submitter_link = $submitter->getProfileLink();
		$submitter_vapp = $submitter->getVectorAppIcon();
		if (!empty($submitter_vapp))
			$submitter_link = preg_replace(new RegExp('(</a>)$'),"$submitter_vapp$1", $submitter_link);

		$submit_tag = Time::tag(strtotime($this->submitted_at));
		$edited_tag = $this->last_edited !== $this->submitted_at ? '<div><span class="shorten edited">Last edited </span><span class="typcn typcn-pencil" title="Last edited"></span>'.Time::tag(strtotime($this->last_edited)).'</div>' :'';

		$sub_prov_favme = $this->sub_prov === 'fav.me';
		if ($sub_prov_favme || Permission::sufficient('staff')){
			$title = "<a href='http://{$this->sub_prov}/{$this->sub_id}' target='_blank' rel='noopener'>$title</a>";
		}
		if ($lazyload)
			$preview = "<div class='entry-deviation-promise' data-entryid='{$this->id}'></div>";
		else $preview = $this->getListItemPreview();

		$voting = $this->getListItemVoting($event);

		$actions = Auth::$signed_in && (Auth::$user->id === $this->submitted_by || Permission::sufficient('staff'))
			? "<div class='actions'>
				<button class='blue typcn typcn-pencil edit-entry' title='Edit'></button>
				<button class='red typcn typcn-times delete-entry' title='Withdraw'></button>
			</div>"
			: '';

		$HTML = <<<HTML
$voting
$preview
<div class="details">
	<span class="label">{$title}</span>
	<div class="submitter">
		<div><span class="shorten submitter">By </span><span class="typcn typcn-user" title="By"></span>{$submitter_link}</div>
		<div><span class="shorten time">Submitted </span><span class="typcn typcn-time" title="Submitted"></span>$submit_tag</div>
		$edited_tag
	</div>
	$actions
</div>
HTML;
		return $wrap ? "<li id='entry-{$this->id}'>$HTML</li>" : $HTML;
	}
}
