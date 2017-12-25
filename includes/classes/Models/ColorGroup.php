<?php

namespace App\Models;

use App\CoreUtils;
use App\Permission;
use App\UserPrefs;
use SeinopSys\RGBAColor;

/**
 * @inheritdoc
 * @property int        $id
 * @property int        $appearance_id
 * @property string     $label
 * @property Appearance $appearance
 * @property Color[]    $colors
 * @method static ColorGroup|ColorGroup[] find(...$args)
 * @method static ColorGroup[] find_all_by_appearance_id(int $appearance_id)
 */
class ColorGroup extends OrderedModel {
	public static $table_name = 'color_groups';

	public static $belongs_to = [
		['appearance'],
	];

	public static $has_many = [
		['colors', 'foreign_key' => 'group_id', 'order' => '"order" asc'],
	];

	/** @inheritdoc */
	public function assign_order(){
		if ($this->order !== null)
			return;

		$LastGroup = self::find('first',[
			'conditions' => [ 'appearance_id' => $this->appearance_id ],
			'order' => '"order" desc',
		]);
		$this->order = !empty($LastGroup->order) ? $LastGroup->order+1 : 1;
	}

	/**
	 * Make sure appearance_id is filtered somehow in the $opts array
	 *
	 * @inheritdoc
	 */
	 public static function in_order(array $opts = []){
		self::addOrderOption($opts);
		return self::find('all', $opts);
	 }

	/**
	 * Remove the colors in this group without removing the group itself
	 */
	public function wipeColors(){
        Color::delete_all([
            'conditions' => [ 'group_id' => $this->id ],
        ]);
	}

	/**
	 * Get HTML for a color group
	 *
	 * @param Color[][] $AllColors
	 * @param bool      $wrap
	 * @param bool      $colon
	 * @param bool      $colorNames
	 * @param bool      $force_extra_info
	 *
	 * @return string
	 */
	public function getHTML($AllColors = null, bool $wrap = true, bool $colon = true, bool $colorNames = false, bool $force_extra_info = false):string {
		$label = CoreUtils::escapeHTML($this->label).($colon?': ':'');
		$HTML =
			"<span class='cat'>$label".
				($colorNames && Permission::sufficient('staff')?'<span class="admin"><button class="blue typcn typcn-pencil edit-cg"><span>Edit</span></button><button class="red typcn typcn-trash delete-cg"><span>Delete</span></button></span>':'').
			'</span>';
		$Colors = empty($AllColors) ? $this->colors : ($AllColors[$this->id] ?? null);
		if (!empty($Colors)){
			$extraInfo = $force_extra_info || !UserPrefs::get('cg_hideclrinfo');
			foreach ($Colors as $i => $c){
				$title = CoreUtils::aposEncode($c->label);
				$color = '';
				if (!empty($c->hex)){
					$color = $c->hex;
					$title .= "' style='background-color:$color' class='valid-color";
				}

				$append = "<span title='$title'>$color</span>";
				if ($colorNames){
					$label = CoreUtils::escapeHTML($c->label);
					$append = "<div class='color-line".(!$extraInfo || empty($color)?' no-detail':'')."'>$append<span><span class='label'>$label";
					if ($extraInfo && !empty($color)){
						/** @noinspection NullPointerExceptionInspection */
						$rgb = RGBAColor::parse($color)->toRGB();
						$append .= "</span><span class='ext'>$color &bull; $rgb";
					}
					$append .= '</span></div>';
				}
				$HTML .= $append;
			}
		}

		return $wrap ? "<li id='cg{$this->id}'>$HTML</li>" : $HTML;
	}
}
