<?php

namespace App\Models;

use App\CoreUtils;
use App\Permission;
use App\Response;
use App\UserPrefs;

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
		$desc_rend;

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
	static function get(int $id):?Event {
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
			case "user":
			case "member":
			case "staff":
				return Permission::sufficient($this->entry_role, $user->role);
			break;
			case "spec_discord":
				return $user->isDiscordMember();
			break;
			case "spec_illustrator":
			case "spec_inkscape":
			case "spec_ponyscape":
				$reqapp = explode('_',$this->entry_role)[1];
				$vapp = UserPrefs::get('p_vectorapp');
				return  !empty($vapp) && $vapp === $reqapp;
			break;
		}
	}

	public function checkCanVote(User $user):bool {
		return Permission::sufficient($this->vote_role, $user->role);
	}

	public function getEntryRoleName():string {
		return in_array($this->entry_role, self::REGULAR_ENTRY_ROLES) ? CoreUtils::makePlural(Permission::ROLES_ASSOC[$this->entry_role]) : self::SPECIAL_ENTRY_ROLES[$this->entry_role];
	}

	/**
	 * @return \App\Models\EventEntry[]
	 */
	public function getEntries(){
		global $Database;

		return $Database->where('eventid', $this->id)->orderBy('score','ASC')->orderBy('submitted_at','ASC')->get('events__entries');
	}

	public function getEntriesHTML(bool $wrap = WRAP):string {
		$HTML = '';
		$Entries = $this->getEntries();
		foreach ($Entries as $entry)
			$HTML .= $entry->toListItemHTML($this);
		return $wrap ? "<ul id='event-entries'>$HTML</ul>" : $HTML;
	}
}
