<?php

namespace App;

use Elasticsearch\Common\Exceptions\Missing404Exception as ElasticMissing404Exception;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException as ElasticNoNodesAvailableException;

class Appearances {
	/**
	 * @param bool      $EQG
	 * @param int|int[] $limit
	 * @param  string   $cols
	 *
	 * @return array
	 */
	static function get($EQG, $limit = null, $cols = '*'){
		global $CGDb;

		self::_order();
		if (isset($EQG))
			$CGDb->where('ishuman', $EQG)->where('id',0,'!=');
		return $CGDb->get('appearances', $limit, $cols);
	}

	/**
	 * Order appearances
	 *
	 * @param string $dir
	 */
	private static function _order($dir = 'ASC'){
		global $CGDb;
		$CGDb
			->orderByLiteral('CASE WHEN "order" IS NULL THEN 1 ELSE 0 END', $dir)
			->orderBy('"order"', $dir)
			->orderBy('id', $dir);
	}

	/**
	 * @param array $Appearances
	 * @param bool  $wrap
	 *
	 * @return string
	 */
	static function getHTML($Appearances, $wrap = WRAP){
		global $CGDb, $_MSG, $Search;

		$HTML = '';
		if (!empty($Appearances)) foreach ($Appearances as $Appearance){
			$Appearance['label'] = CoreUtils::escapeHTML($Appearance['label']);

			$img = self::getSpriteHTML($Appearance);
			$updates = self::getUpdatesHTML($Appearance['id']);
			$notes = self::getNotesHTML($Appearance);
			$tags = $Appearance['id'] ? self::getTagsHTML($Appearance['id'], true, $Search) : '';
			$colors = self::getColorsHTML($Appearance);
			$eqgp = $Appearance['ishuman'] ? 'eqg/' : '';

			$RenderPath = FSPATH."cg_render/{$Appearance['id']}.png";
			$FileModTime = '?t='.(file_exists($RenderPath) ? filemtime($RenderPath) : time());
			$Actions = "<a class='btn typcn typcn-image darkblue' title='View as PNG' href='/cg/{$eqgp}v/{$Appearance['id']}.png$FileModTime' target='_blank'></a>".
			           "<button class='getswatch typcn typcn-brush teal' title='Download swatch file'></button>";
			if (Permission::sufficient('staff'))
				$Actions .= "<button class='edit typcn typcn-pencil blue' title='Edit'></button>".
				            ($Appearance['id']!==0?"<button class='delete typcn typcn-trash red' title='Delete'></button>":'');
			$safelabel = self::getSafeLabel($Appearance);
			$HTML .= "<li id='p{$Appearance['id']}'>$img<div><strong><a href='/cg/v/{$Appearance['id']}-$safelabel'>{$Appearance['label']}</a>$Actions</strong>$updates$notes$tags$colors</div></li>";
		}
		else {
			if (empty($_MSG))
				$_MSG = "No appearances to show";
			$HTML .= "<div class='notice info align-center'><label>$_MSG</label></div>";
		}

		return $wrap ? "<ul id='list' class='appearance-list'>$HTML</ul>" : $HTML;
	}

	static function isPrivate($Appearance, bool $ignoreStaff = false):bool {
		$isPrivate = !empty($Appearance['private']);
		if (!$ignoreStaff && Permission::sufficient('staff'))
			$isPrivate = false;
		return $isPrivate;
	}

	/**
	 * @param array $Appearance
	 *
	 * @return string
	 */
	static function getPendingPlaceholderFor($Appearance):string {
		return self::isPrivate($Appearance) ? "<div class='colors-pending'><span class='typcn typcn-time'></span> This appearance will be finished soon, please check back later &mdash; ".Time::tag($Appearance['added']).'</div>' : false;
	}

	/**
	 * Returns the markup of the color list for a specific appearance
	 *
	 * @param array $Appearance
	 * @param bool  $wrap
	 * @param bool  $colon
	 * @param bool  $colorNames
	 *
	 * @return string
	 */
	static function getColorsHTML($Appearance, bool $wrap = WRAP, $colon = true, $colorNames = false){
		global $CGDb;

		if ($placehold = self::getPendingPlaceholderFor($Appearance))
			return $placehold;

		$ColorGroups = ColorGroups::get($Appearance['id']);
		$AllColors = ColorGroups::getColorsForEach($ColorGroups);

		$HTML = '';
		if (!empty($ColorGroups)) foreach ($ColorGroups as $cg)
			$HTML .= ColorGroups::getHTML($cg, $AllColors, WRAP, $colon, $colorNames);

		return $wrap ? "<ul class='colors'>$HTML</ul>" : $HTML;
	}

