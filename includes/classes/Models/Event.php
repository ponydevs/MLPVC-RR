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
		$added_at;

	const EVENT_TYPES = [
		'collab' => 'Collaboration',
		//'contest' => 'Contest',
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
		return $Database->where('id', $id)->getOne('event');
	}

	public function toLink():string {
		return "/event/{$this->id}";
	}
}
