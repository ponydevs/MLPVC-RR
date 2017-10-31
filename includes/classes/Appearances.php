<?php

namespace App;

use App\Models\Appearance;
use App\Models\Notification;
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
			$updates = $Appearance->owner_id === null ? $Appearance->getUpdatesHTML() : '';
			$notes = $Appearance->getNotesHTML();
			$tags = $Appearance->owner_id === null ? $Appearance->getTagsHTML() : '';
			$colors = $Appearance->getColorsHTML();
			$eqgp = $Appearance->ishuman ? 'eqg/' : '';
			$personalp = $Appearance->owner_id !== null ? '/@'.$Appearance->owner->name : '';

			$RenderPath = $Appearance->getPalettePath();
			$FileModTime = '?t='.CoreUtils::filemtime($RenderPath);
			$Actions = "<a class='btn link typcn typcn-image' title='View as PNG' href='$personalp/cg/{$eqgp}v/{$Appearance->id}p.png$FileModTime' target='_blank'></a>".
			           "<button class='getswatch typcn typcn-brush teal' title='Download swatch file'></button>";
			if ($permission)
				$Actions .= "<button class='edit typcn typcn-pencil darkblue' title='Edit'></button>".
				            ($Appearance->id!==0?"<button class='delete typcn typcn-trash red' title='Delete'></button>":'');
			$safelabel = $Appearance->getSafeLabel();
			$processedLabel = $Appearance->processLabel();
			$privlock = $Appearance->private ? "<span class='typcn typcn-lock-closed color-orange'></span> " : '';
			$HTML .= "<li id='p{$Appearance->id}'>$img<div><strong>$privlock<a href='{$Appearance->toURL()}'>$processedLabel</a>$Actions</strong>$updates$notes$tags$colors</div></li>";
		}
		else {
			if (empty($_MSG))
				$_MSG = 'No appearances to show';
			$HTML .= "<div class='notice info align-center'><label>$_MSG</label></div>";
		}

		return $wrap ? "<ul id='list' class='appearance-list'>$HTML</ul>" : $HTML;
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
				$elastiClient->update(array_merge((new Appearance(['id' => $id]))->getElasticMeta(), [
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
		/** @var $Appearances Appearance[] */
		$Appearances = DB::$instance->where('id != 0')->where('owner_id IS NULL')->get('appearances');

		$params = ['body' => []];
		foreach ($Appearances as $i => $a){
			$meta = $a->getElasticMeta();
		    $params['body'][] = [
		        'index' => [
		            '_index' => $meta['index'],
		            '_type' => $meta['type'],
		            '_id' => $meta['id'],
		        ]
		    ];

		    $params['body'][] = $a->getElasticBody();

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