	/**
	 * Return the markup of a set of tags belonging to a specific pony
	 *
	 * @param int         $PonyID
	 * @param bool        $wrap
	 * @param string|null $Search
	 *
	 * @return string
	 */
	static function getTagsHTML($PonyID, $wrap = WRAP, $Search = null){
		global $CGDb;

		$Tags = Tags::getFor($PonyID, null, Permission::sufficient('staff'));

		$HTML = '';
		if (Permission::sufficient('staff') && $PonyID !== 0)
			$HTML .= "<input type='text' class='addtag tag' placeholder='Enter tag' pattern='".TAG_NAME_PATTERN."' maxlength='30' required>";
		$HideSynon = Permission::sufficient('staff') && UserPrefs::get('cg_hidesynon');
		if (!empty($Tags)) foreach ($Tags as $i => $t){
			$isSynon = !empty($t['synonym_of']);
			$searchedFor = !empty($Search) && in_array($t['tid'],$Search['orig_tid']);
			if ($isSynon && $HideSynon && !$searchedFor)
				continue;
			$class = " class='tag id-{$t['tid']}".($isSynon?' synonym':'').(!empty($t['type'])?' typ-'.$t['type']:'')."'";
			$title = !empty($t['title']) ? " title='".CoreUtils::aposEncode($t['title'])."'" : '';
			if ($searchedFor || (Permission::insufficient('staff') && !empty($Search['tid_assoc'][$t['tid']])))
				$t['name'] = "<mark>{$t['name']}</mark>";
			$syn_of = $isSynon ? " data-syn-of='{$t['synonym_of']}'" : '';
			$HTML .= "<span$class$title$syn_of>{$t['name']}</span>";
		}

		return $wrap ? "<div class='tags'>$HTML</div>" : $HTML;
	}

	/**
	 * Get the notes for a specific appearance
	 *
	 * @param array $Appearance
	 * @param bool  $wrap
	 * @param bool  $cmLink
	 *
	 * @return string
	 */
	static function getNotesHTML($Appearance, $wrap = WRAP, $cmLink = true){
		global $EPISODE_ID_REGEX;

		$hasNotes = !empty($Appearance['notes']);
		$hasCM = !empty($Appearance['cm_favme']) && $cmLink !== NOTE_TEXT_ONLY;
		if ($hasNotes || $hasCM){
			$notes = '';
			if ($hasNotes){
				$Appearance['notes'] = preg_replace_callback('/'.EPISODE_ID_PATTERN.'/',function($a){
					$Ep = Episodes::getActual((int) $a[1], (int) $a[2]);
					return !empty($Ep)
						? "<a href='{$Ep->formatURL()}'>".CoreUtils::aposEncode($Ep->formatTitle(AS_ARRAY,'title'))."</a>"
						: "<strong>{$a[0]}</strong>";
				},$Appearance['notes']);
				$Appearance['notes'] = preg_replace_callback('/'.MOVIE_ID_PATTERN.'/',function($a){
					$Ep = Episodes::getActual(0, (int) $a[1], true);
					return !empty($Ep)
						? "<a href='{$Ep->formatURL()}'>".CoreUtils::aposEncode($Ep->formatTitle(AS_ARRAY,'title'))."</a>"
						: "<strong>{$a[0]}</strong>";
				},$Appearance['notes']);
				$Appearance['notes'] = preg_replace_callback('/(?:^|[^\\\\])\K(?:#(\d+))\b/',function($a){
					global $CGDb;
					$Appearance = $CGDb->where('id', $a[1])->getOne('appearances');
					return (
						!empty($Appearance)
						? "<a href='/cg/v/{$Appearance['id']}'>{$Appearance['label']}</a>"
						: "$a[0]"
					);
				},$Appearance['notes']);
				$Appearance['notes'] = str_replace('\#', '#', $Appearance['notes']);
				$notes = '<span>'.nl2br($Appearance['notes']).'</span>';
			}
			if ($hasCM){
				$dir = '';
				if (isset($Appearance['cm_dir'])){
					$head_to_tail = $Appearance['cm_dir'] === CM_DIR_HEAD_TO_TAIL;
					$CMPreviewUrl = self::getCMPreviewURL($Appearance);
					$dir = ' <span class="cm-direction" data-cm-preview="'.$CMPreviewUrl.'" data-cm-dir="'.($head_to_tail ? 'ht' : 'th').'"><span class="typcn typcn-info-large"></span> '.($head_to_tail ? 'Head-Tail' : 'Tail-Head').' orientation</span>';
				}
				$notes .= "<a href='http://fav.me/{$Appearance['cm_favme']}'><span>Cutie Mark</span>$dir</a>";
			}
		}
		else {
			if (!Permission::sufficient('staff')) return '';
			$notes = '';
		}
		return $wrap ? "<div class='notes'>$notes</div>" : $notes;
	}

