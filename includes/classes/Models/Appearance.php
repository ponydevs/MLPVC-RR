<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\Appearances;
use App\Auth;
use App\CGUtils;
use App\CoreUtils;
use App\DB;
use App\Episodes;
use App\Models\Logs\MajorChange;
use App\Permission;
use App\RegExp;
use App\Tags;
use App\Time;
use App\UserPrefs;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException as ElasticNoNodesAvailableException;
use HtmlGenerator\HtmlTag;
use SeinopSys\RGBAColor;

/**
 * @property int                 $id
 * @property int                 $order
 * @property string              $label
 * @property string              $notes_src
 * @property string              $notes_rend
 * @property string|null         $owner_id
 * @property DateTime            $added
 * @property DateTime            $last_cleared
 * @property bool                $ishuman
 * @property bool                $private
 * @property string              $token
 * @property Cutiemark[]         $cutiemarks          (Via relations)
 * @property ColorGroup[]        $color_groups        (Via relations)
 * @property User|null           $owner               (Via relations)
 * @property RelatedAppearance[] $related_appearances (Via relations)
 * @property Color[]             $preview_colors      (Via relations)
 * @property Tag[]               $tags                (Via relations)
 * @property Tagged[]            $tagged              (Via relations)
 * @property MajorChange[]       $major_changes       (Via relations)
 * @method static Appearance[] find_by_sql($sql, $values = null)
 * @method static Appearance find_by_owner_id_and_label(string $uuid, string $label)
 * @method static Appearance find_by_ishuman_and_label($ishuman, string $label)
 * @method static Appearance|Appearance[] find(...$args)
 * @method static Appearance[] all(...$args)
 */
class Appearance extends NSModel implements LinkableInterface {
	public static $table_name = 'appearances';

