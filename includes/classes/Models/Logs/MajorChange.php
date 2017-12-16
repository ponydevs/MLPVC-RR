<?php

namespace App\Models\Logs;

use App\DB;
use App\Models\Appearance;

/**
 * @inheritdoc
 * @property int        $appearance_id
 * @property string     $reason
 * @property Appearance $appearance
 */
class MajorChange extends AbstractEntryType {
	public static $table_name = 'log__major_changes';

	public static $belongs_to = [
		['appearance', 'class' => '\App\Models\Appearance'],
	];

	/**
	 * Gets the list of updates for an appearance
	 *
	 * @param int|null        $PonyID
	 * @param string|int|null $count
	 *
	 * @return MajorChange|MajorChange[]
	 */
	public static function get(?int $PonyID, $count = null){
		$LIMIT = '';
		if ($count !== null)
			$LIMIT = \is_string($count) ? $count : "LIMIT $count";
		$WHERE = $PonyID !== null ? "WHERE cm.appearance_id = $PonyID" : '';

		$query = DB::$instance->setModel(__CLASS__)->query(
			"SELECT cm.*
			FROM log__major_changes cm
			LEFT JOIN log l ON cm.entryid = l.refid AND l.reftype = 'major_changes'
			{$WHERE}
			ORDER BY l.timestamp DESC
			{$LIMIT}");

		if ($count === MOST_RECENT)
			return $query[0] ?? null;

		return $query;
	}}
