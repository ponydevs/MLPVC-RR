<?php

namespace App;

class Updates {
	/**
	 * Gets the list of updates for an appearance
	 *
	 * @param int        $PonyID
	 * @param string|int $count
	 *
	 * @return array
	 */
	public static function get($PonyID, $count = null){
		global $Database;

		$LIMIT = '';
		if (isset($count))
			$LIMIT = is_string($count) ? $count : "LIMIT $count";
		$WHERE = isset($PonyID) ? "WHERE cm.ponyid = $PonyID" :'';
		$query = $Database->rawQuery(
			"SELECT cm.*, l.initiator, l.timestamp
			FROM log__color_modify cm
			LEFT JOIN log l ON cm.entryid = l.refid && l.reftype = 'color_modify'
			{$WHERE}
			ORDER BY l.timestamp DESC
			{$LIMIT}");

		if ($count === MOST_RECENT)
			return $query[0] ?? null;
		return $query;
	}
}
