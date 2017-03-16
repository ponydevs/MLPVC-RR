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
		$eventid;
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

	private static function _getPreviewDiv(string $fullsize, string $preview):string {
		return "<div class='preview'><a href='{$fullsize}' target='_blank'><img src='{$preview}' alt='event entry preview'></a></div>";
	}

	public function toListItemHTML(Event $event, bool $wrap = true):string {
		global $signedIn, $currentUser;

		$preview = isset($this->prev_thumb) && isset($this->prev_full)
			? self::_getPreviewDiv($this->prev_full, $this->prev_thumb)
			: '';
		$title = CoreUtils::escapeHTML($this->title);
		$submitter = Users::get($this->submitted_by)->getProfileLink(User::LINKFORMAT_TEXT);
		$submit_tag = Time::tag(strtotime($this->submitted_at));

		if ($this->sub_prov === 'fav.me'){
			$title = "<a href='http://fav.me/{$this->sub_id}'>$title</a>";
			if (empty($preview)){
				$submission = DeviantArt::getCachedDeviation($this->sub_id, $this->sub_prov);
				if (isset($submission->preview) && isset($submission->fullsize))
					$preview = self::_getPreviewDiv($submission->fullsize, $submission->preview);
			}
		}

		$actions = $signedIn && ($currentUser->id === $this->submitted_by || Permission::sufficient('staff')) && time() < strtotime($event->ends_at)
			? '<div class="actions"><button class="blue typcn typcn-pencil edit-entry">Edit</button><button class="red typcn typcn-times delete-entry">Withdraw</button></div>'
			: '';

		$HTML = <<<HTML
$preview
<div class="details">
	<span class="label">{$title}</span>
	<span class="submitter">Submitted by {$submitter} {$submit_tag}</span>
	$actions
</div>
HTML;
		return $wrap ? "<li id='entry-{$this->entryid}'>$HTML</li>" : $HTML;
	}
}
