<?php

namespace App\Models;

use App\Auth;
use App\CGUtils;
use App\CoreUtils;
use App\DeviantArt;
use App\File;
use App\Permission;
use App\Users;

/**
 * @property int         $id
 * @property int         $appearance_id
 * @property string      $facing
 * @property string|null $favme
 * @property int         $rotation
 * @property string|null $label
 * @property Appearance  $appearance     (Via relations)
 * @property string|null $contributor_id (Via magic method)
 * @property User        $contributor    (Via magic method)
 * @method static Cutiemark find(int $id)
 * @method static Cutiemark[] find_by_sql($sql, $data = null)
 * @method static Cutiemark[] find_all_by_appearance_id(int $appearance_id)
 */
class Cutiemark extends NSModel {
	public static $table_name = 'cutiemarks';

	public static $belongs_to = [
		['appearance'],
	];

	public static $after_destroy = ['remove_files'];

	public function get_contributor_id(){
		$attrval = $this->read_attribute('contributor_id');
		if ($attrval === null && $this->favme !== null){
			$deviation = DeviantArt::getCachedDeviation($this->favme);
			if (!empty($deviation)){
				$cont = Users::get($deviation->author, 'name');
				if (!empty($cont)){
					$this->contributor_id = $cont->id;
					$this->save();
					return $cont->id;
				}
			}
		}
		return $attrval;
	}

	public function get_contributor(){
		return $this->contributor_id !== null ? Users::get($this->contributor_id) : null;
	}

	public function getTokenizedFilePath(){
		return FSPATH."cm_tokenized/{$this->id}.svg";
	}

	const SOURCE_FOLDER = FSPATH.'cm_source/';

	public function getSourceFilePath(){
		return self::SOURCE_FOLDER.$this->id.'.svg';
	}

	public function getRenderedFilePath(){
		return FSPATH."cg_render/cutiemark/{$this->id}.svg";
	}

	public function getTokenizedFile():?string {
		$tokenized_path = $this->getTokenizedFilePath();
		$source_path = $this->getSourceFilePath();
		$source_exists = file_exists($source_path);
		if (file_exists($tokenized_path)){
			if (!$source_exists){
				CoreUtils::deleteFile($tokenized_path);
				$this->appearance->clearRenderedImages([Appearance::CLEAR_CM]);
				return null;
			}
			if (filemtime($tokenized_path) >= filemtime($source_path))
				return File::get($tokenized_path);
		}

		if (!$source_exists)
			return null;

		$data = File::get($source_path);
		$data = CGUtils::tokenizeSvg(CoreUtils::sanitizeSvg($data), $this->appearance_id);
		CoreUtils::createFoldersFor($tokenized_path);
		File::put($tokenized_path, $data);
		return $data;
	}

	/**
	 * @see CGUtils::renderCMSVG()
	 * @return string|null
	 */
	public function getRenderedRelativeURL():?string {
		return "/cg/cutiemark/{$this->id}.svg";
	}

	/**
	 * @see CGUtils::renderCMSVG()
	 * @return string|null
	 */
	public function getRenderedURL():?string {
		return $this->getRenderedRelativeURL().'?t='.CoreUtils::filemtime($this->getRenderedFilePath());
	}

	/**
	 * @see CGUtils::renderCMFacingSVG()
	 * @return string
	 */
	public function getFacingSVGURL(){
		return $this->appearance->getFacingSVGURL($this->facing);
	}


	/**
	 * @param bool $wrap
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function getListItemForAppearancePage($wrap = WRAP){
		$facing = $this->facing !== null ? 'Facing '.CoreUtils::capitalize($this->facing) : 'Symmetrical';
		$facingSVG = $this->getFacingSVGURL();
		$preview = CoreUtils::aposEncode($this->getRenderedURL());

		$canEdit = Permission::sufficient('staff') || (Auth::$signed_in && $this->appearance->owner_id === Auth::$user->id);
		$hasLabel = $this->label !== null;
		$id = $canEdit ? "<span class='cm-id'>{$this->id}</span> " : '';
		$title = "<span class='title'>$id".($hasLabel ? CoreUtils::escapeHTML($this->label) : $facing).'</span>';
		$subtitle = $hasLabel ? "\n<span class='subtitle'>$facing</span>" : '';

		$links = "<a href='/cg/cutiemark/download/{$this->id}' class='btn link typcn typcn-download'>SVG</a>";
		if ($canEdit){
			$who = ($this->appearance->owner_id !== null ? 'Owner and ' : '').'Staff';
			$links .= "<a href='/cg/cutiemark/download/{$this->id}?source' class='btn orange typcn typcn-download' title='Download the original file as uploaded ($who only)'></a>";
		}
		if (($this->favme ?? null) !== null)
			$links .= "<a href='http://fav.me/{$this->favme}' class='btn btn-da typcn'>Source</a>";

		$madeby = '';
		if ($this->contributor !== null){
			$userlink = $this->contributor->toAnchor(User::WITH_AVATAR);
			$madeby = "<span class='madeby'>By $userlink</span>";
		}

		$rotate = $this->rotation !== 0 ? "transform:rotate({$this->rotation}deg)" : '';
		$content = <<<HTML
$title$subtitle
<div class="preview" style="background-image:url('{$facingSVG}')">
	<div class="img" style="background-image:url('{$preview}');$rotate"></div>
</div>
<div class="dl-links">$links</div>
$madeby
HTML;
		return $wrap ? "<li class='pony-cm' id='cm{$this->id}'>$content</li>" : $content;
	}

	public function to_js_response(){
		$response = $this->to_array(['except' => ['contributor_id','favme']]);
		if ($this->favme !== null)
			$response['deviation'] = 'http://fav.me/'.$this->favme;
		if ($this->contributor_id !== null)
			$response['username'] = $this->contributor->name;
		$response['rendered'] = $this->getRenderedURL();
		return $response;
	}

	public function remove_files(){
		CoreUtils::deleteFile($this->getSourceFilePath());
		CoreUtils::deleteFile($this->getTokenizedFilePath());
		CoreUtils::deleteFile($this->getRenderedFilePath());
	}
}
