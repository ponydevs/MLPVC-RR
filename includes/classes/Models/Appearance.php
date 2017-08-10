<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\Appearances;
use App\Auth;
use App\CGUtils;
use App\CoreUtils;
use App\DB;
use App\Episodes;
use App\Permission;
use App\RegExp;
use App\Time;

/**
 * @property int                 $id
 * @property int                 $order
 * @property string              $label
 * @property string              $notes_src
 * @property string              $notes_rend
 * @property string              $owner_id
 * @property DateTime            $added
 * @property DateTime            $last_cleared
 * @property bool                $ishuman
 * @property bool                $private
 * @property Cutiemark[]         $cutiemarks
 * @property ColorGroup[]        $color_groups
 * @property User|null           $owner
 * @property RelatedAppearance[] $related_appearances (Via magic method)
 * @property Color[]             $preview_colors      (Via magic method)
 * @property Tag[]               $tags
 * @property Tagged[]            $tagged
 * @method static Appearance[] find_by_sql($sql, $values = null)
 * @method static Appearance find_by_owner_id_and_label(string $uuid, string $label)
 * @method static Appearance find_by_ishuman_and_label($ishuman, string $label)
 * @method static Appearance|Appearance[] find(...$args)
 */
class Appearance extends NSModel implements LinkableInterface {
	/** @var int[] */

	public static $has_many = [
		['cutiemarks', 'foreign_key' => 'appearance_id', 'order' => 'facing asc'],
		['tags', 'through' => 'tagged'],
		['tagged', 'class' => 'Tagged'],
		['color_groups', 'order' => '"order" asc, id asc'],
		['related_appearances', 'class' => 'RelatedAppearance', 'foreign_key' => 'source_id', 'order' => 'target_id asc'],
	];
	public static $belongs_to = [
		['owner', 'class' => 'User', 'foreign_key' => 'owner_id'],
	];
	public static $before_save = ['render_notes'];