	/**
	 * Get sprite URL for an appearance
	 *
	 * @param int    $AppearanceID
	 * @param string $fallback
	 *
	 * @return string
	 */
	static function getSpriteURL(int $AppearanceID, string $fallback = ''):string {
		$fpath = SPRITE_PATH."$AppearanceID.png";
		if (file_exists($fpath))
			return "/cg/v/{$AppearanceID}s.png?t=".filemtime($fpath);
		return $fallback;
	}

	/**
	 * Returns the HTML for sprite images
	 *
	 * @param array $Appearance
	 *
	 * @return string
	 */
	static function getSpriteHTML($Appearance){
		$imgPth = self::getSpriteURL($Appearance['id']);
		if (!empty($imgPth)){
			$img = "<a href='$imgPth' target='_blank' title='Open image in new tab'><img src='$imgPth' alt='".CoreUtils::aposEncode($Appearance['label'])."'></a>";
			if (Permission::sufficient('staff'))
				$img = "<div class='upload-wrap'>$img</div>";
		}
		else if (Permission::sufficient('staff'))
			$img = "<div class='upload-wrap'><a><img src='/img/blank-pixel.png'></a></div>";
		else return '';

		return "<div class='sprite'>$img</div>";
	}

	/**
	 * Returns the markup for the time of last update displayed under an appaerance
	 *
	 * @param int  $PonyID
	 * @param bool $wrap
	 *
	 * @return string
	 */
	static function getUpdatesHTML($PonyID, $wrap = WRAP){
		global $Database;

		$update = Updates::get($PonyID, MOST_RECENT);
		if (!empty($update)){
			$update = "Last updated ".Time::tag($update['timestamp']);
		}
		else {
			if (!Permission::sufficient('staff')) return '';
			$update = '';
		}
		return $wrap ? "<div class='update'>$update</div>" : $update;
	}

	/**
	 * Sort appearances based on tags
	 *
	 * @param array $Appearances
	 * @param bool  $simpleArray
	 *
	 * @return array
	 */
	static function sort($Appearances, $simpleArray = false){
		global $CGDb;
		$GroupTagIDs = array_keys(CGUtils::GROUP_TAG_IDS_ASSOC);
		$Sorted = array();
		$Tagged = array();
		foreach ($CGDb->where('tid IN ('.implode(',',$GroupTagIDs).')')->orderBy('ponyid','ASC')->get('tagged') as $row)
			$Tagged[$row['ponyid']][] = $row['tid'];
		foreach ($Appearances as $p){
			if (!empty($Tagged[$p['id']])){
				if (count($Tagged[$p['id']]) > 1)
					usort($Tagged[$p['id']],function($a,$b) use ($GroupTagIDs){
						return array_search($a, $GroupTagIDs) - array_search($b, $GroupTagIDs);
					});
				$tid = $Tagged[$p['id']][0];
			}
			else $tid = -1;
			$Sorted[$tid][] = $p;
		}
		if ($simpleArray){
			$idArray = array();
			foreach (CGUtils::GROUP_TAG_IDS_ASSOC as $Category => $CategoryName){
				if (empty($Sorted[$Category]))
					continue;
				foreach ($Sorted[$Category] as $p)
					$idArray[] = $p['id'];
			}
			return $idArray;
		}
		else return $Sorted;
	}

