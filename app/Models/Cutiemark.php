<?php

namespace App\Models;

use App\Auth;
use App\CGUtils;
use App\CoreUtils;
use App\DeviantArt;
use App\File;
use App\NSUriBuilder;
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
	/** For Twig */
	public function getAppearance():Appearance {
		return $this->appearance;
	}

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
		return $this->contributor_id !== null ? User::find($this->contributor_id) : null;
	}
	/** For Twig */
	public function getContributor():?User {
		return $this->contributor;
	}

	public function getTokenizedFilePath(){
		return FSPATH."cm_tokenized/{$this->id}.svg";
	}

	public const SOURCE_FOLDER = FSPATH.'cm_source/';

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
		return "/cg/cutiemark/{$this->id}.svg".(!empty($_GET['token']) ? "?token={$_GET['token']}" : '');
	}

	/**
	 * @see CGUtils::renderCMSVG()
	 * @return string|null
	 */
	public function getRenderedURL():?string {
		$token = !empty($_GET['token']) ? '&token='.urlencode($_GET['token']) : '';
		return $this->getRenderedRelativeURL().'?t='.CoreUtils::filemtime($this->getRenderedFilePath()).$token;
	}

	/**
	 * @see CGUtils::renderCMFacingSVG()
	 * @return string
	 */
	public function getFacingSVGURL(){
		return $this->appearance->getFacingSVGURL($this->facing);
	}

	public function getPreviewForAppearancePageListItem(){
		$facing_svg = $this->getFacingSVGURL();
		$preview = CoreUtils::aposEncode($this->getRenderedURL());
		$rotate = $this->rotation !== 0 ? "transform:rotate({$this->rotation}deg)" : '';
		return <<<HTML
			<div class="preview" style="background-image:url('{$facing_svg}')">
				<div class="img" style="background-image:url('{$preview}');$rotate"></div>
			</div>
			HTML;

	}

	public function canEdit():bool {
		return Permission::sufficient('staff') || (Auth::$signed_in && $this->appearance->owner_id === Auth::$user->id);
	}

	public function getDownloadURL($source = false):string {
		$url = new NSUriBuilder("/cg/cutiemark/download/{$this->id}");
		if (!empty($_GET['token']))
			$url->append_query_param('token', $_GET['token']);
		if ($source)
			$url->append_query_param('source', null);
		return $url;
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
