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
	/** For Twig */
	public function getAppearance():Appearance {
		return $this->appearance;
	}

	public static function total(bool $eqg):int {
		$query = DB::$instance->querySingle(
			'SELECT COUNT(mc.entryid) as total
			FROM log__major_changes mc
			INNER JOIN appearances a ON mc.appearance_id = a.id
			WHERE a.ishuman = ?', [$eqg]);

		return $query['total'] ?? 0;
	}

	/**
	 * Gets the list of updates for an entire guide or just an appearance
	 *
	 * @param int|null        $PonyID
	 * @param bool|null       $EQG
	 * @param string|int|null $count
	 *
	 * @return MajorChange|MajorChange[]
	 */
	public static function get(?int $PonyID, ?bool $EQG, $count = null){
		$LIMIT = '';
		if ($count !== null)
			$LIMIT = \is_string($count) ? $count : "LIMIT $count";
		$WHERE = $PonyID !== null ? "WHERE mc.appearance_id = $PonyID" : 'WHERE a.ishuman = '.($EQG?'true':'false');

		$query = DB::$instance->setModel(__CLASS__)->query(
			"SELECT mc.*
			FROM log__major_changes mc
			INNER JOIN log l ON mc.entryid = l.refid AND l.reftype = 'major_changes'
			INNER JOIN appearances a ON mc.appearance_id = a.id
			{$WHERE}
			ORDER BY l.timestamp DESC
			{$LIMIT}");

		if ($count === MOST_RECENT)
			return $query[0] ?? null;

		return $query;
	}}
