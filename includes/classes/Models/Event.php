<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\CoreUtils;
use App\DB;
use App\DeviantArt;
use App\Permission;
use App\UserPrefs;

/**
 * @property int          $id
 * @property int          $max_entries
 * @property string       $name
 * @property string       $type
 * @property string       $entry_role
 * @property string       $vote_role
 * @property DateTime     $starts_at
 * @property DateTime     $ends_at
 * @property string       $added_by
 * @property DateTime     $added_at
 * @property string       $desc_src
 * @property string       $desc_rend
 * @property string       $result_favme
 * @property string       $finalized_by
 * @property DateTime     $finalized_at
 * @property EventEntry[] $entries
 * @property User         $creator
 * @property User         $finalizer
 */
class Event extends NSModel implements LinkableInterface {
	public static $has_many = [
		['entries', 'class_name' => 'EventEntry', 'order' => 'score desc, submitted_at asc'],
	];
	public static $belongs_to = [
		['creator', 'class' => 'User', 'foreign_key' => 'added_by'],
		['finalizer', 'class' => 'User', 'foreign_key' => 'finalized_by'],
	];

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

	/**
	 * @return Event[]
	 */
	public static function upcoming(){
		return DB::$instance->where('starts_at > NOW()')
			->orWhere('ends_at > NOW()')
			->orderBy('starts_at')
			->get('events');
	}

	public function toURL():string {
		return "/event/{$this->id}-".$this->getSafeName();
	}
	public function toAnchor():string{
		return "<a href='{$this->toURL()}'>$this->name</a>";
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
			case 'spec_discord':
				return $user->isDiscordMember();
			case 'spec_illustrator':
			case 'spec_inkscape':
			case 'spec_ponyscape':
				$reqapp = explode('_',$this->entry_role)[1];
				$vapp = UserPrefs::get('p_vectorapp');
				return !empty($vapp) && $vapp === $reqapp;
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
		return \in_array($this->entry_role, self::REGULAR_ENTRY_ROLES) ? CoreUtils::makePlural(Permission::ROLES_ASSOC[$this->entry_role]) : self::SPECIAL_ENTRY_ROLES[$this->entry_role];
	}

	public function isFinalized(){
		return $this->type === 'collab' ? !empty($this->result_favme) : $this->hasEnded();
	}

	public function getEntriesHTML(bool $lazyload = false, bool $wrap = WRAP):string {
		$HTML = '';
		$Entries = $this->entries;
		foreach ($Entries as $entry)
			$HTML .= $entry->toListItemHTML($this, $lazyload);
		return $wrap ? "<ul id='event-entries'>$HTML</ul>" : $HTML;
	}

	public function getWinnerHTML(bool $wrap = WRAP):string {
		$HTML = '';

		if ($this->type === 'collab')
			$HTML = '<div id="final-image">'.DeviantArt::getCachedDeviation($this->result_favme)->toLinkWithPreview().'</div>';
		else {

			/** @var $HighestScoringEntries EventEntry[] */
			$HighestScoringEntries = DB::$instance->setModel('EventEntry')->query(
				'SELECT * FROM '.EventEntry::$table_name.
				'WHERE event_id = ? AND score > 0 AND score = (SELECT MAX(score) FROM '.EventEntry::$table_name.')
				ORDER BY submitted_at ASC',[$this->id]);

			if (empty($HighestScoringEntries))
				$HTML .= CoreUtils::notice('info','<span class="typcn typcn-times"></span> No entries match the win criteria, thus the event ended without a winner');
			else {
				$HTML .= '<p>The event has concluded with '.CoreUtils::makePlural('winner', \count($HighestScoringEntries),PREPEND_NUMBER).'.</p>';
				foreach ($HighestScoringEntries as $entry){
					$title = CoreUtils::escapeHTML($entry->title);
					$preview = isset($entry->prev_full) ? "<a href='{$entry->prev_src}'><img src='{$entry->prev_thumb}' alt=''><span class='title'>$title</span></a>" : "<span class='title'>$title</span>";
					$by = '<div>'.$entry->submitter->toAnchor(User::WITH_AVATAR).'</div>';
					$HTML .= "<div class='winning-entry'>$preview$by</div>";
				}
			}
		}

		return $wrap ? "<div id='results'>$HTML</div>" : $HTML;
	}
}