	/** @return Color[] */
	public function get_preview_colors(){
		if ($this->private)
			return [];

		/** @var $arr Color[] */
		$arr = DB::$instance->setModel('Color')->query(
			'SELECT c.hex FROM colors c
			LEFT JOIN color_groups cg ON c.group_id = cg.id
			WHERE cg.appearance_id = ? AND c.hex IS NOT NULL
			ORDER BY cg."order" ASC, c."order" ASC
			LIMIT 4', [$this->id]);

		if (!empty($arr))
			usort($arr, function(Color $a, Color $b){
				return CoreUtils::yiq($b->hex) <=> CoreUtils::yiq($a->hex);
			});

		return $arr;
	}

	/**
	 * Replaces non-alphanumeric characters in the appearance label with dashes
	 *
	 * @return string
	 */
	public function getSafeLabel():string {
		return CoreUtils::makeUrlSafe($this->label);
	}

	public static function find_dupe(bool $creating, bool $personalGuide, array $data){
		$firstcol = $personalGuide?'owner_id':'ishuman';
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
	public function getSpriteURL(int $size = self::SPRITE_SIZES['REGULAR'], string $fallback = ''):string {
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
	public function getSpriteHTML(bool $canUpload):string {
		$imgPth = $this->getSpriteURL();
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

	private static function _processNotes(string $notes):string {
		$notes = CoreUtils::sanitizeHtml($notes);
		$notes = preg_replace(new RegExp('(\s)(&gt;&gt;(\d+))(\s|$)'),"$1<a href='https://derpibooru.org/$3'>$2</a>$4",$notes);
		$notes = preg_replace_callback('/'.EPISODE_ID_PATTERN.'/',function($a){
			$Ep = Episodes::getActual((int) $a[1], (int) $a[2]);
			return !empty($Ep)
				? "<a href='{$Ep->toURL()}'>".CoreUtils::aposEncode($Ep->formatTitle(AS_ARRAY,'title')).'</a>'
				: "<strong>{$a[0]}</strong>";
		},$notes);
		$notes = preg_replace_callback('/'.MOVIE_ID_PATTERN.'/',function($a){
			$Ep = Episodes::getActual(0, (int) $a[1], true);
			return !empty($Ep)
				? "<a href='{$Ep->toURL()}'>".CoreUtils::aposEncode(Episodes::shortenTitlePrefix($Ep->formatTitle(AS_ARRAY,'title'))).'</a>'
				: "<strong>{$a[0]}</strong>";
		},$notes);
		$notes = preg_replace_callback('/(?:^|[^\\\\])\K(?:#(\d+))(\'s?)?\b/',function($a){

			$Appearance = DB::$instance->where('id', $a[1])->getOne('appearances');
			return (
				!empty($Appearance)
				? "<a href='/cg/v/{$Appearance->id}'>{$Appearance->label}</a>".(!empty($a[2])?CoreUtils::posess($Appearance->label, true):'')
				: "$a[0]"
			);
		},$notes);
		return nl2br(str_replace('\#', '#', $notes));
	}

	/**
	 * Get the notes for a specific appearance
	 *
	 * @param bool $wrap
	 * @param bool $cmLink
	 *
	 * @return string
	 */
	public function getNotesHTML(bool $wrap = WRAP, bool $cmLink = true):string {
		global $EPISODE_ID_REGEX;

		if (!empty($this->notes_src)){
			if ($this->notes_rend === null){
				$this->notes_rend = self::_processNotes($this->notes_src);
				$this->save();
			}
			$notes = "<span>{$this->notes_rend}</span>";
		}
		else {
			if (!Permission::sufficient('staff'))
				return '';
			$notes = '';
		}
		return $wrap ? "<div class='notes'>$notes</div>" : $notes;
	}

	public function isPrivate(bool $ignoreStaff = false):bool {
		$isPrivate = !empty($this->private);
		if (!$ignoreStaff && (Permission::sufficient('staff') || (Auth::$signed_in ? $this->owner_id === Auth::$user->id : false)))
			$isPrivate = false;
		return $isPrivate;
	}

	/**
	 * As of right now this simply changes single quotes in the lable to their "smart" version
	 *
	 * @return string
	 */
	public function processLabel():string {
		return preg_replace(new RegExp("'"),'’', $this->label);
	}

	/**
	 * Returns the HTML for the placeholder which is displayed in place of the color group
	 *  to anyone without edit access while the appearance is private
	 *
	 * @return string
	 */
	public function getPendingPlaceholder():string {
		return $this->isPrivate() ? "<div class='colors-pending'><span class='typcn typcn-time'></span> ".(isset($this->last_cleared) ? 'This appearance is currently undergoing maintenance and will be available again shortly &mdash; '.Time::tag($this->last_cleared) :  'This appearance will be finished soon, please check back later &mdash; '.Time::tag($this->added)).'</div>' : false;
	}

	/**
	 * Retruns preview image link
	 *
	 * @return string
	 */
	public function getPreviewURL():string {
		$path = str_replace('#',$this->id,CGUtils::PREVIEW_SVG_PATH);
		return "/cg/v/{$this->id}p.svg?t=".(file_exists($path) ? filemtime($path) : time());
	}

	public function toURL():string {
		$safeLabel = $this->getSafeLabel();
		$owner = $this->owner_id !== null ? '/@'.User::find($this->owner_id)->name : '';
		return "$owner/cg/v/{$this->id}-$safeLabel";
	}

	public function toAnchor():string {
		$label = $this->processLabel();
		$link = $this->toURL();
		return "<a href='$link'>$label</a>";
	}

	public function toAnchorWithPreview():string {
		$preview = $this->getPreviewURL();
		$preview = "<img src='$preview' class='preview'>";
		$label = $this->processLabel();
		$link = $this->toURL();
		return "<a href='$link'>$preview<span>$label</span></a>";
	}

	/**
	 * @param Tag $tag
	 *
	 * @return bool Indicates whether the passed tag is used on the appearance
	 */
	public function is_tagged(Tag $tag):bool {
		return Tagged::is($tag, $this);
	}

	public function getRelatedHTML():string {
		$LINKS = '';
		if (count($this->related_appearances) === 0)
			return $LINKS;
		foreach ($this->related_appearances as $r)
			$LINKS .= '<li>'.$r->target->toAnchorWithPreview().'</li>';
		return "<section class='related'><h2>Related appearances</h2><ul>$LINKS</ul></section>";
	}

	/**
	 * Re-index the appearance in ElasticSearch
	 */
	public function reindex(){
		// We don't want to index private appearances
		if ($this->owner_id !== null)
			return;

		Appearances::updateIndex($this);
	}

	public function clearRelations(){
		RelatedAppearance::delete_all(['conditions' => [
			'source_id = :id OR target_id = :id',
			['id' => $this->id],
		]]);
	}

	public function getSpriteRelevantColors():array {
		$Colors = [];
		foreach ([0, $this->id] as $AppearanceID){
			$ColorGroups = ColorGroup::find_all_by_appearance_id($AppearanceID);
			$SortedColorGroups = [];
			foreach ($ColorGroups as $cg)
				$SortedColorGroups[$cg->id] = $cg;

			$AllColors = CGUtils::getColorsForEach($ColorGroups);
			foreach ($AllColors as $cg){
				/** @var $cg Color[] */
				foreach ($cg as $c)
					$Colors[] = [
						'hex' => $c->hex,
						'label' => $SortedColorGroups[$c->group_id]->label.' | '.$c->label,
						'mandatory' => $AppearanceID !== 0,
					];
			}
		}
		if ($this->owner_id === null)
			$Colors = array_merge($Colors,
				[
					[
						'hex' => '#D8D8D8',
						'label' => 'Mannequin | Outline',
						'mandatory' => false,
					],
					[
		                'hex' => '#E6E6E6',
		                'label' => 'Mannequin | Fill',
						'mandatory' => false,
					],
					[
		                'hex' => '#BFBFBF',
		                'label' => 'Mannequin | Shadow Outline',
						'mandatory' => false,
					],
					[
		                'hex' => '#CCCCCC',
		                'label' => 'Mannequin | Shdow Fill',
						'mandatory' => false,
					]
				]
			);

		return [ $Colors, $ColorGroups, $AllColors ];
	}

	public function spriteHasColorIssues():bool {
		if (empty($this->getSpriteURL()))
			return false;

		/** @var $SpriteColors int[] */
		$SpriteColors = array_flip(CGUtils::getSpriteImageMap($this->id)['colors']);

		foreach ($this->getSpriteRelevantColors()[0] as $c){
			if ($c['mandatory'] && !isset($SpriteColors[$c['hex']]))
				return true;
		}

		return false;
	}

	public function checkSpriteColors(){
		$checkWho = $this->owner_id ?? Appearances::SPRITE_NAG_USERID;
		$hasColorIssues = $this->spriteHasColorIssues();
		$oldNotifs = Appearances::getSpriteColorIssueNotifications($this->id, $checkWho);
		if ($hasColorIssues && empty($oldNotifs))
			Notification::send($checkWho,'sprite-colors',['appearance_id' => $this->id]);
		else if (!$hasColorIssues && !empty($oldNotifs))
			Appearances::clearSpriteColorIssueNotifications($oldNotifs);
	}

	public function render_notes(){
		if ($this->notes_src === null)
			$this->notes_rend = null;
		else $this->notes_rend = self::_processNotes($this->notes_src);
	}
}