	/**
	 * @param string|int[] $ids
	 */
	static function reorder($ids){
		global $CGDb;
		if (empty($ids))
			return;

		$elastiClient = CoreUtils::elasticClient();
		$list = is_string($ids) ? explode(',', $ids) : $ids;
		foreach ($list as $i => $id){
			$order = $i+1;
			if (!$CGDb->where('id', $id)->update('appearances', array('order' => $order)))
				Response::fail("Updating appearance #$id failed, process halted");

			$elastiClient->update(array_merge(self::getElasticMeta(['id' => $id]), [
				'body' => [ 'doc' => ['order' => $order] ],
			]));
		}
	}


	/**
	 * @param bool $EQG
	 */
	static function getSortReorder($EQG){
		if ($EQG)
			return;
		self::reorder(self::sort(self::get($EQG,null,'id'), SIMPLE_ARRAY));
	}

	/**
	 * Apply pre-defined template to an appearance
	 * $EQG controls whether to apply EQG or Pony template
	 *
	 * @param int  $AppearanceID
	 * @param bool $EQG
	 *
	 * @throws \Exception
	 */
	static function applyTemplate($AppearanceID, $EQG){
		global $CGDb, $Color;

		if (empty($AppearanceID) || !is_numeric($AppearanceID))
			throw new \Exception('Incorrect value for $PonyID while applying template');

		if ($CGDb->where('ponyid', $AppearanceID)->has('colorgroups'))
			throw new \Exception('Template can only be applied to empty appearances');

		$Scheme = $EQG
			? array(
				'Skin' => array(
					'Outline',
					'Fill',
				),
				'Hair' => array(
					'Outline',
					'Fill',
				),
				'Eyes' => array(
					'Gradient Top',
					'Gradient Middle',
					'Gradient Bottom',
					'Highlight Top',
					'Highlight Bottom',
					'Eyebrows',
				),
			)
			: array(
				'Coat' => array(
					'Outline',
					'Fill',
					'Shadow Outline',
					'Shadow Fill',
				),
				'Mane & Tail' => array(
					'Outline',
					'Fill',
				),
				'Iris' => array(
					'Gradient Top',
					'Gradient Middle',
					'Gradient Bottom',
					'Highlight Top',
					'Highlight Bottom',
				),
				'Cutie Mark' => array(
					'Fill 1',
					'Fill 2',
				),
				'Magic' => array(
					'Aura',
				),
			);

		$cgi = 0;
		$ci = 0;
		foreach ($Scheme as $GroupName => $ColorNames){
			$GroupID = $CGDb->insert('colorgroups',array(
				'ponyid' => $AppearanceID,
				'label' => $GroupName,
				'order' => $cgi++,
			), 'groupid');
			if (!$GroupID)
				throw new \Exception(rtrim("Color group \"$GroupName\" could not be created: ".$CGDb->getLastError()), ': ');

			foreach ($ColorNames as $label){
				if (!$CGDb->insert('colors',array(
					'groupid' => $GroupID,
					'label' => $label,
					'order' => $ci++,
				))) throw new \Exception(rtrim("Color \"$label\" could not be added: ".$CGDb->getLastError()), ': ');
			}
		}
	}

