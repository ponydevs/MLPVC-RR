<?php

namespace App\Models;

use App\CGUtils;
use App\CoreUtils;
use App\DeviantArt;
use App\File;
use App\Users;

/**
 * @property int        $id
 * @property int        $appearance_id
 * @property string     $facing
 * @property string     $favme
 * @property int        $rotation
 * @property Appearance $appearance     (Via relations)
 * @property int        $contributor_id (Via magic method)
 * @property User       $contributor    (Via magic method)
 * @method static Cutiemark find(int $id)
 * @method static Cutiemark[] find_by_sql($sql, $data = null)
 */
class Cutiemark extends NSModel {
	static $table_name = 'cutiemarks';

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

	public function getSourceFilePath(){
		return FSPATH."cm_source/{$this->id}.svg";
	}

	public function getVectorFilePath(){
		return str_replace(['*','#'],[$this->id,$this->appearance_id],CGUtils::CM_SVG_PATH);
	}

	public function getTokenizedFile():?string {
		$tokenized_path = $this->getTokenizedFilePath();
		$source_path = $this->getSourceFilePath();
		$source_exists = file_exists($source_path);
		if (file_exists($tokenized_path)){
			if (!$source_exists){
				@unlink($tokenized_path);
				$this->appearance->clearRenderedImages([Appearance::CLEAR_CM]);
				return null;
			}
			if (filemtime($tokenized_path) >= filemtime($source_path))
				return File::get($tokenized_path);
		}

		if (!$source_exists){
			$data = DeviantArt::trackDownSVG($this->favme);
			if ($data === null)
				return null;

			CoreUtils::createFoldersFor($source_path);
			File::put($source_path, $data);
		}
		else $data = File::get($source_path);
		$data = CGUtils::tokenizeSvg(CoreUtils::sanitizeSvg($data), $this->appearance_id);
		CoreUtils::createFoldersFor($tokenized_path);
		File::put($tokenized_path, $data);
		return $data;
	}

	/**
	 * @see CGUtils::renderCMSVG()
	 * @return string|null
	 */
	public function getVectorRelativeURL():?string {
		return "/cg/cutiemark/{$this->id}.svg";
	}

	/**
	 * @see CGUtils::renderCMSVG()
	 * @return string|null
	 */
	public function getVectorURL():?string {
		return $this->getVectorRelativeURL().'?t='.CoreUtils::filemtime($this->getVectorFilePath());
	}

	/**
	 * @see CGUtils::renderCMFacingSVG()
	 * @return string
	 */
	public function getFacingSVGURL(){
		return $this->appearance->getFacingSVGURL($this->facing);
	}

	public function remove_files(){
		@unlink($this->getSourceFilePath());
		@unlink($this->getTokenizedFilePath());
		@unlink($this->getVectorFilePath());
	}
}
