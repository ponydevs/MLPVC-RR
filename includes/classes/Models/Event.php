<?php

namespace App\Models;

class Event extends AbstractFillable {
	/** @var string */
	public
		$id,
		$name,
		$type,
		$entry_role,
		$starts_at,
		$ends_at,
		$added_by,
		$added_at,
		$description;
	/** @var int */
	public
		$max_entries;

	const EVENT_TYPES = [
		'collab' => 'Collaboration',
		//'contest' => 'Contest',
	];

	const REGULAR_ENTRY_ROLES = ['user', 'member', 'staff'];

	const SPECIAL_ENTRY_ROLES = [
		//'spec_discord' => 'Discord Server Members',
		//'spec_ai' => 'Illustrator Users',
		//'spec_inkscape' => 'Inkscape Users',
		//'spec_ponyscape' => 'Ponyscape Users',
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
		return "/event/{$this->id}";
	}
}