	public static $has_many = [
		['cutiemarks', 'foreign_key' => 'appearance_id', 'order' => 'facing asc'],
		['tags', 'through' => 'tagged'],
		['tagged', 'class' => 'Tagged'],
		['color_groups', 'order' => '"order" asc, id asc'],
		['related_appearances', 'class' => 'RelatedAppearance', 'foreign_key' => 'source_id', 'order' => 'target_id asc'],
		['major_changes', 'class' => 'Logs\MajorChange', 'order' => 'entryid desc'],
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
		$arr = DB::$instance->setModel(Color::class)->query(
			'SELECT c.hex FROM colors c
			LEFT JOIN color_groups cg ON c.group_id = cg.id
			WHERE cg.appearance_id = ? AND c.hex IS NOT NULL
			ORDER BY cg."order" ASC, c."order" ASC
			LIMIT 4', [$this->id]);

		if (!empty($arr))
			usort($arr, function(Color $a, Color $b){
				/** @noinspection NullPointerExceptionInspection */
				return RGBAColor::parse($b->hex)->yiq() <=> RGBAColor::parse($a->hex)->yiq();
			});

		return $arr;
	}

	public function getPalettePath(){
		return FSPATH."cg_render/appearance/{$this->id}/palette.png";
	}

	/**
	 * Replaces non-alphanumeric characters in the appearance label with dashes
	 *
	 * @return string
	 */
	public function getURLSafeLabel():string {
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

	public const SPRITE_SIZES = [
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
			return "/cg/v/{$this->id}s.png?s=$size&t=".filemtime($fpath).(!empty($_GET['token']) ? "&token={$_GET['token']}" : '');
		return $fallback;
	}

	/**
	 * Returns the HTML for sprite images
	 *
	 * @param bool $canUpload
	 * @param User $user
	 *
	 * @return string
	 */
	public function getSpriteHTML(bool $canUpload, ?User $user = null):string {
		if (Auth::$signed_in && $this->owner_id === Auth::$user->id && !UserPrefs::get('a_pcgsprite'))
			$canUpload = false;

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
		$notes = preg_replace(new RegExp('(\s)(&gt;&gt;(\d+))(\D|$)'),"$1<a href='https://derpibooru.org/$3'>$2</a>$4",$notes);
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
		$cms = Cutiemark::count(['appearance_id' => $this->id]);
		if ($cms > 0)
			$notes .= '<span>'.CoreUtils::makePlural('Cutie mark', $cms).' available</span>';
		return $wrap ? "<div class='notes'>$notes</div>" : $notes;
	}

	/**
	 * Returns the markup of the color list for a specific appearance
	 *
	 * @param bool       $wrap
	 * @param bool       $colon
	 * @param bool       $colorNames
	 *
	 * @return string
	 */
	public function getColorsHTML(bool $wrap = WRAP, $colon = true, $colorNames = false){
		if ($placehold = $this->getPendingPlaceholder())
			return $placehold;

		$ColorGroups = $this->color_groups;
		$AllColors = CGUtils::getColorsForEach($ColorGroups);

		$HTML = '';
		if (!empty($ColorGroups)) foreach ($ColorGroups as $cg)
			$HTML .= $cg->getHTML($AllColors, WRAP, $colon, $colorNames);

		return $wrap ? "<ul class='colors'>$HTML</ul>" : $HTML;
	}

	/**
	 * Return the markup of a set of tags belonging to a specific appearance
	 *
	 * @param bool $wrap
	 *
	 * @return string
	 */
	public function getTagsHTML(bool $wrap = WRAP):string {
		$isStaff = Permission::sufficient('staff');
		$Tags = Tags::getFor($this->id, null, $isStaff);

		$HTML = '';
		if (!empty($Tags)) foreach ($Tags as $t)
			$HTML .= $t->to_html($this->ishuman);

		return $wrap ? "<div class='tags'>$HTML</div>" : $HTML;
	}

	/**
	 * Returns the markup for the time of last update displayed under an appaerance
	 *
	 * @param bool $wrap
	 *
	 * @return string
	 */
	public function getUpdatesHTML($wrap = WRAP){
		$update = MajorChange::get($this->id, null, MOST_RECENT);
		if (!empty($update)){
			$update = 'Last updated '.Time::tag($update->log->timestamp);
		}
		else {
			if (!Permission::sufficient('staff'))
				return '';
			$update = '';
		}
		return $wrap ? "<div class='update'>$update</div>" : $update;
	}

	public function getChangesHTML(bool $wrap = WRAP):string {
		$HTML = '';
		if (\count($this->major_changes) === 0)
			return $HTML;

			$isStaff = Permission::sufficient('staff');
		foreach ($this->major_changes as $change){
			$li = CoreUtils::escapeHTML($change->reason).' &ndash; '.Time::tag($change->log->timestamp);
			if ($isStaff)
				$li .= ' by '.$change->log->actor->toAnchor();
			$HTML .= "<li>$li</li>";
		}
		if (!$wrap)
			return $HTML;

		return <<<HTML
<section class="major-changes">
	<h2><span class='typcn typcn-warning'></span>List of major changes</h2>
	<ul>$HTML</ul>
</section>
HTML;
	}

	/**
	 * Returns the HTML of the "Linked to from # episodes" section of appearance pages
	 *
	 * @param bool       $allowMovies
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function getRelatedEpisodesHTML($allowMovies = false){
		/** @var $EpTagsOnAppearance Tag[] */
		$EpTagsOnAppearance = DB::$instance->setModel(Tag::class)->query(
			"SELECT t.name
			FROM tagged tg
			LEFT JOIN tags t ON tg.tag_id = t.id
			WHERE tg.appearance_id = ? AND  t.type = 'ep'
			ORDER BY t.name", [$this->id]);

		if (empty($EpTagsOnAppearance))
			return '';

		$List = [];
		foreach ($EpTagsOnAppearance as $tag){
			$name = strtoupper($tag->name);
			$EpData = Episode::parseID($name);
			$Ep = Episodes::getActual($EpData['season'], $EpData['episode'], $allowMovies);
			$List[] = (
				empty($Ep)
				? CGUtils::expandEpisodeTagName($name)
				: $Ep->toAnchor($Ep->formatTitle())
			);
		}
		$List = implode(', ',$List);
		$N_episodes = CoreUtils::makePlural($this->ishuman ? 'movie' : 'episode', \count($EpTagsOnAppearance),PREPEND_NUMBER);
		$hide = '';

		return <<<HTML
	<section id="ep-appearances" $hide>
		<h2><span class='typcn typcn-video'></span>Linked to from $N_episodes</h2>
		<p>$List</p>
	</section>
HTML;
	}

	public function veirfyToken(?string $token = null){
		if ($token === null){
			if (!isset($_GET['token']))
				return false;
			$token = $_GET['token'];
		}

		return hash_equals($this->token, $token);
	}

	public function isPrivate(bool $ignoreStaff = false):bool {
		$isPrivate = !empty($this->private);
		if (!$ignoreStaff && (
			Permission::sufficient('staff')
			|| (Auth::$signed_in ? $this->owner_id === Auth::$user->id : false)
			|| ($this->owner_id !== null && $this->veirfyToken())
		))
			$isPrivate = false;
		return $isPrivate;
	}

	/**
	 * As of right now this simply changes single quotes in the label to their "smart" version
	 *
	 * @deprecated Just use the label property
	 *
	 * @return string
	 */
	public function processLabel():string {
		return $this->label;
	}

	/**
	 * Returns the HTML for the placeholder which is displayed in place of the color group
	 *  to anyone without edit access while the appearance is private
	 *
	 * @return string
	 */
	public function getPendingPlaceholder():string {
		return $this->isPrivate() ? "<div class='colors-pending'><span class='typcn typcn-time'></span> ".($this->last_cleared !== null ? 'This appearance is currently undergoing maintenance and will be available again shortly &mdash; '.Time::tag($this->last_cleared) :  'This appearance will be finished soon, please check back later &mdash; '.Time::tag($this->added)).'</div>' : '';
	}

	/**
	 * Retruns preview image link
	 *
	 * @see CGUtils::renderPreviewSVG()
	 * @return string
	 */
	public function getPreviewURL():string {
		$path = str_replace('#',$this->id,CGUtils::PREVIEW_SVG_PATH);
		return "/cg/v/{$this->id}p.svg?t=".CoreUtils::filemtime($path);
	}

	public function getPreviewHTML():string {
		$locked = $this->owner_id !== null && $this->private;

		if ($this->isPrivate(true))
			$preview = "<span class='typcn typcn-".($locked?'lock-closed':'time').' color-'.($locked?'orange':'darkblue')."'></span> ";
		else {
			$preview = $this->getPreviewURL();
			$preview = "<img src='$preview' class='preview' alt=''>";
		}

		return $preview;
	}

	/**
	 * @see CGUtils::renderCMFacingSVG()
	 *
	 * @param string $facing
	 * @param bool   $ts
	 *
	 * @return string
	 */
	public function getFacingSVGURL(?string $facing = null, bool $ts = true){
		if ($facing === null)
			$facing = 'left';
		$path = str_replace(['#','@'],[$this->id, $facing],CGUtils::CMDIR_SVG_PATH);
		return "/cg/v/{$this->id}f.svg?facing=$facing".
			($ts?'&t='.CoreUtils::filemtime($path):'').
			(!empty($_GET['token']) ? "&token={$_GET['token']}" : '');
	}

	public function toURL():string {
		$safeLabel = $this->getURLSafeLabel();
		$pcg = $this->owner_id !== null;
		$owner = $pcg ? '/@'.User::find($this->owner_id)->name : '';
		$guide = $pcg ? '' : ($this->ishuman ? 'eqg' : 'pony').'/';
		return "$owner/cg/{$guide}v/{$this->id}-$safeLabel";
	}

	public function toAnchor():string {
		return "<a href='{$this->toURL()}'>{$this->label}</a>";
	}

	public function toAnchorWithPreview():string {
		return "<a href='{$this->toURL()}'>{$this->getPreviewHTML()}<span>{$this->label}</span></a>";
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
		if (\count($this->related_appearances) === 0)
			return $LINKS;
		foreach ($this->related_appearances as $r){
			if (!$r->target->hidden())
				$LINKS .= '<li>'.$r->target->toAnchorWithPreview().'</li>';
		}
		if (empty($LINKS))
			return $LINKS;
		return "<section class='related'><h2>Related appearances</h2><ul>$LINKS</ul></section>";
	}

	/**
	 * Re-index the appearance in ElasticSearch
	 */
	public function reindex(){
		// We don't want to index private appearances
		if ($this->owner_id !== null)
			return;

		$this->updateIndex();
	}

	public function updateIndex(){
		try {
			CoreUtils::elasticClient()->update($this->toElasticArray(false, true));
		}
		catch (ElasticNoNodesAvailableException $e){
			CoreUtils::error_log('ElasticSearch server was down when server attempted to index appearance '.$this->id);
		}
	}

	public function getElasticMeta(){
		return array_merge(CGUtils::ELASTIC_BASE,[
			'type' => 'entry',
			'id' => $this->id,
		]);
	}

	public function getElasticBody(){
		$tags = Tags::getFor($this->id, null, true, true);
		foreach ($tags as $k => $tag)
			$tags[$k] = $tag->name;
		return [
			'label' => $this->label,
			'order' => $this->order,
			'private' => $this->private,
			'ishuman' => $this->ishuman,
			'tags' =>  $tags,
		];
	}

	public function toElasticArray(bool $no_body = false, bool $update = false):array {
		$params = $this->getElasticMeta();
		if ($no_body)
			return $params;
		$params['body'] = $this->getElasticBody();
		if ($update)
			$params['body'] = [
				'doc' => $params['body'],
				'upsert' => $params['body'],
			];
		return $params;
	}

	public function clearRelations(){
		RelatedAppearance::delete_all(['conditions' => [
			'source_id = :id OR target_id = :id',
			['id' => $this->id],
		]]);
	}

	public const STATIC_RELEVANT_COLORS = [
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
	];
	public function getSpriteRelevantColors():array {
		$Colors = [];
		foreach ([0, $this->id] as $AppearanceID){
			$ColorGroups = ColorGroup::find_all_by_appearance_id($AppearanceID);
			uasort($ColorGroups, function(ColorGroup $a, ColorGroup $b){
				return $a->order <=> $b->order;
			});
			$SortedColorGroups = [];
			foreach ($ColorGroups as $cg)
				$SortedColorGroups[$cg->id] = $cg;

			$AllColors = CGUtils::getColorsForEach($ColorGroups);
			if ($AllColors !== null && \count($AllColors) > 0){
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
		}
		if ($this->owner_id === null)
			$Colors = array_merge($Colors, self::STATIC_RELEVANT_COLORS);

		return [ $Colors, $ColorGroups ?? null, $AllColors ?? null];
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

	/**
	 * @param bool $treatHexNullAsEmpty
	 *
	 * @return bool
	 */
	public function hasColors(bool $treatHexNullAsEmpty = false):bool {
		$hexnull = $treatHexNullAsEmpty?' AND hex IS NOT NULL':'';
		return (DB::$instance->querySingle(
			"SELECT count(*) as cnt FROM colors
			WHERE group_id IN (SELECT group_id FROM color_groups WHERE appearance_id = ?)$hexnull", [$this->id])['cnt'] ?? 0) > 0;
	}

	public const
		PONY_TEMPLATE = [
			'Coat' => [
				'Outline',
				'Fill',
				'Shadow Outline',
				'Shadow Fill',
			],
			'Mane & Tail' => [
				'Outline',
				'Fill',
			],
			'Iris' => [
				'Gradient Top',
				'Gradient Middle',
				'Gradient Bottom',
				'Highlight Top',
				'Highlight Bottom',
			],
			'Cutie Mark' => [
				'Fill 1',
				'Fill 2',
			],
			'Magic' => [
				'Aura',
			],
		],
		HUMAN_TEMPLATE = [
			'Skin' => [
				'Outline',
				'Fill',
			],
			'Hair' => [
				'Outline',
				'Fill',
			],
			'Eyes' => [
				'Gradient Top',
				'Gradient Middle',
				'Gradient Bottom',
				'Highlight Top',
				'Highlight Bottom',
				'Eyebrows',
			],
		];

	/**
	 * Apply pre-defined template to an appearance
	 *
	 * @return self
	 */
	public function applyTemplate():self {
		if (ColorGroup::exists([ 'conditions' => ['appearance_id = ?', $this->id] ]))
			throw new \RuntimeException('Template can only be applied to empty appearances');

		/** @var $Scheme string[][] */
		$Scheme = $this->ishuman
			? self::HUMAN_TEMPLATE
			: self::PONY_TEMPLATE;

		$cgi = 1;
		foreach ($Scheme as $GroupName => $ColorNames){
			/** @var $Group ColorGroup */
			$Group = ColorGroup::create([
				'appearance_id' => $this->id,
				'label' => $GroupName,
				'order' => $cgi++,
			]);
			$GroupID = $Group->id;
			if (!$GroupID)
				throw new \RuntimeException(rtrim("Color group \"$GroupName\" could not be created: ".DB::$instance->getLastError(), ': '));

			$ci = 1;
			foreach ($ColorNames as $label){
				if (!(new Color([
					'group_id' => $GroupID,
					'label' => $label,
					'order' => $ci++,
				]))->save()) throw new \RuntimeException(rtrim("Color \"$label\" could not be added: ".DB::$instance->getLastError(), ': '));
			}
		}

		return $this;
	}

	public const CLEAR_ALL = [
		self::CLEAR_PALETTE,
		self::CLEAR_PREVIEW,
		self::CLEAR_CM,
		self::CLEAR_CMDIR,
		self::CLEAR_SPRITE,
		self::CLEAR_SPRITE_SVG,
	];
	public const
		CLEAR_PALETTE     = 'palette.png',
		CLEAR_PREVIEW     = 'preview.svg',
		CLEAR_CM          = '&cutiemark',
		CLEAR_CMDIR       = 'cmdir-*.svg',
		CLEAR_SPRITE      = 'sprite.png',
		CLEAR_SPRITE_SVG  = 'sprite.svg',
		CLEAR_SPRITE_MAP  = 'linedata.json.gz';

	/**
	 * Deletes rendered images of an appearance (forcing its re-generation)
	 *
	 * @param array $which
	 *
	 * @return bool
	 */
	public function clearRenderedImages(array $which = self::CLEAR_ALL):bool {
		$success = [];
		$clearCMPos = array_search(self::CLEAR_CM, $which, true);
		if ($clearCMPos !== false){
			array_splice($which, $clearCMPos, 1);
			foreach ($this->cutiemarks as $cm){
				$fpath = $cm->getRenderedFilePath();
				$success[] = CoreUtils::deleteFile($fpath);
			}
		}
		foreach ($which as $suffix){
			$path = FSPATH."cg_render/appearance/{$this->id}/$suffix";
			if (strpos($path, '*') === false)
				$success[] = CoreUtils::deleteFile($path);
			else {
				foreach (glob($path) as $file)
					$success[] = CoreUtils::deleteFile($file);
			}
		}
		return !\in_array(false, $success, true);
	}

	public const DEFAULT_COLOR_MAPPING = [
		'Coat Outline' => '#0D0D0D',
		'Coat Shadow Outline' => '#000000',
		'Coat Fill' => '#2B2B2B',
		'Coat Shadow Fill' => '#171717',
		'Mane & Tail Outline' => '#333333',
		'Mane & Tail Fill' => '#5E5E5E',
	];
	public function getColorMapping($DefaultColorMapping){
		$Colors = DB::$instance->query(
			'SELECT cg.label as cglabel, c.label as clabel, c.hex
			FROM color_groups cg
			LEFT JOIN colors c on c.group_id = cg.id
			WHERE cg.appearance_id = ?
			ORDER BY cg.order ASC, c.label ASC', [$this->id]);

		$ColorMapping = [];
		foreach ($Colors as $row){
			$cglabel = preg_replace(new RegExp('^(Costume|Dress)$'),'Coat',$row['cglabel']);
			$cglabel = preg_replace(new RegExp('^(Coat|Mane & Tail) \([^)]+\)$'),'$1',$cglabel);
			$eye = $row['cglabel'] === 'Iris';
			$colorlabel = preg_replace(new RegExp('^(?:(?:(?:Purple|Yellow|Red)\s)?(?:Main|First|Normal'.(!$eye?'|Gradient(?:\s(?:Light|(?:\d+\s)?(?:Top|Botom)))?\s':'').'))?(.+?)(?:\s\d+)?(?:/.*)?$'),'$1', $row['clabel']);
			$label = "$cglabel $colorlabel";
			if (isset($DefaultColorMapping[$label]) && !isset($ColorMapping[$label]))
				$ColorMapping[$label] = $row['hex'];
		}
		if (!isset($ColorMapping['Coat Shadow Outline']) && isset($ColorMapping['Coat Outline']))
			$ColorMapping['Coat Shadow Outline'] = $ColorMapping['Coat Outline'];
		if (!isset($ColorMapping['Coat Shadow Fill']) && isset($ColorMapping['Coat Fill']))
			$ColorMapping['Coat Shadow Fill'] = $ColorMapping['Coat Fill'];

		return $ColorMapping;
	}

	public function render_notes(){
		if ($this->notes_src === null)
			$this->notes_rend = null;
		else $this->notes_rend = self::_processNotes($this->notes_src);
	}

	public function hidden(bool $ignoreStaff = false):bool {
		return $this->owner_id !== null && $this->private && $this->isPrivate($ignoreStaff);
	}

	public function getTagsAsText(){
		$tags = [];
		foreach (Tags::getFor($this->id, null, Permission::sufficient('staff')) as $tag)
			$tags[] = $tag->name;
		return implode(', ', $tags);
	}

	public function processTagChanges(string $new_tags, bool $eqg){
		$new = [];
		foreach (explode(',', $new_tags) as $new_tag)
			$new[CoreUtils::trim($new_tag)] = true;
		$old = [];
		$old_tags = DB::$instance->query(
			'SELECT t.name as name, t.id as id FROM tags t
			LEFT JOIN tagged tg ON t.id = tg.tag_id
			WHERE tg.appearance_id = ?', [$this->id]);

		/** @var $removed int[] */
		$removed = [];
		foreach ($old_tags as $row){
			$name = $row['name'];
			if (!isset($new[$name]))
				$removed[] = $row['id'];
			else unset($new[$name]);
		}
		if (!empty($removed)){
			DB::$instance->where('tag_id', $removed)->where('appearance_id', $this->id)->delete(Tagged::$table_name);
			foreach ($removed as $tag_id)
				TagChange::record(false, $tag_id, $this->id);
		}

		foreach ($new as $name => $_){
			$_POST['tag_name'] = CoreUtils::trim($name);
			if (empty($_POST['tag_name']))
				continue;

			$tag_name = CGUtils::validateTagName('tag_name');
			$tag_type = null;

			$episodeTagCheck = CGUtils::normalizeEpisodeTagName($tag_name);
			if ($episodeTagCheck !== false){
				$tag_name = $episodeTagCheck;
				$tag_type = 'ep';
			}

			$tag = Tags::getActual($tag_name, 'name');
			if (empty($tag))
				$tag = Tag::create([
					'name' => $tag_name,
					'type' => $tag_type,
				]);

			Tagged::make($tag->id, $this->id)->save();
			TagChange::record(true, $tag->id, $this->id);
			$tag->updateUses();
			if (!empty(CGUtils::GROUP_TAG_IDS_ASSOC[$eqg?'eqg':'pony'][$tag->id]))
				Appearances::getSortReorder($eqg);
		}
	}
}