	/**
	 * Returns the HTML of the "Appears in # episodes" section of appearance pages
	 *
	 * @param array $Appearance
	 * @param bool  $allowMovies
	 *
	 * @return string
	 */
	static function getRelatedEpisodesHTML($Appearance, $allowMovies = false){
		global $CGDb;

		$EpTagsOnAppearance = $CGDb->rawQuery(
			"SELECT t.tid
			FROM tagged tt
			LEFT JOIN tags t ON tt.tid = t.tid
			WHERE tt.ponyid = ? &&  t.type = 'ep'",array($Appearance['id']));

		if (!empty($EpTagsOnAppearance)){
			foreach ($EpTagsOnAppearance as $k => $row)
				$EpTagsOnAppearance[$k] = $row['tid'];

			$EpAppearances = $CGDb->rawQuery("SELECT DISTINCT name FROM tags WHERE tid IN (".implode(',',$EpTagsOnAppearance).") ORDER BY name");
			if (empty($EpAppearances))
				return '';

			$List = '';
			foreach ($EpAppearances as $tag){
				$name = strtoupper($tag['name']);
				$EpData = Episodes::parseID($name);
				$Ep = Episodes::getActual($EpData['season'], $EpData['episode'], $allowMovies);
				$List .= (
					empty($Ep)
					? self::expandEpisodeTagName($name)
					: "<a href='{$Ep->formatURL()}'>".$Ep->formatTitle().'</a>'
				).', ';
			}
			$List = rtrim($List, ', ');
			$N_episodes = CoreUtils::makePlural($Appearance['ishuman'] ? 'movie' : 'episode',count($EpAppearances),PREPEND_NUMBER);
			$hide = '';
		}
		else {
			$N_episodes = 'no episodes';
			$List = '';
			$hide = 'style="display:none"';
		}

		return <<<HTML
	<section id="ep-appearances" $hide>
		<h2><span class='typcn typcn-video'></span>Appears in $N_episodes</h2>
		<p>$List</p>
	</section>
HTML;
	}
	/**
	 * Turns "S#E#" into "S0# E0#"
	 *
	 * @param string $tagname
	 *
	 * @return string
	 */
	static function expandEpisodeTagName(string $tagname):string {
		global $EPISODE_ID_REGEX, $MOVIE_ID_REGEX;

		if (preg_match($EPISODE_ID_REGEX, $tagname, $_match))
			return 'S'.CoreUtils::pad($_match[1]).' E'.CoreUtils::pad($_match[2]);
		if (preg_match($MOVIE_ID_REGEX, $tagname, $_match))
			return "Movie #{$_match[1]}";
		return $tagname;
	}

	/**
	 * Retruns CM preview image link
	 *
	 * @param array $Appearance
	 *
	 * @return string
	 */
	static function getCMPreviewURL($Appearance){
		return $Appearance['cm_preview'] ?? DeviantArt::getCachedSubmission($Appearance['cm_favme'])['preview'];
	}

	/**
	 * Retruns CM preview SVG image link (pony butt)
	 *
	 * @param int $AppearanceID
	 *
	 * @return string
	 */
	static function getCMPreviewSVGURL(int $AppearanceID){
		$path = str_replace('#',$AppearanceID,CGUtils::CMDIR_SVG_PATH);
		return "/cg/v/{$AppearanceID}.svg?t=".(file_exists($path) ? filemtime($path) : time());
	}

	/**
	 * Retruns preview image link
	 *
	 * @param array $Appearance
	 *
	 * @return string
	 */
	static function getPreviewURL($Appearance){
		$path = str_replace('#',$Appearance['id'],CGUtils::PREVIEW_SVG_PATH);
		return "/cg/v/{$Appearance['id']}p.svg?t=".(file_exists($path) ? filemtime($path) : time());
	}

	/**
	 * Replaces non-alphanumeric characters in the appearance label with dashes
	 *
	 * @param array $Appearance
	 *
	 * @return string
	 */
	static function getSafeLabel($Appearance){
		return CoreUtils::trim(preg_replace(new RegExp('-+'),'-',preg_replace(new RegExp('[^A-Za-z\d\-]'),'-', $Appearance['label'])),'-');
	}

	static function getRelated(int $AppearanceID){
		global $CGDb;

		return $CGDb->rawQuery(
			/** @lang PostgreSQL */
			"(
				SELECT p.id, p.order, p.label, r.mutual
				FROM appearance_relations r
				LEFT JOIN appearances p ON p.id = r.target
				WHERE r.source = :id
			)
			UNION ALL
			(
				SELECT p.id, p.order, p.label, r.mutual
				FROM appearance_relations r
				LEFT JOIN appearances p ON p.id = r.source
				WHERE r.target = :id && mutual = true
			)
			ORDER BY \"order\"", array(':id' => $AppearanceID));
	}

	static function getRelatedHTML(array $Related):string {
		if (empty($Related))
			return '';
		$LINKS = '';
		foreach ($Related as $p){
			$safeLabel = self::getSafeLabel($p);
			$preview = self::getPreviewURL($p);
			$preview = "<img src='$preview' class='preview'>";
			$LINKS .= "<li><a href='/cg/v/{$p['id']}-$safeLabel'>$preview{$p['label']}</a></li>";
		}
		return "<section class='related'><h2>Related appearances</h2><ul>$LINKS</ul></section>";
	}

