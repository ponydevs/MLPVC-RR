<?php

namespace App;

use App\Models\Appearance;
use App\Models\Cutiemark;

class Cutiemarks {
	/**
	 * Fetches FRESH cutiemark data from the database instead of using the cached property
	 * DO NOT REPLACE WITH ActiveRecord RELATIONS
	 *
	 * @param Appearance $Appearance
	 *
	 * @return Cutiemark[]
	 */
	public static function get(Appearance $Appearance){
		return DB::$instance->where('appearance_id', $Appearance->id)->get(Cutiemark::$table_name);
	}

	const VALID_FACING_VALUES = ['left','right'];

	/**
	 * @param Cutiemark[] $CutieMarks
	 * @param bool        $wrap
	 *
	 * @return string
	 */
	public static function getListForAppearancePage($CutieMarks, $wrap = WRAP){
		$HTML = '';
		foreach ($CutieMarks as $cm)
			$HTML .= $cm->getListItemForAppearancePage();

		return $wrap ? "<ul id='pony-cm-list'>$HTML</ul>" : $HTML;
	}

	/**
	 * @param Cutiemark[] $CMs
	 * @return string
	 */
	public static function convertDataForLogs($CMs):string {
		$out = [];
		foreach ($CMs as $v)
			$out[$v->id] = $v->to_array([
				'except' => ['id','appearance_id'],
			]);
		return JSON::encode($out);
	}
}
