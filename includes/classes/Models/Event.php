<?php

namespace App\Models;

use App\CoreUtils;
use App\DeviantArt;
use App\Permission;
use App\UserPrefs;
use App\Users;

class Event extends AbstractFillable {
	/** @var int */
	public
		$id,
		$max_entries;
	/** @var string */
	public
		$name,
		$type,
		$entry_role,
		$vote_role,
		$starts_at,
		$ends_at,
		$added_by,
		$added_at,
		$desc_src,
		$desc_rend,
		$result_favme,
		$finalized_at,
		$finalized_by;

	const EVENT_TYPES = [
		'collab' => 'Collaboration',
		'contest' => 'Contest',
	];

	const REGULAR_ENTRY_ROLES = ['user', 'member', 'staff'];

	const SPECIAL_ENTRY_ROLES = [
		'spec_discord' => 'Discord Server Members',
		'spec_ai' => 'Illustrator Users',
		'spec_inkscape' => 'Inkscape Users',
		'spec_ponyscape' => 'Ponyscape Users',
	];

	/** @param array|object */
	public function __construct($iter = null){
		parent::__construct($this, $iter);
	}

	/**
	 * @param int $id
	 *
	 * @return Event|null
	 */
	public static function get(int $id):?Event {
		global $Database;

		/** @noinspection PhpIncompatibleReturnTypeInspection */
		return $Database->where('id', $id)->getOne('events');
	}

	public function toURL():string {
		return "/event/{$this->id}-".$this->getSafeName();
	}

	public function getSafeName():string {
		return CoreUtils::makeUrlSafe($this->name);
	}

	public function checkCanEnter(User $user):bool {
		switch ($this->entry_role){
			case 'user':
			case 'member':
			case 'staff':
				return Permission::sufficient($this->entry_role, $user->role);
			break;
			case 'spec_discord':
				return $user->isDiscordMember();
			break;
			case 'spec_illustrator':
			case 'spec_inkscape':
			case 'spec_ponyscape':
				$reqapp = explode('_',$this->entry_role)[1];
				$vapp = UserPrefs::get('p_vectorapp');
				return  !empty($vapp) && $vapp === $reqapp;
			break;
		}
	}

	public function checkCanVote(User $user):bool {
		return !$this->hasEnded() && Permission::sufficient($this->vote_role, $user->role);
	}

	public function hasStarted(?int $now = null){
		return ($now??time()) >= strtotime($this->starts_at);
	}

	public function hasEnded(?int $now = null){
		return ($now??time()) >= strtotime($this->ends_at);
	}

	public function getEntryRoleName():string {
		return in_array($this->entry_role, self::REGULAR_ENTRY_ROLES) ? CoreUtils::makePlural(Permission::ROLES_ASSOC[$this->entry_role]) : self::SPECIAL_ENTRY_ROLES[$this->entry_role];
	}

	public function isFinalized(){
		return $this->type === 'collab' ? !empty($this->result_favme) : $this->hasEnded();
	}

	/**
	 * @return \App\Models\EventEntry[]
	 */
	public function getEntries(){
		global $Database;

		return $Database->where('eventid', $this->id)->orderBy('score','DESC')->orderBy('submitted_at','ASC')->get('events__entries');
	}

	public function getEntriesHTML(bool $wrap = WRAP):string {
		$HTML = '';
		$Entries = $this->getEntries();
		foreach ($Entries as $entry)
			$HTML .= $entry->toListItemHTML($this);
		return $wrap ? "<ul id='event-entries'>$HTML</ul>" : $HTML;
	}

	public function getWinnerHTML(bool $wrap = WRAP):string {
		$HTML = '';

		if ($this->type === 'collab')
			$HTML = '<div id="final-image">'.DeviantArt::getCachedDeviation($this->result_favme)->toLinkWithPreview().'</div>';
		else {
			global $Database;
			/** @var $HighestScoringEntries EventEntry[] */
			$HighestScoringEntries = $Database->setClass(EventEntry::class)->rawQuery(
				'SELECT * FROM events__entries
				WHERE eventid = ? && score > 0 && score = (SELECT MAX(score) FROM events__entries)
				ORDER BY submitted_at ASC',[$this->id]);

			if (empty($HighestScoringEntries))
				$HTML .= CoreUtils::notice('info','<span class="typcn typcn-times"></span> No entries match the win criteria, thus the event ended without a winner');
			else {
				$HTML .= '<p>The event has concluded with '.CoreUtils::makePlural('winner',count($HighestScoringEntries),PREPEND_NUMBER).'.</p>';
				foreach ($HighestScoringEntries as $entry){
					$title = CoreUtils::escapeHTML($entry->title);
					$preview = $entry->prev_full !== null ? "<a href='{$entry->prev_src}'><img src='{$entry->prev_thumb}' alt=''><span class='title'>$title</span></a>" : "<span class='title'>$title</span>";
					$by = '<div>'.Users::get($entry->submitted_by)->getProfileLink(User::LINKFORMAT_FULL).'</div>';
					$HTML .= "<div class='winning-entry'>$preview$by</div>";
				}
			}
		}

		return $wrap ? "<div id='results'>$HTML</div>" : $HTML;
	}
}