	static function validateAppearancePageID(){
		return (new Input('APPEARANCE_PAGE','int',array(
			Input::IS_OPTIONAL => true,
			Input::IN_RANGE => [0,null],
			Input::CUSTOM_ERROR_MESSAGES => array(
				Input::ERROR_RANGE => 'Appearance ID must be greater than or equal to @min'
			)
		)))->out();
	}

	const ELASTIC_COLUMNS = 'id,label,order,ishuman,private';

	static function reindex(){
		global $CGDb;

		$elasticClient = CoreUtils::elasticClient();
		try {
			$elasticClient->indices()->delete(CGUtils::ELASTIC_BASE);
		}
		catch(ElasticMissing404Exception $e){
			$message = JSON::decode($e->getMessage());

			// Eat exception if the index we're re-creating does not exist yet
			if ($message['error']['type'] !== 'index_not_found_exception' || $message['error']['index'] !== CGUtils::ELASTIC_BASE['index'])
				throw $e;
		}
		catch (ElasticNoNodesAvailableException $e){
			Response::fail('Re-index failed, ElasticSearch server is down!');
		}
		$params = array_merge(CGUtils::ELASTIC_BASE, [
			"body" => [
				"mappings" => [
					"entry" => [
						"_all" => [ "enabled" => false  ],
						"properties" => [
							"label" => [
								"type" => "text",
								"analyzer" => "overkill",
							],
							"order" => [ "type" => "integer" ],
							"ishuman" => [ "type" => "boolean" ],
							"private" => [ "type" => "boolean" ],
							"tags" => [
								"type" => "text",
								"analyzer" => "overkill",
							],
						],
					],
				],
				"settings" => [
					"analysis" => [
						"analyzer" => [
							"overkill" => [
								"type" => "custom",
								"tokenizer" => "overkill",
								"filter" => [
									"lowercase"
								]
							],
						],
						"tokenizer" => [
							"overkill" => [
								"type" => "edge_ngram",
								"min_gram" => 2,
								"max_gram" => 6,
								"token_chars" => [
									"letter",
									"digit",
								],
							],
						],
					],
				],
			]
		]);
		$elasticClient->indices()->create(array_merge($params));
		$Appearances = $CGDb->where('id != 0')->get('appearances',null,self::ELASTIC_COLUMNS);

		$params = array('body' => []);
		foreach ($Appearances as $i => $a){
			$meta = self::getElasticMeta($a);
		    $params['body'][] = [
		        'index' => [
		            '_index' => $meta['index'],
		            '_type' => $meta['type'],
		            '_id' => $meta['id'],
		        ]
		    ];

		    $params['body'][] = self::getElasticBody($a);

		    if ($i % 100 == 0) {
		        $elasticClient->bulk($params);
		        $params = ['body' => []];
		    }
		}
		if (!empty($params['body'])) {
	        $elasticClient->bulk($params);
		}

		Response::success('Re-index completed');
	}

	static function updateIndex(int $AppearanceID, string $fields = self::ELASTIC_COLUMNS):array {
		global $CGDb;

		$Appearance = $CGDb->where('id', $AppearanceID)->getOne('appearances', $fields);
		try {
			CoreUtils::elasticClient()->update(self::toElasticArray($Appearance, false, true));
		}
		catch (ElasticNoNodesAvailableException $e){
			error_log('ElasticSearch server was down when server attempted to index appearance '.$AppearanceID);
		}

		return $Appearance;
	}

	static function getElasticMeta($Appearance){
		return array_merge(CGUtils::ELASTIC_BASE,[
			'type' => 'entry',
			'id' => $Appearance['id'],
		]);
	}

	static function getElasticBody($Appearance){
		$tags = Tags::getFor($Appearance['id'], null, true, true);
		foreach ($tags as $k => $tag)
			$tags[$k] = $tag['name'];
		return [
			'label' => $Appearance['label'],
			'order' => $Appearance['order'],
			'private' => $Appearance['private'],
			'ishuman' => $Appearance['ishuman'],
			'tags' =>  $tags,
		];
	}

	static function toElasticArray(array $Appearance, bool $no_body = false, bool $update = false):array {
		$params = self::getElasticMeta($Appearance);
		if ($no_body)
			return $params;
		$params['body'] = self::getElasticBody($Appearance);
		if ($update)
			$params['body'] = [
				'doc' => $params['body'],
				'upsert' => $params['body'],
			];
		return $params;
	}
}
