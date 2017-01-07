<?php

namespace App;

use App\Exceptions\MismatchedProviderException;
use App\Models\Cutiemark;
use App\Models\User;

class Cutiemarks {
	/**
	 * @param int    $AppearanceID
	 * @param string $cols
	 *
	 * @return Cutiemark[]|null
	 */
	static function get($AppearanceID, $cols = '*'){
		global $Database;

		return $Database->where('ponyid', $AppearanceID)->get('cutiemarks', null, $cols);
	}

	const VALID_FACING_VALUES = ['left','right'];

	/**
	 * @param Cutiemark[] $CutieMarks
	 * @param bool        $wrap
	 *
	 * @return string
	 */
	static function getListForAppearancePage($CutieMarks, $wrap = WRAP){
		$HTML = '';
		foreach ($CutieMarks as $cm){
			$HTML .= self::getListItemForAppearancePage($cm);
		}

		return $wrap ? "<ul id='pony-cm-list'>$HTML</ul>" : $HTML;
	}

	/**
	 * @param Cutiemark $cm
	 * @param bool      $wrap
	 *
	 * @return string
	 */
	static function getListItemForAppearancePage(Cutiemark $cm, $wrap = WRAP){
		$facing = isset($cm->facing) ? 'Facing '.CoreUtils::capitalize($cm->facing) : 'Symmetrical';
		$previewSVG = Appearances::getCMPreviewSVGURL($cm);
		$preview = CoreUtils::aposEncode($cm->getPreviewURL());

		$Vector = DeviantArt::getCachedDeviation($cm->favme);
		$userlink = Users::get($Vector['author'],'name','name, avatar_url')->getProfileLink(User::LINKFORMAT_FULL);
		$content = <<<HTML
<span class="title">$facing</span>
<a  class="preview" href="http://fav.me/{$cm->favme}" style="background-image:url('{$previewSVG}')">
	<div class="img" style="transform: rotateZ({$cm->favme_rotation}deg); background-image:url('{$preview}')"></div>
</a>
<span class="madeby">$userlink</span>
HTML;
		return $wrap ? "<li class='pony-cm'>$content</li>" : $content;
	}

	// null (=symmetric) is stringified to '' by implode
	const VALID_FACING_COMBOS = ['left','right',''];
	// TODO Implement the remaining facing options
	//const VALID_FACING_COMBOS = ['left,right','right,left','left','right',''];

	static function postProcess(&$data, int $index){
		$favme = isset($_POST['favme'][$index]) ? trim($_POST['favme'][$index]) : null;
		if (empty($favme)){
			if ($index > 0)
				return false;
			Response::fail('Deviation link is missing');
		}

		if (isset($_POST['facing'][$index])){
			$facing = trim($_POST['facing'][$index]);
			if (empty($facing))
				$facing = null;
			else if (!in_array($facing,Cutiemarks::VALID_FACING_VALUES))
				Response::fail('Body orientation is invalid');
		}
		else $facing = null;
		$data['facing'] = $facing;

		try {
			$Image = new ImageProvider($favme, array('fav.me', 'dA'));
			$favme = $Image->id;
		}
		catch (MismatchedProviderException $e){
			Response::fail('The cutie mark vector must be on DeviantArt, '.$e->getActualProvider().' links are not allowed');
		}
		catch (\Exception $e){ Response::fail("Cutie Mark link issue: ".$e->getMessage()); }
		if (!CoreUtils::isDeviationInClub($favme))
			Response::fail('The cutie mark vector must be in the group gallery');
		$data['favme'] = $favme;

		if (!isset($_POST['favme_rotation'][$index]))
			Response::fail('Preview rotation amount is missing');
		$favme_rotation = intval($_POST['favme_rotation'][$index]);
		if (!is_numeric($favme_rotation))
			Response::fail('Preview rotation must be a number');
		if ($favme_rotation < -180 || $favme_rotation > 180)
			Response::fail('Preview rotation must be between -180 and 180');
		$data['favme_rotation'] = $favme_rotation;

		$data['preview'] = null;
		$data['preview_src'] = null;
		if (isset($_POST['preview_src'][$index])){
			$preview_src = trim($_POST['preview_src'][$index]);
			if (!empty($preview_src)){
				$prov = new ImageProvider($preview_src);
				if (!isset($prov->preview))
					Response::fail('Preview image could not be found.');

				$data['preview'] = $prov->preview;
				$data['preview_src'] = $preview_src;
			}
		}
	}

	/**
	 * @param Cutiemark[] $CMs
	 * @return string
	 */
	static function convertDataForLogs($CMs):string {
		foreach ($CMs as $k => $v){
			$CMs[$k] = (array)$v;
			if (isset($v->ponyid))
				unset($CMs[$k]['ponyid']);
		}
		return JSON::encode($CMs);
	}
}
