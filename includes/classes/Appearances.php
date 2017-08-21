<?php

namespace App;

use App\Models\Appearance;
use App\Models\Color;
use App\Models\ColorGroup;
use App\Models\Episode;

use App\Models\Logs\MajorChange;
use App\Models\Notification;
use App\Models\Tag;
use Elasticsearch\Common\Exceptions\Missing404Exception as ElasticMissing404Exception;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException as ElasticNoNodesAvailableException;
use Elasticsearch\Common\Exceptions\ServerErrorResponseException as ElasticServerErrorResponseException;

class Appearances {
	const COUNT_COL = 'COUNT(*) as cnt';
	/**
	 * @param bool      $EQG
	 * @param int|int[] $limit
	 * @param string    $userid
	 * @param string    $cols
	 *
	 * @return Appearance[]
	 */
	public static function get($EQG, $limit = null, $userid = null, $cols = null){
		if ($userid !== null)
			DB::$instance->where('owner_id', $userid);
		else {
			DB::$instance->where('owner_id IS NULL');
			self::_order();
			if ($EQG !== null)
				DB::$instance->where('ishuman', $EQG)->where('id',0,'!=');
		}
		if ($cols === self::COUNT_COL)
			DB::$instance->disableAutoClass();

		return DB::$instance->get('appearances', $limit, $cols);
	}

	/**
	 * Order appearances
	 *
	 * @param string $dir
	 */
	private static function _order($dir = 'ASC'){
		DB::$instance->orderByLiteral('CASE WHEN "order" IS NULL THEN 1 ELSE 0 END', $dir)
			->orderBy('"order"', $dir)
			->orderBy('id', $dir);
	}

	/**
	 * @param Appearance[] $Appearances
	 * @param bool         $wrap
	 * @param bool         $permission
	 *
	 * @return string
	 */
	public static function getHTML($Appearances, $wrap = WRAP, $permission = null){
		global $_MSG;

		if ($permission === null)
			$permission = Permission::sufficient('staff');

		$HTML = '';
		if (!empty($Appearances)) foreach ($Appearances as $Appearance){
			$Appearance->label = CoreUtils::escapeHTML($Appearance->label);

			$img = $Appearance->getSpriteHTML($permission);
			$updates = $Appearance->owner_id === null ? self::getUpdatesHTML($Appearance->id) : '';
			$notes = $Appearance->getNotesHTML();
			$tags = $Appearance->owner_id === null ? ($Appearance->id ? self::getTagsHTML($Appearance->id, true) : '') : '';
			$colors = self::getColorsHTML($Appearance);
			$eqgp = $Appearance->ishuman ? 'eqg/' : '';
			$personalp = $Appearance->owner_id !== null ? '/@'.$Appearance->owner->name : '';

			$RenderPath = $Appearance->getPalettePath();
			$FileModTime = '?t='.(file_exists($RenderPath) ? filemtime($RenderPath) : time());
			$Actions = "<a class='btn link typcn typcn-image' title='View as PNG' href='$personalp/cg/{$eqgp}v/{$Appearance->id}p.png$FileModTime' target='_blank'></a>".
			           "<button class='getswatch typcn typcn-brush teal' title='Download swatch file'></button>";
			if ($permission)
				$Actions .= "<button class='edit typcn typcn-pencil darkblue' title='Edit'></button>".
				            ($Appearance->id!==0?"<button class='delete typcn typcn-trash red' title='Delete'></button>":'');
			$safelabel = $Appearance->getSafeLabel();
			$processedLabel = $Appearance->processLabel();
			$HTML .= "<li id='p{$Appearance->id}'>$img<div><strong><a href='$personalp/cg/{$eqgp}v/{$Appearance->id}-$safelabel'>$processedLabel</a>$Actions</strong>$updates$notes$tags$colors</div></li>";
		}
		else {
			if (empty($_MSG))
				$_MSG = 'No appearances to show';
			$HTML .= "<div class='notice info align-center'><label>$_MSG</label></div>";
		}

		return $wrap ? "<ul id='list' class='appearance-list'>$HTML</ul>" : $HTML;
	}

	/**
	 * Returns the markup of the color list for a specific appearance
	 *
	 * @param Appearance $Appearance
	 * @param bool       $wrap
	 * @param bool       $colon
	 * @param bool       $colorNames
	 *
	 * @return string
	 */
	public static function getColorsHTML(Appearance $Appearance, bool $wrap = WRAP, $colon = true, $colorNames = false){
		if ($placehold = $Appearance->getPendingPlaceholder())
			return $placehold;

		$ColorGroups = $Appearance->color_groups;
		$AllColors = CGUtils::getColorsForEach($ColorGroups);

		$HTML = '';
		if (!empty($ColorGroups)) foreach ($ColorGroups as $cg)
			$HTML .= $cg->getHTML($AllColors, WRAP, $colon, $colorNames);

		return $wrap ? "<ul class='colors'>$HTML</ul>" : $HTML;
	}

