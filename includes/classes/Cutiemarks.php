<?php

namespace App;

use App\Exceptions\MismatchedProviderException;
use App\Models\Appearance;
use App\Models\Cutiemark;
use App\Models\User;

class Cutiemarks {
	/**
	 * @param Appearance $Appearance
	 * @param bool       $procSym
	 *
	 * @return Cutiemark[]|null
	 */
	public static function get(Appearance $Appearance, bool $procSym = true){
		/** @var $CMs Cutiemark[] */
		$CMs = DB::$instance->where('appearance_id', $Appearance->id)->get(Cutiemark::$table_name);
		return $CMs;
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
		$preview = CoreUtils::aposEncode($cm->getVectorURL());

		$canEdit = Permission::sufficient('staff') || (Auth::$signed_in && $cm->appearance->owner_id === Auth::$user->id);

		$links = "<a href='/cg/cutiemark/download/{$cm->id}' class='btn link typcn typcn-download'>SVG</a>";
		if ($canEdit)
			$links .= "<a href='/cg/cutiemark/download/{$cm->id}?source' class='btn orange typcn typcn-download' title='Download the original file as uploaded (Staff only)'></a>";
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
	 * @param Cutiemark $cm
	 * @param array     $item
	 */
	public static function postProcess(Cutiemark $cm, array $item){
		// TODO Update ALL uses of this method
		if (isset($item['facing'])){
			$facing = CoreUtils::trim($item['facing']);
			if (empty($facing))
				$facing = null;
			else if (!in_array($facing,self::VALID_FACING_VALUES,true))
				Response::fail('Body orientation is invalid');
		}
		else $facing = null;
		$cm->facing = $facing;

		switch ($item['attribution']){
			case 'deviation':
				$deviation = new Input('deviation','favme',[
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Deviation link is missing',
						Input::ERROR_INVALID => 'Deviation link (@value) is invalid',
					],
				]);
				$cm->favme = $favme;
			break;
			case 'user':

			break;
			case 'none':
				// Skip validation
			break;
			default:
				Response::fail('The specified credit method is invalid');
		}

		if (!isset($item['rotation']))
			Response::fail('Preview rotation amount is missing');
		if (!is_numeric($item['rotation']))
			Response::fail('Preview rotation must be a number');
		$rotation = (int) $item['frotation'];
		if (abs($rotation) > 45)
			Response::fail('Preview rotation must be between -45 and 45');
		$cm->rotation = $rotation;
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
