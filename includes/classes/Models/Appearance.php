<?php

namespace App\Models;

use ActiveRecord\Model;
use App\CGUtils;
use App\CoreUtils;
use App\Episodes;
use App\Permission;
use App\RegExp;

/**
 * @property int         $id
 * @property int         $order
 * @property string      $label
 * @property string      $notes
 * @property string      $added
 * @property string      $owned_by
 * @property string      $last_cleared
 * @property bool        $ishuman
 * @property bool        $private
 * @property Cutiemark[] $cutiemarks
 * @property User|null   $owner
 * @method static Appearance[] find_by_sql($sql, $values = null)
 * @method static Appearance find_by_owned_by_and_label(string $uuid, string $label)
 * @method static Appearance find_by_ishuman_and_label($ishuman, string $label)
 * @method static Appearance|Appearance[] find(...$args)
 */
class Appearance extends Model {
	static $has_many = [
		['cutiemarks', 'foreign_key' => 'ponyid', 'order' => 'facing asc'],
		['tags', 'through' => 'tagged', 'foreign_key' => 'ponyid', 'primary_key' => 'tid'],
	];
	static $belongs_to = [
		['owner', 'class' => 'User', 'foreign_key' => 'owned_by'],
	];

	/**
	 * Replaces non-alphanumeric characters in the appearance label with dashes
	 *
	 * @return string
	 */
	function getSafeLabel():string {
		return CoreUtils::makeUrlSafe($this->label);
	}

	static function find_dupe(bool $creating, bool $personalGuide, array $data){
		$firstcol = $personalGuide?'owned_by':'ishuman';
		$conds = [
			"$firstcol = ? AND label = ?",
			$data[$firstcol],
			$data['label'],
		];
		if (!$creating){
			$conds[0] .= ' AND id != ?';
			$conds[] = $data['id'];
		}
		return Appearance::find('first', [ 'conditions' => $conds ]);
	}

	/** @var int[] */
	const SPRITE_SIZES = [
		'REGULAR' => 600,
		'SOURCE' => 300,
	];

	/**
	 * Get sprite URL for an appearance
	 *
	 * @param int    $size
	 * @param string $fallback
	 *
	 * @return string
	 */
	function getSpriteURL(int $size = self::SPRITE_SIZES['REGULAR'], string $fallback = ''):string {
		$fpath = SPRITE_PATH."{$this->id}.png";
		if (file_exists($fpath))
			return "/cg/v/{$this->id}s.png?s=$size&t=".filemtime($fpath);
		return $fallback;
	}

	/**
	 * Returns the HTML for sprite images
	 *
	 * @param bool $canUpload
	 *
	 * @return string
	 */
	function getSpriteHTML(bool $canUpload):string {
		$imgPth = self::getSpriteURL($this->id);
		if (!empty($imgPth)){
			$img = "<a href='$imgPth' target='_blank' title='Open image in new tab'><img src='$imgPth' alt='".CoreUtils::aposEncode($this->label)."'></a>";
			if ($canUpload)
				$img = "<div class='upload-wrap'>$img</div>";
		}
		else if ($canUpload)
			$img = "<div class='upload-wrap'><a><img src='/img/blank-pixel.png'></a></div>";
		else return '';

		return "<div class='sprite'>$img</div>";
	}

	/**
	 * Get the notes for a specific appearance
	 *
	 * @param bool $wrap
	 * @param bool $cmLink
	 *
	 * @return string
	 */
	function getNotesHTML(bool $wrap = WRAP, bool $cmLink = true):string {
		global $EPISODE_ID_REGEX;

		$hasNotes = !empty($this->notes);
		if ($hasNotes){
			$notes = '';
			if ($hasNotes){
				$this->notes = preg_replace_callback('/'.EPISODE_ID_PATTERN.'/',function($a){
					$Ep = Episodes::getActual((int) $a[1], (int) $a[2]);
					return !empty($Ep)
						? "<a href='{$Ep->toURL()}'>".CoreUtils::aposEncode($Ep->formatTitle(AS_ARRAY,'title'))."</a>"
						: "<strong>{$a[0]}</strong>";
				},$this->notes);
				$this->notes = preg_replace_callback('/'.MOVIE_ID_PATTERN.'/',function($a){
					$Ep = Episodes::getActual(0, (int) $a[1], true);
					return !empty($Ep)
						? "<a href='{$Ep->toURL()}'>".CoreUtils::aposEncode($Ep->formatTitle(AS_ARRAY,'title'))."</a>"
						: "<strong>{$a[0]}</strong>";
				},$this->notes);
				$this->notes = preg_replace_callback('/(?:^|[^\\\\])\K(?:#(\d+))\b/',function($a){
					global $Database;
					$Appearance = $Database->where('id', $a[1])->getOne('appearances');
					return (
						!empty($Appearance)
						? "<a href='/cg/v/{$Appearance->id}'>{$Appearance->label}</a>"
						: "$a[0]"
					);
				},$this->notes);
				$this->notes = str_replace('\#', '#', $this->notes);
				$notes = '<span>'.nl2br($this->notes).'</span>';
			}
		}
		else {
			if (!Permission::sufficient('staff')) return '';
			$notes = '';
		}
		return $wrap ? "<div class='notes'>$notes</div>" : $notes;
	}

	function isPrivate(bool $ignoreStaff = false):bool {
		$isPrivate = !empty($this->private);
		if (!$ignoreStaff && (Permission::sufficient('staff') || (Auth::$signed_in ? $this->owned_by === Auth::$user->id : false)))
			$isPrivate = false;
		return $isPrivate;
	}

	/**
	 * As of right now this simply changes single quotes in the lable to their "smart" version
	 *
	 * @return string
	 */
	function processLabel():string {
		return preg_replace(new RegExp("'"),'’', $this->label);
	}

	/**
	 * Returns the HTML for the placeholder which is displayed in place of the color group
	 *  to anyone without edit access while the appearance is private
	 *
	 * @return string
	 */
	function getPendingPlaceholder():string {
		return $this->isPrivate() ? "<div class='colors-pending'><span class='typcn typcn-time'></span> ".(isset($this->last_cleared) ? "This appearance is currently undergoing maintenance and will be available again shortly &mdash; ".Time::tag($this->last_cleared) :  "This appearance will be finished soon, please check back later &mdash; ".Time::tag($this->added)).'</div>' : false;
	}

	/**
	 * Retruns preview image link
	 *
	 * @return string
	 */
	function getPreviewURL():string {
		$path = str_replace('#',$this->id,CGUtils::PREVIEW_SVG_PATH);
		return "/cg/v/{$this->id}p.svg?t=".(file_exists($path) ? filemtime($path) : time());
	}

	function getLink():string {
		$safeLabel = $this->getSafeLabel();
		$owner = isset($this->owned_by) ? '/@'.(User::find($this->owned_by)->name) : '';
		return "$owner/cg/v/{$this->id}-$safeLabel";
	}

	/**
	 * @return string
	 */
	function getLinkWithPreviewHTML():string {
		$preview = $this->getPreviewURL();
		$preview = "<img src='$preview' class='preview'>";
		$label = $this->processLabel();
		$link = $this->getLink();
		return "<a href='$link'>$preview<span>$label</span></a>";
	}
}