	/**
	 * Return the markup of a set of tags belonging to a specific pony
	 *
	 * @param int  $PonyID
	 * @param bool $wrap
	 *
	 * @return string
	 */
	public static function getTagsHTML(int $PonyID, bool $wrap = WRAP):string {
		$Tags = Tags::getFor($PonyID, null, Permission::sufficient('staff'));

		$HTML = '';
		if ($PonyID !== 0 && Permission::sufficient('staff'))
			$HTML .= "<input type='text' class='addtag tag' placeholder='Enter tag' pattern='".TAG_NAME_PATTERN."' maxlength='30' required>";
		$HideSynon = Permission::sufficient('staff') && UserPrefs::get('cg_hidesynon');
		if (!empty($Tags)) foreach ($Tags as $t){
			$isSynon = !empty($t->synonym_of);
			if ($isSynon && $HideSynon)
				continue;
			$class = " class='tag id-{$t->id}".($isSynon?' synonym':'').(!empty($t->type)?' typ-'.$t->type:'')."'";
			$title = !empty($t->title) ? " title='".CoreUtils::aposEncode($t->title)."'" : '';
			$syn_of = $isSynon ? " data-syn-of='{$t->synonym_of}'" : '';
			$HTML .= "<span$class$title$syn_of>{$t->name}</span>";
		}

		return $wrap ? "<div class='tags'>$HTML</div>" : $HTML;
	}

