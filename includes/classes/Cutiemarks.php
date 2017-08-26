<?php

namespace App;

use App\Models\Appearance;
use App\Models\CachedDeviation;
use App\Models\Cutiemark;
use App\Models\User;

class Cutiemarks {
	/**
	 * Fetches FRESH cutiemark data from the database instead of using the cached property
	 *
	 * @param Appearance $Appearance
	 *
	 * @return Cutiemark[]
	 */
	public static function get(Appearance $Appearance){
		return Cutiemark::find_all_by_appearance_id($Appearance->id);
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
			$HTML .= self::getListItemForAppearancePage($cm);

		return $wrap ? "<ul id='pony-cm-list'>$HTML</ul>" : $HTML;
	}

	/**
	 * @param Cutiemark $cm
	 * @param bool      $wrap
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function getListItemForAppearancePage(Cutiemark $cm, $wrap = WRAP){
		$facing = $cm->facing !== null ? 'Facing '.CoreUtils::capitalize($cm->facing) : 'Symmetrical';
		$facingSVG = $cm->getFacingSVGURL();
		$preview = CoreUtils::aposEncode($cm->getRenderedURL());

		$canEdit = Permission::sufficient('staff') || (Auth::$signed_in && $cm->appearance->owner_id === Auth::$user->id);

		$links = "<a href='/cg/cutiemark/download/{$cm->id}' class='btn link typcn typcn-download'>SVG</a>";
		if ($canEdit){
			$who = ($cm->appearance->owner_id !== null ? 'Owner and ' : '').'Staff';
			$links .= "<a href='/cg/cutiemark/download/{$cm->id}?source' class='btn orange typcn typcn-download' title='Download the original file as uploaded ($who only)'></a>";
		}
		if (($cm->favme ?? null) !== null)
			$links .= "<a href='http://fav.me/{$cm->favme}' class='btn btn-da typcn'>Source</a>";

		$madeby = '';
		if ($cm->contributor !== null){
			$userlink = $cm->contributor->toAnchor(User::WITH_AVATAR);
			$madeby = "<span class='madeby'>By $userlink</span>";
		}

		$id = $canEdit ? "<span class='cm-id'>{$cm->id}</span> " : '';
		$rotate = $cm->rotation !== 0 ? "transform:rotate({$cm->rotation}deg)" : '';
		$content = <<<HTML
<span class="title">$id$facing</span>
<div class="preview" style="background-image:url('{$facingSVG}')">
	<div class="img" style="background-image:url('{$preview}');$rotate"></div>
</div>
<div class="dl-links">$links</div>
$madeby
HTML;
		return $wrap ? "<li class='pony-cm' id='cm{$cm->id}'>$content</li>" : $content;
	}

	/**
	 * @param Cutiemark[] $CMs
	 * @return string
	 */
	public static function convertDataForLogs($CMs):string {
		foreach ($CMs as $k => $v)
			$CMs[$k] = $v->to_array([
				'except' => 'appearance_id',
			]);
		return JSON::encode($CMs);
	}
}