	/**
	 * Returns the markup for the time of last update displayed under an appaerance
	 *
	 * @param int  $PonyID
	 * @param bool $wrap
	 *
	 * @return string
	 */
	public static function getUpdatesHTML($PonyID, $wrap = WRAP){
		$update = MajorChange::get($PonyID, MOST_RECENT);
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

	/**
	 * Sort appearances based on tags
	 *
	 * @param Appearance[] $Appearances
	 * @param bool         $EQG
	 * @param bool         $simpleArray
	 *
	 * @return array
	 */
	public static function sort($Appearances, bool $EQG, bool $simpleArray = false){
		$GroupTagIDs = array_keys(CGUtils::GROUP_TAG_IDS_ASSOC[$EQG?'eqg':'pony']);
		$Sorted = [];
		$Tagged = [];
		$_tagged = DB::$instance->where('tag_id IN ('.implode(',',$GroupTagIDs).')')->orderBy('appearance_id')->get('tagged');
		foreach ($_tagged as $row)
			$Tagged[$row->appearance_id][] = $row->tag_id;
		foreach ($Appearances as $p){
			if (!empty($Tagged[$p->id])){
				if (count($Tagged[$p->id]) > 1)
					usort($Tagged[$p->id],function($a,$b) use ($GroupTagIDs){
						return array_search($a, $GroupTagIDs, true) - array_search($b, $GroupTagIDs, true);
					});
				$tid = $Tagged[$p->id][0];
			}
			else $tid = -1;
			$Sorted[$tid][] = $p;
		}
		if ($simpleArray){
			$idArray = [];
			foreach (CGUtils::GROUP_TAG_IDS_ASSOC[$EQG?'eqg':'pony'] as $Category => $CategoryName){
				if (empty($Sorted[$Category]))
					continue;
				/** @var $Sorted Appearance[][] */
				foreach ($Sorted[$Category] as $p)
					$idArray[] = $p->id;
			}
			return $idArray;
		}
		else return $Sorted;
	}

	/**
	 * @param string|int[] $ids
	 */
	public static function reorder($ids){
		if (empty($ids))
			return;

		$elastiClient = CoreUtils::elasticClient();
		try {
			$elasticAvail = CoreUtils::elasticClient()->ping();
		}
		catch (ElasticNoNodesAvailableException|ElasticServerErrorResponseException $e){
			$elasticAvail = false;
		}
		$list = is_string($ids) ? explode(',', $ids) : $ids;
		foreach ($list as $i => $id){
			$order = $i+1;
			if (!DB::$instance->where('id', $id)->update('appearances', ['order' => $order]))
				Response::fail("Updating appearance #$id failed, process halted");

			if ($elasticAvail)
				$elastiClient->update(array_merge(self::getElasticMeta(new Appearance(['id' => $id])), [
					'body' => [ 'doc' => ['order' => $order] ],
				]));
		}
	}


	/**
	 * @param bool $EQG
	 */
	public static function getSortReorder($EQG){
		if ($EQG)
			return;
		self::reorder(self::sort(self::get($EQG,null,null,'id'), $EQG, SIMPLE_ARRAY));
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
	public static function applyTemplate(int $AppearanceID, bool $EQG){
		if (empty($AppearanceID) || !is_numeric($AppearanceID))
			throw new \InvalidArgumentException('Incorrect value for $AppearanceID while applying template');

		if (ColorGroup::exists([ 'conditions' => ['appearance_id = ?', $AppearanceID] ]))
			throw new \RuntimeException('Template can only be applied to empty appearances');

		/** @var $Scheme string[][] */
		$Scheme = $EQG
			? [
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
			]
			: [
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
			];

		$cgi = 1;
		$ci = 1;
		foreach ($Scheme as $GroupName => $ColorNames){
			/** @var $Group ColorGroup */
			$Group = ColorGroup::create([
				'appearance_id' => $AppearanceID,
				'label' => $GroupName,
				'order' => $cgi++,
			]);
			$GroupID = $Group->id;
			if (!$GroupID)
				throw new \RuntimeException(rtrim("Color group \"$GroupName\" could not be created: ".DB::$instance->getLastError()), ': ');

			foreach ($ColorNames as $label){
				if (!(new Color([
					'group_id' => $GroupID,
					'label' => $label,
					'order' => $ci++,
				]))->save()) throw new \RuntimeException(rtrim("Color \"$label\" could not be added: ".DB::$instance->getLastError()), ': ');
			}
		}
	}

	/**
	 * Returns the HTML of the "Linked to from # episodes" section of appearance pages
	 *
	 * @param Appearance $Appearance
	 * @param bool       $allowMovies
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function getRelatedEpisodesHTML(Appearance $Appearance, $allowMovies = false){
		/** @var $EpTagsOnAppearance Tag[] */
		$EpTagsOnAppearance = DB::$instance->setModel('Tag')->query(
			"SELECT t.name
			FROM tagged tg
			LEFT JOIN tags t ON tg.tag_id = t.id
			WHERE tg.appearance_id = ? AND  t.type = 'ep'
			ORDER BY t.name", [$Appearance->id]);

		if (empty($EpTagsOnAppearance))
			return '';

		$List = [];
		foreach ($EpTagsOnAppearance as $tag){
			$name = strtoupper($tag->name);
			$EpData = Episode::parseID($name);
			$Ep = Episodes::getActual($EpData['season'], $EpData['episode'], $allowMovies);
			$List[] = (
				empty($Ep)
				? self::expandEpisodeTagName($name)
				: $Ep->toAnchor($Ep->formatTitle())
			);
		}
		$List = implode(', ',$List);
		$N_episodes = CoreUtils::makePlural($Appearance->ishuman ? 'movie' : 'episode',count($EpTagsOnAppearance),PREPEND_NUMBER);
		$hide = '';

		return <<<HTML
	<section id="ep-appearances" $hide>
		<h2><span class='typcn typcn-video'></span>Linked to from $N_episodes</h2>
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
	public static function expandEpisodeTagName(string $tagname):string {
		global $EPISODE_ID_REGEX, $MOVIE_ID_REGEX;

		if (preg_match($EPISODE_ID_REGEX, $tagname, $_match))
			return 'S'.CoreUtils::pad($_match[1]).' E'.CoreUtils::pad($_match[2]);
		if (preg_match($MOVIE_ID_REGEX, $tagname, $_match))
			return "Movie #{$_match[1]}";
		return $tagname;
	}

	/**
	 * Retruns CM preview SVG image link (pony butt)
	 *
	 * @param \App\Models\Cutiemark $cm
	 *
	 * @return string
	 */
	public static function getCMFacingSVGURL($cm){
		$path = str_replace(['@','#'],[$cm->facing,$cm->appearance_id],CGUtils::CMDIR_SVG_PATH);
		return "/cg/v/{$cm->appearance_id}d.svg?facing={$cm->facing}&t=".(file_exists($path) ? filemtime($path) : time());
	}

	/**
	 * @return int
	 */
	public static function validateAppearancePageID(){
		return (new Input('APPEARANCE_PAGE','int', [
			Input::IS_OPTIONAL => true,
			Input::IN_RANGE => [0,null],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_RANGE => 'Appearance ID must be greater than or equal to @min'
			]
		]))->out();
	}

	/**
	 * @param Appearance $appearance
	 * @param bool       $treatHexNullAsEmpty
	 *
	 * @return bool
	 */
	public static function hasColors(Appearance $appearance, bool $treatHexNullAsEmpty = false):bool {
		$hexnull = $treatHexNullAsEmpty?'AND hex IS NOT NULL':'';
		return (DB::$instance->querySingle(
			"SELECT count(*) as cnt
			FROM colors
			WHERE group_id IN (SELECT group_id FROM color_groups WHERE appearance_id = ?) $hexnull", [$appearance->id])['cnt'] ?? 0) > 0;
	}

	public static function reindex(){
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
			'body' => [
				'mappings' => [
					'entry' => [
						'_all' => ['enabled' => false  ],
						'properties' => [
							'label' => [
								'type' => 'text',
								'analyzer' => 'overkill',
							],
							'order' => ['type' => 'integer'],
							'ishuman' => ['type' => 'boolean'],
							'private' => ['type' => 'boolean'],
							'tags' => [
								'type' => 'text',
								'analyzer' => 'overkill',
							],
						],
					],
				],
				'settings' => [
					'analysis' => [
						'analyzer' => [
							'overkill' => [
								'type' => 'custom',
								'tokenizer' => 'overkill',
								'filter' => [
									'lowercase'
								]
							],
						],
						'tokenizer' => [
							'overkill' => [
								'type' => 'edge_ngram',
								'min_gram' => 2,
								'max_gram' => 30,
								'token_chars' => [
									'letter',
									'digit',
								],
							],
						],
					],
				],
			]
		]);
		$elasticClient->indices()->create(array_merge($params));
		$Appearances = DB::$instance->where('id != 0')->where('owner_id IS NULL')->get('appearances');

		$params = ['body' => []];
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

		    if ($i % 100 === 0) {
		        $elasticClient->bulk($params);
		        $params = ['body' => []];
		    }
		}
		if (!empty($params['body'])) {
	        $elasticClient->bulk($params);
		}

		Response::success('Re-index completed');
	}

	/**
	 * @param Appearance|int $Appearance
	 */
	public static function updateIndex($Appearance){
		if (is_numeric($Appearance))
			$Appearance = Appearance::find('first', ['conditions' => ['id = ?', (int)$Appearance]]);
		try {
			CoreUtils::elasticClient()->update(self::toElasticArray($Appearance, false, true));
		}
		catch (ElasticNoNodesAvailableException $e){
			error_log('ElasticSearch server was down when server attempted to index appearance '.$Appearance->id);
		}
	}

	public static function getElasticMeta(Appearance $Appearance){
		return array_merge(CGUtils::ELASTIC_BASE,[
			'type' => 'entry',
			'id' => $Appearance->id,
		]);
	}

	public static function getElasticBody(Appearance $Appearance){
		$tags = Tags::getFor($Appearance->id, null, true, true);
		foreach ($tags as $k => $tag)
			$tags[$k] = $tag->name;
		return [
			'label' => $Appearance->label,
			'order' => $Appearance->order,
			'private' => $Appearance->private,
			'ishuman' => $Appearance->ishuman,
			'tags' =>  $tags,
		];
	}

	public static function toElasticArray(Appearance $Appearance, bool $no_body = false, bool $update = false):array {
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

	const SPRITE_NAG_USERID = '06af57df-8755-a533-8711-c66f0875209a';

	/**
	 * @param int    $appearance_id
	 * @param string $nag_id        ID of user to nag
	 *
	 * @return Notification[]
	 */
	public static function getSpriteColorIssueNotifications(int $appearance_id, ?string $nag_id = self::SPRITE_NAG_USERID){
		if ($nag_id !== null)
			DB::$instance->where('recipient_id', $nag_id);
		return DB::$instance
			->where('type','sprite-colors')
			->where("data->>'appearance_id'",(string)$appearance_id)
			->where('read_at',null)
			->orderBy('sent_at','DESC')
			->get(Notification::$table_name);
	}

	/**
	 * @param int|Notification[] $appearance_id
	 * @param string             $action        What to set as the notification clearing action
	 * @param string             $nag_id        ID of user to nag
	 */
	public static function clearSpriteColorIssueNotifications($appearance_id, string $action = 'clear', ?string $nag_id = self::SPRITE_NAG_USERID){
		if (is_int($appearance_id))
			$notifs = self::getSpriteColorIssueNotifications($appearance_id, $nag_id);
		else $notifs = $appearance_id;
		if (empty($notifs))
			return;

		foreach ($notifs as $n)
			Notifications::safeMarkRead($n->id, $action, true);
	}
}
