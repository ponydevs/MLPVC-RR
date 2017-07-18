<?php

namespace App\Controllers;
use ActiveRecord\Table;
use App\Auth;
use App\CGUtils;
use App\CoreUtils;
use App\CSRFProtection;
use App\Cutiemarks;
use App\DB;
use App\Exceptions\NoPCGSlotsException;
use App\Input;
use App\JSON;
use App\Logs;
use App\Models\Appearance;
use App\Models\Color;
use App\Models\ColorGroup;
use App\Models\Cutiemark;
use App\Models\Logs\MajorChange;
use App\Models\RelatedAppearance;
use App\Models\Tag;
use App\Models\Tagged;
use App\Models\User;
use App\Pagination;
use App\Permission;
use App\RegExp;
use App\Reponse;
use App\Response;
use App\UserPrefs;
use App\Appearances;
use App\Tags;
use App\ColorGroups;
use App\Users;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use Elasticsearch\Common\Exceptions\ServerErrorResponseException;
use ONGR\ElasticsearchDSL;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\Compound\FunctionScoreQuery;
use ONGR\ElasticsearchDSL\Query\MatchAllQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use Elasticsearch\Common\Exceptions\Missing404Exception as ElasticMissing404Exception;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException as ElasticNoNodesAvailableException;

class ColorGuideController extends Controller {
	public $do = 'colorguide';
	public function __construct(){
		parent::__construct();

		if (POST_REQUEST)
			CSRFProtection::protect();

		if (RelatedAppearance::count() === 0){
			$rel = DB::$instance->get('appearance_relations');
			foreach ($rel as $r)
				RelatedAppearance::make($r['source'],$r['target'],$r['mutual']);
		}
	}

	/** @var bool */
	private $_EQG, $_appearancePage, $_personalGuide;
	/** @var string */
	private $_cgPath;
	private function _initCGPath(){
		$this->_cgPath = '/cg'.($this->_EQG?'/eqg':'');
	}
	private function _initialize($params, bool $setPath = true){
		$this->_EQG = !empty($params['eqg']) || isset($_GET['eqg']);
		if ($setPath)
			$this->_initCGPath();
		$this->_appearancePage = isset($_POST['APPEARANCE_PAGE']);
		$this->_personalGuide = isset($_POST['PERSONAL_GUIDE']);
	}

	/** @var User */
	private $_ownedBy;
	/** @var bool */
	private $_isOwnedByUser;
	private function _initPersonal($params, $force = true){
		$this->_initialize($params, !$force);

		$nameSet = isset($params['name']);
		if (!$nameSet && $force)
			CoreUtils::notFound();
		$this->_ownedBy = $nameSet ? Users::get($params['name'], 'name') : null;
		$this->_isOwnedByUser = $nameSet ? (Auth::$signed_in && Auth::$user->id === $this->_ownedBy->id) : false;

		if ($nameSet)
			$this->_cgPath = "/@{$this->_ownedBy->name}/cg";
	}

	/** @var \App\Models\Appearance */
	private $_appearance;
	public function _getAppearance($params){
		$asFile = isset($params['ext']);
		if (!isset($params['id']))
			Response::fail('Missing appearance ID');
		$this->_appearance = Appearance::find($params['id']);
		if (empty($this->_appearance))
			CoreUtils::notFound();

		if (empty($this->_appearance->owner_id)){
			if ($this->_appearance->ishuman && !$this->_EQG){
				$this->_EQG = 1;
				$this->_cgPath = '/cg/eqg';
			}
			else if (!$this->_appearance->ishuman && $this->_EQG){
				$this->_EQG = 0;
				$this->_cgPath = '/cg';
			}
			else if ($this->_ownedBy !== null){
				$this->_ownedBy = null;
				$this->_isOwnedByUser = false;
				$this->_cgPath = '/cg';
			}
		}
		else {
			$this->_EQG = null;
			$OwnerName = $this->_appearance->owner->name;
			$this->_cgPath = "/@$OwnerName/cg";
		}
	}

	public function spriteColors($params){
		if (Permission::insufficient('member'))
			CoreUtils::notFound();

		$this->_getAppearance($params);
		if (Permission::insufficient('staff') && ($this->_appearance->owner_id ?? null) != Auth::$user->id)
			CoreUtils::notFound();

		$Map = CGUtils::getSpriteImageMap($this->_appearance->id);
		if (empty($Map))
			CoreUtils::notFound();

		$Colors = [];
		$loop = isset($this->_appearance->owner_id) ? [$this->_appearance->id] : [0, $this->_appearance->id];
		foreach ($loop as $AppearanceID){
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
						'label' => $SortedColorGroups[$c->group_id]['label'].' | '.$c->label,
					];
			}
		}
		if (!isset($this->_appearance->owner_id))
			$Colors = array_merge($Colors,
				[
					[
						'hex' => '#D8D8D8',
						'label' => 'Mannequin | Outline',
					],
					[
		                'hex' => '#E6E6E6',
		                'label' => 'Mannequin | Fill',
					],
					[
		                'hex' => '#BFBFBF',
		                'label' => 'Mannequin | Shadow Outline',
					],
					[
		                'hex' => '#CCCCCC',
		                'label' => 'Mannequin | Shdow Fill',
					]
				]
			);

		$this->_initialize($params);

		$SafeLabel = $this->_appearance->getSafeLabel();
		CoreUtils::fixPath("{$this->_cgPath}/sprite/{$this->_appearance->id}-$SafeLabel");

		CoreUtils::loadPage([
			'view' => "{$this->do}-sprite",
			'title' => "Sprite of {$this->_appearance->label}",
			'css' => "{$this->do}-sprite",
			'js' => "{$this->do}-sprite",
			'import' => [
				'Appearance' => $this->_appearance,
				'ColorGroups' => $ColorGroups,
				'Colors' => $Colors,
				'AllColors' => $AllColors,
				'Map' => $Map,
			],
		]);
	}

	public function appearanceAsFile($params, User $Owner = null){
		if (!isset($Owner))
			$this->_initialize($params);
		$this->_getAppearance($params);

		switch ($params['ext']){
			case 'png':
				if (!empty($params['type'])) switch ($params['type']){
					case 's': CGUtils::renderSpritePNG($this->_cgPath, $this->_appearance->id, $_GET['s'] ?? null);
					case 'p': CGUtils::renderAppearancePNG($this->_cgPath, $this->_appearance);
					default: CoreUtils::notFound();
				}

			case 'svg':
				if (!empty($params['type'])) switch ($params['type']){
					case 's': CGUtils::renderSpriteSVG($this->_cgPath, $this->_appearance->id);
					case 'p': CGUtils::renderPreviewSVG($this->_cgPath, $this->_appearance);
					case 'd': CGUtils::renderCMDirectionSVG($this->_cgPath, $this->_appearance->id);
					default: CoreUtils::notFound();
				}
			case 'json': CGUtils::getSwatchesAI($this->_appearance);
			case 'gpl': CGUtils::getSwatchesInkscape($this->_appearance);
		}
		# rendering functions internally call die(), so execution stops above #

		CoreUtils::notFound();
	}

	public function personalAppearanceAsFile($params){
		$this->_initPersonal($params);

		self::appearanceAsFile($params, $this->_ownedBy);
	}

	private $_GUIDE_MANAGE_JS = [
		'jquery.uploadzone',
		'jquery.autocomplete',
		'handlebars-v3.0.3',
		'Sortable',
		'colorguide-tags',
		'colorguide-manage',
	];
	private $_GUIDE_MANAGE_CSS = [
		'colorguide-manage',
	];

	public function appearancePage($params){
		if ($this->_ownedBy === null)
			$this->_initialize($params);
		$this->_getAppearance($params);
		if ($this->_ownedBy !== null && $this->_appearance->owner_id !== $this->_ownedBy->id){
			$this->_ownedBy = null;
			$this->_isOwnedByUser = false;
			$this->_initCGPath();
		}

		$SafeLabel = $this->_appearance->getSafeLabel();
		CoreUtils::fixPath("$this->_cgPath/v/{$this->_appearance->id}-$SafeLabel");
		$title = $heading = $this->_appearance->processLabel();

		$settings = [
			'title' => "$title - Color Guide",
			'heading' => $heading,
			'view' => "{$this->do}-appearance",
			'css' => [$this->do, "{$this->do}-appearance"],
			'js' => ['jquery.qtip', 'jquery.ctxmenu', $this->do, "{$this->do}-appearance"],
			'import' => [
				'Appearance' => $this->_appearance,
				'EQG' => $this->_EQG,
				'isOwner' => false,
			],
		];
		if (!empty($this->_appearance->owner_id)){
			$settings['import']['Owner'] = $this->_ownedBy;
			$settings['import']['isOwner'] = $this->_isOwnedByUser;
		}
		else $settings['import']['Changes'] = MajorChange::get($this->_appearance->id);
		if (Permission::sufficient('staff') || $this->_isOwnedByUser){
			$settings['css'] = array_merge($settings['css'], $this->_GUIDE_MANAGE_CSS);
			$settings['js'] = array_merge($settings['js'], $this->_GUIDE_MANAGE_JS);
		}
		CoreUtils::loadPage($settings);
	}

	public function personalAppearancePage($params){
		$this->_initPersonal($params);

		self::appearancePage($params);
	}

	public function fullList($params){
		$this->_initialize($params);

		$GuideOrder = !isset($_REQUEST['alphabetically']) && !$this->_EQG;
		if (!$GuideOrder)
			DB::$instance->orderBy('label');
		$Appearances = Appearances::get($this->_EQG,null,null,'id,label,private');

		if (isset($_REQUEST['ajax']))
			Response::done(['html' => CGUtils::getFullListHTML($Appearances, $GuideOrder, NOWRAP)]);

		$js = [];
		if (Permission::sufficient('staff'))
			$js[] = 'Sortable';
		$js[] = "{$this->do}-full";

		CoreUtils::loadPage([
			'title' => 'Full List - Color Guide',
			'view' => "{$this->do}-full",
			'css' => "{$this->do}-full",
			'js' => $js,
			'import' => [
				'EQG' => $this->_EQG,
				'Appearances' => $Appearances,
				'GuideOrder' => $GuideOrder,
			]
		]);
	}

	public function reorderFullList($params){
		$this->_initialize($params);

		if (!Permission::sufficient('staff'))
			Response::fail();

		Appearances::reorder((new Input('list','int[]', [
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'The list of IDs is missing',
				Input::ERROR_INVALID => 'The list of IDs is not formatted properly',
			]
		]))->out());

		Response::done(['html' => CGUtils::getFullListHTML(Appearances::get($this->_EQG,null,null,'id,label'), true, NOWRAP)]);
	}

	public function changeList(){
		$Pagination = new Pagination('cg/changes', 50, MajorChange::count());

		CoreUtils::fixPath("/cg/changes/{$Pagination->page}");
		$heading = 'Major Color Changes';
		$title = "Page $Pagination->page - $heading - Color Guide";

		$Changes = MajorChange::get(null, $Pagination->getLimitString());

		$Pagination->respondIfShould(CGUtils::getChangesHTML($Changes, NOWRAP, SHOW_APPEARANCE_NAMES), '#changes');

		CoreUtils::loadPage([
			'title' => $title,
			'heading' => $heading,
			'view' => "{$this->do}-changes",
			'css' => "{$this->do}-changes",
			'js' => 'paginate',
			'import' => [
				'Changes' => $Changes,
				'Pagination' => $Pagination,
			],
		]);
	}

	public function tagList(){
		$Pagination = new Pagination('cg/tags', 20, DB::$instance->count('tags'));

		CoreUtils::fixPath("/cg/tags/{$Pagination->page}");
		$heading = 'Tags';
		$title = "Page $Pagination->page - $heading - Color Guide";

		$Tags = Tags::getFor(null,$Pagination->getLimit(), true);

		$Pagination->respondIfShould(Tags::getTagListHTML($Tags, NOWRAP), '#tags tbody');

		$js = ['paginate'];
		if (Permission::sufficient('staff'))
			$js[] = "{$this->do}-tags";

		CoreUtils::loadPage([
			'title' => $title,
			'heading' => $heading,
			'view' => "{$this->do}-tags",
			'css' => "{$this->do}-tags",
			'js' => $js,
			'import' => [
				'Tags' => $Tags,
				'Pagination' => $Pagination,
			],
		]);
	}

	public function guide($params){
		$this->_initialize($params);

		$title = '';
		$AppearancesPerPage = UserPrefs::get('cg_itemsperpage');
		$Ponies = [];
		try {
			$elasticAvail = CoreUtils::elasticClient()->ping();
		}
		catch (NoNodesAvailableException|ServerErrorResponseException $e){
			$elasticAvail = false;
		}
		$searching = !empty($_GET['q']) && CoreUtils::length(trim($_GET['q'])) > 0;
		$jsResponse = CoreUtils::isJSONExpected();
		if ($elasticAvail){
			$search = new ElasticsearchDSL\Search();
			$orderByOrder = true;
		    $Pagination = new Pagination(ltrim($this->_cgPath, '/'), $AppearancesPerPage);

			// Search query exists
			if ($searching){
				$SearchQuery = preg_replace(new RegExp('[^\w\d\s\*\?]'),'',trim($_GET['q']));
				$title .= "$SearchQuery - ";
				$multiMatch = new ElasticsearchDSL\Query\FullText\MultiMatchQuery(
					['label^5','tags'],
					$SearchQuery,
					[
						'type' => 'cross_fields',
						'minimum_should_match' => '85%',
					]
				);
				$search->addQuery($multiMatch);
				$score = new FunctionScoreQuery(new MatchAllQuery());
				$score->addFieldValueFactorFunction('order',1.5);
				$score->addParameter('boost_mode','multiply');
				$search->addQuery($score);
				$sort = new ElasticsearchDSL\Sort\FieldSort('_score', 'asc');
				$search->addSort($sort);
				$orderByOrder = false;
			}
			else {
				$sort = new ElasticsearchDSL\Sort\FieldSort('order', 'asc');
				$search->addSort($sort);
			}

			$boolquery = new BoolQuery();
			if (Permission::insufficient('staff'))
				$boolquery->add(new TermQuery('private', false), BoolQuery::MUST);
			$boolquery->add(new TermQuery('ishuman', (bool)$this->_EQG), BoolQuery::MUST);
			$search->addQuery($boolquery);

			$search->setSource(false);
			$search = $search->toArray();
			try {
				$search = CGUtils::searchElastic($search, $Pagination);
			}
			catch (Missing404Exception $e){
				$elasticAvail = false;
				$search = [];
			}

			$Pagination->calcMaxPages($search['hits']['total']);
			if (!empty($search['hits']['hits'])){
				$ids = [];
				/** @noinspection ForeachSourceInspection */
				foreach($search['hits']['hits'] as $i => $hit)
					$ids[$hit['_id']] = $i;

				$find = [
					'conditions' => [ 'id IN (?)', array_keys($ids)	],
				];
				if (!$orderByOrder){
					$Ponies = Appearance::find('all', $find);
				}
				else {
					$Ponies = Appearance::find('all', $find);
					if (!empty($Ponies))
						uasort($Ponies, function(Appearance $a, Appearance $b) use ($ids){
							return $ids[$a->id] <=> $ids[$b->id];
						});
				}
			}
		}
		if (!$elasticAvail) {
			if ($searching && $jsResponse)
				Response::fail('The ElasticSearch server is currently down and search is not available, sorry for the inconvenience.<br>Please <a class="send-feedback">let us know</a> about this issue.', ['unavail' => true]);

			$searching = false;
			$SearchQuery = null;
		    $_EntryCount = DB::$instance->where('ishuman',$this->_EQG)->where('id != 0')->count('appearances');

		    $Pagination = new Pagination(ltrim($this->_cgPath, '/'), $AppearancesPerPage, $_EntryCount);
		    $Ponies = Appearances::get($this->_EQG, $Pagination->getLimit());
		}

		if (isset($_REQUEST['btnl'])){
			if (empty($Ponies[0]->id))
				Response::fail('The search returned no results.');
			Response::done(['goto' => $Ponies[0]->getLink()]);
		}

		CoreUtils::fixPath("$this->_cgPath/{$Pagination->page}?q=".(!empty($SearchQuery) ? $SearchQuery : CoreUtils::FIXPATH_EMPTY));
		$heading = ($this->_EQG?'EQG ':'').'Color Guide';
		$title .= "Page {$Pagination->page} - $heading";

		$Pagination->respondIfShould(Appearances::getHTML($Ponies, NOWRAP), '#list');

		$settings = [
			'title' => $title,
			'heading' => $heading,
			'css' => [$this->do],
			'js' => ['jquery.qtip', 'jquery.ctxmenu', $this->do, 'paginate'],
			'import' => [
				'EQG' => $this->_EQG,
				'Ponies' => $Ponies,
				'Pagination' => $Pagination,
				'elasticAvail' => $elasticAvail,
			],
		];
		if (Permission::sufficient('staff')){
			$settings['css'] = array_merge($settings['css'], $this->_GUIDE_MANAGE_CSS);
			$settings['js'] = array_merge($settings['js'], $this->_GUIDE_MANAGE_JS);
		}
		CoreUtils::loadPage($settings, $this);
	}

	public function personalGuide($params){
		$this->_initPersonal($params);

		$title = '';
		$AppearancesPerPage = UserPrefs::get('cg_itemsperpage');
		$Ponies = [];
	    $_EntryCount = $this->_ownedBy->getPCGAppearances(null, true);


	    $Pagination = new Pagination("@{$this->_ownedBy->name}/cg", $AppearancesPerPage, $_EntryCount);
	    $Ponies = $this->_ownedBy->getPCGAppearances($Pagination);

		CoreUtils::fixPath("$this->_cgPath/{$Pagination->page}");
		$heading = CoreUtils::posess($this->_ownedBy->name).' Personal Color Guide';
		$title .= "Page {$Pagination->page} - $heading";

		$Pagination->respondIfShould(Appearances::getHTML($Ponies, NOWRAP), '#list');

		$settings = [
			'title' => $title,
			'heading' => $heading,
			'css' => ['colorguide'],
			'js' => ['jquery.qtip', 'jquery.ctxmenu', 'colorguide', 'paginate'],
			'view' => 'user-colorguide',
			'import' => [
				'Ponies' => $Ponies,
				'Pagination' => $Pagination,
				'Owner' => $this->_ownedBy,
				'isOwner' => $this->_isOwnedByUser,
			],
		];
		if ($this->_isOwnedByUser){
			$settings['css'] = array_merge($settings['css'], $this->_GUIDE_MANAGE_CSS);
			$settings['js'] = array_merge($settings['js'], $this->_GUIDE_MANAGE_JS);
		}
		CoreUtils::loadPage($settings, $this);
	}

	const CM_BASIC_COLS = 'cmid,favme,favme_rotation,preview_src,facing';
	public function export(){
		if (!Permission::sufficient('developer'))
			CoreUtils::notFound();
		$JSON = [
			'Appearances' => [],
			'Tags' => [],
		];

		/** @var $Tags Tag[] */
		$Tags = DB::$instance->orderBy('id')->get('tags');
		if (!empty($Tags)) foreach ($Tags as $t){
			$JSON['Tags'][$t->id] = $t->to_array();
		}

		$Appearances = Appearances::get(null);
		if (!empty($Appearances)) foreach ($Appearances as $p){
			$AppendAppearance = $p->to_array([
				'except' => ['owner_id','last_cleared'],
			]);

			$AppendAppearance['added'] = gmdate('Y-m-d\TH:i:s\Z',$p->added->getTimestamp());

			$AppendAppearance['notes'] = isset($AppendAppearance['notes'])
				? CoreUtils::trim($AppendAppearance['notes'],true)
				: '';

			$CMs = Cutiemarks::get($p, false);
			if (!empty($CMs)){
				$AppendCMs = [];
				foreach ($CMs as $CM){
					$AppendCMs[] = [
						'link' => "http://fav.me/{$CM->favme}",
						'facing' => $CM->facing,
					];
				}
				$AppendAppearance['CutieMark'] = $AppendCMs;
			}

			$AppendAppearance['ColorGroups'] = [];
			if (empty($AppendAppearance['private'])){
				$ColorGroups = $p->color_groups;
				if (!empty($ColorGroups)){
					$AllColors = CGUtils::getColorsForEach($ColorGroups);
					foreach ($ColorGroups as $cg){
						$AppendColorGroup = $cg->to_array([
							'except' => 'appearance_id',
						]);

						$AppendColorGroup['Colors'] = [];
						if (!empty($AllColors[$cg->id])){
							/** @var $colors Color[] */
							$colors = $AllColors[$cg->id];
							foreach ($colors as $c)
								$AppendColorGroup['Colors'][] = $c->to_array([
									'except' => 'group_id',
								]);
						}

						$AppendAppearance['ColorGroups'][$cg->id] = $AppendColorGroup;
					}
				}
			}
			else $AppendAppearance['ColorGroups']['_hidden'] = true;

			$AppendAppearance['TagIDs'] = [];
			$TagIDs = Tags::getFor($p->id,null,null,true);
			if (!empty($TagIDs)){
				foreach ($TagIDs as $t)
					$AppendAppearance['TagIDs'][] = $t->id;
			}

			$AppendAppearance['RelatedAppearances'] = [];
			$RelatedIDs = $p->related_appearances;
			if (!empty($RelatedIDs))
				foreach ($RelatedIDs as $rel)
					$AppendAppearance['RelatedAppearances'][] = $rel->target_id;

			$JSON['Appearances'][$AppendAppearance['id']] = $AppendAppearance;
		}

		$data = JSON::encode($JSON, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		$data = preg_replace_callback('/^\s+/m', function($match){
			return str_pad('',CoreUtils::length($match[0])/4,"\t", STR_PAD_LEFT);
		}, $data);

		CoreUtils::downloadFile($data, 'mlpvc-colorguide.json');
	}

	public function getTags(){
		global $TAG_NAME_REGEX;

		if (!Permission::sufficient('staff'))
			Response::fail();

		$except = (new Input('not','int', [Input::IS_OPTIONAL => true]))->out();
		if ((new Input('action','string', [Input::IS_OPTIONAL => true]))->out() === 'synon'){
			if ($except !== null)
				DB::$instance->where('id',$except);
			/** @var $Tag Tag */
			$Tag = DB::$instance->where('"synonym_of" IS NOT NULL')->getOne('tags');
			if (!empty($Tag))
				Response::fail("This tag is already a synonym of <strong>{$Tag->synonym->name}</strong>.<br>Would you like to remove the synonym?", ['undo' => true]);
		}

		$viaAutocomplete = !empty($_GET['s']);
		$limit = null;
		$cols = 'id, name, type';
		if ($viaAutocomplete){
			if (!preg_match($TAG_NAME_REGEX, $_GET['s']))
				CGUtils::autocompleteRespond('[]');

			$query = CoreUtils::trim(strtolower($_GET['s']));
			$TagCheck = CGUtils::checkEpisodeTagName($query);
			if ($TagCheck !== false)
				$query = $TagCheck;
			DB::$instance->where('name',"%$query%",'LIKE');
			$limit = 5;
			$cols = "id, name, 'typ-'||type as type";
			DB::$instance->orderBy('uses','DESC');
		}
		else DB::$instance->orderBy('type')->where('"synonym_of" IS NULL');

		if ($except !== null)
			DB::$instance->where('id',$except,'!=');

		$Tags = DB::$instance->disableAutoClass()->orderBy('name')->get('tags',$limit,"$cols, uses, synonym_of");
		if ($viaAutocomplete){
			foreach ($Tags as &$t){
				if (empty($t['synonym_of']))
					continue;
				$Syn = Tag::find($t['synonym_of']);
				if (!empty($Syn))
					$t['synonym_target'] = $Syn->name;
			}
			unset($t);
		}

		CGUtils::autocompleteRespond(empty($Tags) ? '[]' : $Tags);
	}

	public function reindex(){
		if (Permission::insufficient('developer'))
			Response::fail();
		Appearances::reindex();
	}

	public function appearanceAction($params){
		$this->_initPersonal($params, false);

		if (Permission::insufficient('member'))
			Response::fail();

		$action = $params['action'];
		$creating = $action === 'make';

		if ($creating){
			if (!$this->_personalGuide && Permission::insufficient('staff'))
				Response::fail('You don’t have permission to add appearances to the official Color Guide');

			if ($this->_personalGuide){
				try {
					$availSlots = Auth::$user->getPCGAvailableSlots();
				}
				catch (NoPCGSlotsException $e){
					Response::fail("You don’t have any slots. If you’d like to know how to get some, click the blue <strong class='color-darkblue'>What?</strong> button on your <a href='/u'>Account page</a> to learn more about this feature.");
				}
				if ($availSlots === 0){
					$remain = Users::calculatePersonalCGNextSlot(Auth::$user->getPCGAppearances(null, true));
					Response::fail("You don’t have enough slots to create another appearance. Delete other ones or finish $remain more ".CoreUtils::makePlural('request',$remain).'.');
				}
			}
		}
		else {
			$this->_getAppearance($params);

			if (!$this->_isOwnedByUser && Permission::insufficient('staff'))
				Response::fail();
		}

		$this->_execAppearanceAction($action, $creating);
	}

	public function _execAppearanceAction($action, $creating = null, $noResponse = false){
		switch ($action){
			case 'get':
				Response::done([
					'label' => $this->_appearance->label,
					'notes' => $this->_appearance->notes,
					'private' => $this->_appearance->private,
				]);
			break;
			case 'set':
			case 'make':
				/** @var $data array */
				$data = [
					'ishuman' => $this->_personalGuide ? null : (bool)$this->_EQG,
				];

				$label = (new Input('label','string', [
					Input::IN_RANGE => [4,70],
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Appearance name is missing',
						Input::ERROR_RANGE => 'Appearance name must be beetween @min and @max characters long',
					]
				]))->out();
				CoreUtils::checkStringValidity($label, 'Appearance name', INVERSE_PRINTABLE_ASCII_PATTERN);
				$dupe = Appearance::find_dupe($creating, $this->_personalGuide, [
					'owner_id' => Auth::$user->id,
					'ishuman' => $data['ishuman'],
					'label' => $label,
					'id' => $creating ? null : $this->_appearance->id,
				]);
				if (!empty($dupe)){
					if ($this->_personalGuide)
						Response::fail('You already have an appearance with the same name in your Personal Color Guide');

					$eqg_url = $this->_EQG ? '/eqg':'';
					Response::fail("An appearance <a href='{$dupe->getLink()}' target='_blank'>already esists</a> in the ".($this->_EQG?'EQG':'Pony').' guide with this exact name. Consider adding an identifier in backets or choosing a different name.');
				}
				if ($creating || $label !== $this->_appearance->label)
					$data['label'] = $label;

				$notes = (new Input('notes','text', [
					Input::IS_OPTIONAL => true,
					Input::IN_RANGE => $creating || $this->_appearance->id !== 0 ? [null,1000] : null,
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_RANGE => 'Appearance notes cannot be longer than @max characters',
					]
				]))->out();
				if (isset($notes)){
					CoreUtils::checkStringValidity($notes, 'Appearance notes', INVERSE_PRINTABLE_ASCII_PATTERN);
					$notes = CoreUtils::sanitizeHtml($notes);
					if ($creating || $notes !== $this->_appearance->notes)
						$data['notes'] = $notes;
				}
				else $data['notes'] = null;

				$data['private'] = isset($_POST['private']);

				if ($creating){
					if ($this->_personalGuide || Permission::insufficient('staff')){
						$data['owner_id'] = Auth::$user->id;
						$ownerName = Auth::$user->name;
					}
					if (empty($data['owner_id'])){
						$biggestOrder = DB::$instance->disableAutoClass()->getOne('appearances','MAX("order") as "order"');
						$data['order'] = ($biggestOrder['order'] ?? 0)+1;
					}
				}
				else if ($data['private']){
					$data['last_cleared'] = date('c');
				}

				/** @var $newAppearance Appearance */
				if ($creating){
					$newAppearance = Appearance::create($data);
					$newAppearance->reindex();
				}
				else {
					$olddata = $this->_appearance->to_array();
					$this->_appearance->update_attributes($data);
					$this->_appearance->reindex();
				}

				$EditedAppearance = $creating ? $newAppearance : $this->_appearance;

				if ($creating){
					$data['id'] = $newAppearance->id;
					$response = [
						'message' => 'Appearance added successfully',
						'goto' => $newAppearance->getLink(),
					];
					$usetemplate = isset($_POST['template']);
					if ($usetemplate){
						try {
							Appearances::applyTemplate($newAppearance->id, $this->_EQG);
						}
						catch (\Exception $e){
							$response['message'] .= ', but applying the template failed';
							$response['info'] = 'The common color groups could not be added.<br>Reason: '.$e->getMessage();
							$usetemplate = false;
						}
					}

					Logs::logAction('appearances', [
						'action' => 'add',
					    'id' => $newAppearance->id,
					    'order' => $newAppearance->order,
					    'label' => $newAppearance->label,
					    'notes' => $newAppearance->notes,
					    'ishuman' => $newAppearance->ishuman,
						'usetemplate' => $usetemplate,
						'private' => $newAppearance->private,
						'owner_id' => $newAppearance->owner_id,
					]);
					Response::done($response);
				}

				CGUtils::clearRenderedImages($this->_appearance->id, [CGUtils::CLEAR_PALETTE, CGUtils::CLEAR_PREVIEW]);

				if (!$creating){
					$diff = [];
					foreach (['label', 'notes', 'private', 'owner_id'] as $key){
						if ($EditedAppearance->{$key} !== $olddata[$key]){
							$diff["old$key"] = $olddata[$key];
							$diff["new$key"] = $EditedAppearance->{$key};
						}
					}
					if (!empty($diff)) Logs::logAction('appearance_modify', [
						'appearance_id' => $this->_appearance->id,
						'changes' => JSON::encode($diff),
					]);
				}

				$response = [];
				if (!$this->_appearancePage){
					$response['label'] = $EditedAppearance->label;
					if (isset($olddata['label']) && $olddata['label'] !== $this->_appearance->label)
						$response['newurl'] = $EditedAppearance->getLink();
					$response['notes'] = $EditedAppearance->getNotesHTML(NOWRAP);
				}

				Response::done($response);
			break;
			case 'delete':
				if ($this->_appearance->id === 0)
					Response::fail('This appearance cannot be deleted');

				$Tagged = Tags::getFor($this->_appearance->id, null, true);

				if (!DB::$instance->where('id', $this->_appearance->id)->delete('appearances'))
					Response::dbError();

				try {
					CoreUtils::elasticClient()->delete(Appearances::toElasticArray($this->_appearance, true));
				}
				catch (ElasticMissing404Exception $e){
					$message = JSON::decode($e->getMessage());

					// Eat error if appearance was not indexed
					if ($message['found'] !== false)
						throw $e;
				}
				catch (ElasticNoNodesAvailableException $e){
					error_log('ElasticSearch server was down when server attempted to remove appearance '.$this->_appearance->id);
				}

				if (!empty($Tagged))
					foreach($Tagged as $tag){
						Tags::updateUses($tag->id);
					};

				$fpath = APPATH."img/cg/{$this->_appearance->id}.png";
				if (file_exists($fpath))
					unlink($fpath);

				CGUtils::clearRenderedImages($this->_appearance->id);

				Logs::logAction('appearances', [
					'action' => 'del',
				    'id' => $this->_appearance->id,
				    'order' => $this->_appearance->order,
				    'label' => $this->_appearance->label,
				    'notes' => $this->_appearance->notes,
				    'ishuman' => $this->_appearance->ishuman,
				    'added' => $this->_appearance->added,
				    'private' => $this->_appearance->private,
				    'owner_id' => $this->_appearance->owner_id,
				]);

				Response::success('Appearance removed');
			break;
			case 'getcgs':
				$cgs = $this->_appearance->color_groups;
				if (empty($cgs))
					Response::fail('This appearance does not have any color groups');
				foreach ($cgs as $i => $cg)
					$cgs[$i] = $cg->to_array([
						'only' => ['id','label'],
					]);
				Response::done(['cgs' => $cgs]);
			break;
			case 'setcgs':
				$order = (new Input('cgs','int[]', [
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Color group order data missing'
					]
				]))->out();
				$oldCGs = $this->_appearance->color_groups;
				$possibleIDs = [];
				foreach ($oldCGs as $cg)
					$possibleIDs[$cg->id] = true;
				foreach ($order as $i => $GroupID){
					if (empty($possibleIDs[$GroupID]))
						Response::fail("There’s no group with the ID of $GroupID on this appearance");

					DB::$instance->where('id', $GroupID)->update('color_groups', ['order' => $i]);
				}
				Table::clear_cache();
				$newCGs = $this->_appearance->color_groups;

				CGUtils::clearRenderedImages($this->_appearance->id, [CGUtils::CLEAR_PALETTE, CGUtils::CLEAR_PREVIEW]);

				$oldCGs = CGUtils::stringifyColorGroups($oldCGs);
				$newCGs = CGUtils::stringifyColorGroups($newCGs);
				if ($oldCGs !== $newCGs) Logs::logAction('cg_order', [
					'appearance_id' => $this->_appearance->id,
					'oldgroups' => $oldCGs,
					'newgroups' => $newCGs,
				]);

				Response::done(['cgs' => Appearances::getColorsHTML($this->_appearance, NOWRAP, !$this->_appearancePage, $this->_appearancePage)]);
			break;
			case 'delsprite':
			case 'getsprite':
			case 'setsprite':
				$fname = $this->_appearance->id.'.png';
				$finalpath = SPRITE_PATH.$fname;

				switch ($action){
					case 'setsprite':
						CGUtils::processUploadedImage('sprite', $finalpath, ['image/png'], [300], [700, 300]);
						CGUtils::clearRenderedImages($this->_appearance->id);
					break;
					case 'delsprite':
						if (empty($this->_appearance->getSpriteURL())){
							if ($noResponse)
								return;
							Response::fail('No sprite file found');
						}

						if (!unlink($finalpath))
							Response::fail('File could not be deleted');
						CGUtils::clearRenderedImages($this->_appearance->id);

						if ($noResponse)
							return;

						Response::done(['sprite' => DEFAULT_SPRITE]);
					break;
				}

				Response::done(['path' => "/cg/v/{$this->_appearance->id}s.png?t=".filemtime($finalpath)]);
			break;
			case 'getrelations':
				if (!empty($this->_appearance->owner_id))
					Response::fail('Relations are unavailable for appearances in personal guides');

				$CheckTag = [];

				$RelatedAppearances = $this->_appearance->related_appearances;
				$RelatedAppearanceIDs = [];
				foreach ($RelatedAppearances as $p)
					$RelatedAppearanceIDs[$p->target_id] = $p->is_mutual;

				$Appearances = DB::$instance->disableAutoClass()->where('ishuman', $this->_EQG)->where('"id" NOT IN (0,'.$this->_appearance->id.')')->orderBy('label')->get('appearances',null,'id,label');

				$Sorted = [
					'unlinked' => [],
					'linked' => [],
				];
				foreach ($Appearances as $a){
					$linked = isset($RelatedAppearanceIDs[$a['id']]);
					if ($linked)
						$a['mutual'] = $RelatedAppearanceIDs[$a['id']];
					$Sorted[$linked ? 'linked' : 'unlinked'][] = $a;
				}

				Response::done($Sorted);
			break;
			case 'setrelations':
				if (!empty($this->_appearance->owner_id))
					Response::fail('Relations are unavailable for appearances in personal guides');

				$AppearanceIDs = (new Input('ids','int[]', [
					Input::IS_OPTIONAL => true,
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_INVALID => 'Appearance ID list is invalid',
					]
				]))->out();
				$MutualIDs = (new Input('mutuals','int[]', [
					Input::IS_OPTIONAL => true,
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_INVALID => 'Mutial relation ID list is invalid',
					]
				]))->out();

				$appearances = [];
				if (!empty($AppearanceIDs))
					foreach ($AppearanceIDs as $id){
						$appearances[$id] = true;
					};

				$mutuals = [];
				if (!empty($MutualIDs))
					foreach ($MutualIDs as $id){
						$mutuals[$id] = true;
					};

				$this->_appearance->clearRelations();
				if (!empty($appearances))
					foreach ($appearances as $id => $_)
						RelatedAppearance::make($this->_appearance->id, $id, isset($mutuals[$id]));

				$out = [];
				if ($this->_appearancePage)
					$out['section'] = $this->_appearance->getRelatedHTML();
				Response::done($out);
			break;
			case 'getcms':
				$CMs = Cutiemarks::get($this->_appearance,false);
				$ProcessedCMs = Cutiemarks::get($this->_appearance);

				Response::done(['cms' => $CMs, 'preview' => Cutiemarks::getListForAppearancePage($ProcessedCMs, NOWRAP)]);
			break;
			case 'getcmpreview':
				$CMs = [];

				$CM1 = new Cutiemark([ 'appearance_id' => $this->_appearance->id ]);
				Cutiemarks::postProcess($CM1, 0);
				$CMs[] = $CM1;

				$CM2 = new Cutiemark([ 'appearance_id' => $this->_appearance->id ]);
				if (Cutiemarks::postProcess($CM2, 1))
					$CMs[] = $CM2;

				Cutiemarks::processSymmetrical($CMs);
				Response::done(['html' => Cutiemarks::getListForAppearancePage($CMs, NOWRAP)]);
			break;
			case 'setcms':
				/** @var $data Cutiemark[] */
				$data = [];
				$newFacingValues = [];
				for ($i = 0; $i < 2; $i++){
					if (isset($_POST['cmid'][$i])){
						if (!Cutiemark::exists($_POST['cmid'][$i]))
							Response::fail('The cutie mark you\'re trying to update does not exist');

						$cm = Cutiemark::find($_POST['cmid'][$i]);
					}
					else $cm = new Cutiemark([
						'appearance_id' => $this->_appearance->id,
					]);
					if (Cutiemarks::postProcess($cm, $i) === false)
						break;

					$newFacingValues[] = $cm->facing;
					$data[$i] = $cm;
				}

				$CurrentCMs = Cutiemarks::get($this->_appearance);
				$usedFacingValues = [];
				if (!empty($CurrentCMs)){
					foreach ($CurrentCMs as $cm)
						$usedFacingValues[$cm->facing] = $cm->cmid;
				}
				$newfacing = implode(',',$newFacingValues);
				if (!in_array($newfacing,Cutiemarks::VALID_FACING_COMBOS))
					Response::fail("The used combination of facing values ($newfacing) is not allowed");

				$cleanRendered = [];
				if (!in_array('left', $newFacingValues))
					$cleanRendered[] = CGUtils::CLEAR_CMDIR_LEFT;
				if (!in_array('right', $newFacingValues))
					$cleanRendered[] = CGUtils::CLEAR_CMDIR_RIGHT;
				if (!empty($cleanRendered))
					CGUtils::clearRenderedImages($this->_appearance->id, $cleanRendered);

				foreach ($data as $cmdata)
					$cmdata->save();

				$CutieMarks = Cutiemarks::get($this->_appearance, false);
				$olddata = Cutiemarks::convertDataForLogs($CurrentCMs);
				$newdata = Cutiemarks::convertDataForLogs($CutieMarks);
				if ($olddata !== $newdata)
					Logs::logAction('cm_modify',[
						'appearance_id' => $this->_appearance->id,
						'olddata' => $olddata,
						'newdata' => $newdata,
					]);

				$data = [];
				if ($this->_appearancePage && !empty($CutieMarks))
					$data['html'] = Cutiemarks::getListForAppearancePage($CutieMarks);
				Response::done($data);
			break;
			case 'delcms':
				$CMs = Cutiemarks::get($this->_appearance);
				if (empty($CMs))
					Response::done();
				foreach ($CMs as $cm)
					$cm->delete();

				Logs::logAction('cm_delete',[
					'appearance_id' => $this->_appearance->id,
					'data' => Cutiemarks::convertDataForLogs($CMs),
				]);

				Response::done();
			break;
			case 'clear-cache':
				if (!CGUtils::clearRenderedImages($this->_appearance->id))
					Response::fail('Cache could not be purged');

				if ($noResponse)
					return;

				Response::success('Cached images have been removed, they will be re-generated on the next request');
			break;
			case 'tag':
			case 'untag':
				if ($this->_appearance->owner_id !== null)
					Response::fail('Tagging is unavailable for appearances in personal guides');

				if ($this->_appearance->id === 0)
					Response::fail('This appearance cannot be tagged');

				switch ($action){
					case 'tag':
						$tag_name = CGUtils::validateTagName('tag_name');

						$TagCheck = CGUtils::checkEpisodeTagName($tag_name);
						if ($TagCheck !== false)
							$tag_name = $TagCheck;

						$Tag = Tags::getActual($tag_name, 'name');
						if (empty($Tag))
							Response::fail("The tag $tag_name does not exist.<br>Would you like to create it?", [
								'cancreate' => $tag_name,
								'typehint' => $TagCheck !== false ? 'ep' : null,
							]);
						if (Tagged::is($Tag, $this->_appearance))
							Response::fail('This appearance already has this tag');

						if (!Tagged::make($Tag->id, $this->_appearance->id)->save())
							Response::dbError();

					break;
					case 'untag':
						$tag_id = (new Input('tag','int', [
							Input::CUSTOM_ERROR_MESSAGES => [
								Input::ERROR_MISSING => 'Tag ID is missing',
								Input::ERROR_INVALID => 'Tag ID (@value) is invalid',
							]
						]))->out();
						$Tag = Tag::find($tag_id);
						if (empty($Tag))
							Response::fail('This tag does not exist');
						if ($Tag->synonym_of !== null)
							Response::fail('Synonym tags cannot be removed from appearances directly. '.
							        "If you want to remove this tag you must remove <strong>{$Tag->synonym->name}</strong> or the synonymization.");

						if (
							DB::$instance->where('appearance_id', $this->_appearance->id)->where('tag_id', $Tag->id)->has('tagged')
							&& !DB::$instance->where('appearance_id', $this->_appearance->id)->where('tag_id', $Tag->id)->delete('tagged')
						) Response::dbError();
					break;
				}

				Appearances::updateIndex($this->_appearance);

				Tags::updateUses($Tag->id);
				if (!empty(CGUtils::GROUP_TAG_IDS_ASSOC[$Tag->id]))
					Appearances::getSortReorder($this->_EQG);

				$response = ['tags' => Appearances::getTagsHTML($this->_appearance->id, NOWRAP)];
				if ($this->_appearancePage && $Tag->type === 'ep'){
					$response['needupdate'] = true;
					$response['eps'] = Appearances::getRelatedEpisodesHTML($this->_appearance, $this->_EQG);
				}
				Response::done($response);
			break;
			case 'applytemplate':
				try {
					Appearances::applyTemplate($this->_appearance->id, $this->_EQG);
				}
				catch (\Exception $e){
					Response::fail('Applying the template failed. Reason: '.$e->getMessage());
				}

				Response::done(['cgs' => Appearances::getColorsHTML($this->_appearance, NOWRAP, !$this->_appearancePage, $this->_appearancePage)]);
			break;
			case 'selectiveclear':
				$wipe_cache = (new Input('wipe_cache','bool',[
					Input::IS_OPTIONAL => true,
				]))->out();
				if ($wipe_cache)
					$this->_execAppearanceAction('clear-cache',null,true);

				$wipe_sprite = (new Input('wipe_sprite','bool',[
					Input::IS_OPTIONAL => true,
				]))->out();
				if ($wipe_sprite)
					$this->_execAppearanceAction('delsprite',null,true);

				$wipe_colors = (new Input('wipe_colors','string',[
					Input::IS_OPTIONAL => true,
				]))->out();
				switch ($wipe_colors){
					case 'color_hex':
						if (Appearances::hasColors($this->_appearance, true)){
							if (!DB::$instance->query('UPDATE colors SET hex = null WHERE group_id IN (SELECT id FROM color_groups WHERE appearance_id = ?)', [$this->_appearance->id]))
								Response::dbError();
						}
					break;
					case 'color_all':
						if (Appearances::hasColors($this->_appearance)){
							if (!DB::$instance->query('DELETE FROM colors WHERE group_id IN (SELECT id FROM color_groups WHERE appearance_id = ?)', [$this->_appearance->id]))
								Response::dbError();
						}
					break;
					case 'all':
						if (ColorGroup::exists(['conditions' => ['appearance_id = ?', $this->_appearance->id]])){
							if (!DB::$instance->query('DELETE FROM color_groups WHERE appearance_id = ?', [$this->_appearance->id]))
								Response::dbError();
						}
					break;
				}

				if (empty($this->_appearance->owner_id)){
					$wipe_tags = (new Input('wipe_tags','bool',[
						Input::IS_OPTIONAL => true,
					]))->out();
					if ($wipe_tags && !empty($this->_appearance->tagged)){
						if (!DB::$instance->where('appearance_id', $this->_appearance->id)->delete('tagged'))
							Response::dbError('Failed to wipe tags');
						foreach ($this->_appearance->tagged as $tag)
							Tags::updateUses($tag->tag_id);
					}
				}

				$update = ['last_cleared' => date('c')];

				$wipe_notes = (new Input('wipe_notes','bool',[
					Input::IS_OPTIONAL => true,
				]))->out();
				if ($wipe_notes)
					$update['notes'] = null;

				$mkpriv = (new Input('mkpriv','bool',[
					Input::IS_OPTIONAL => true,
				]))->out();
				if ($mkpriv)
					$update['private'] = 1;

				if (!empty($update))
					DB::$instance->where('id', $this->_appearance->id)->update('appearances',$update);

				Response::done();
			break;
			default: CoreUtils::notFound();
		}
	}

	public function recountTagUses(){
		if (Permission::insufficient('staff'))
			Response::fail();

		$tagIDs = (new Input('tagids','int[]', [
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Missing list of tags to update',
				Input::ERROR_INVALID => 'List of tags is invalid',
			]
		]))->out();
		$counts = [];
		$updates = 0;
		foreach ($tagIDs as $tid){
			if (Tags::getActual($tid,'id',RETURN_AS_BOOL)){
				$result = Tags::updateUses($tid, true);
				if ($result['status'])
					$updates++;
				$counts[$tid] = $result['count'];
			}
		}

		Response::success(
			(
				!$updates
				? 'There was no change in the tag usage counts'
				: "$updates tag".($updates!==1?"s'":"'s").' use count'.($updates!==1?'s were':' was').' updated'
			),
			['counts' => $counts]
		);
	}

	public function tagAction($params){
		$this->_initialize($params);
		if (Permission::insufficient('staff'))
			CoreUtils::notFound();

		$action = $params['action'];
		$adding = $action === 'make';

		if (!$adding){
			if (!isset($params['id']))
				Response::fail('Missing tag ID');
			$TagID = intval($params['id'], 10);
			$Tag = Tag::find($TagID);
			if (empty($Tag))
				Response::fail('This tag does not exist');
		}

		$data = [];

		switch ($action){
			case 'get':
				Response::done($Tag->to_array());
			case 'del':
				$AppearanceID = Appearances::validateAppearancePageID();

				$tid = $Tag->synonym_of ?? $Tag->id;
				$Uses = Tagged::by_tag($tid);
				$UseCount = count($Uses);
				if (!isset($_POST['sanitycheck']) && $UseCount > 0)
					Response::fail('<p>This tag is currently used on '.CoreUtils::makePlural('appearance',$UseCount,PREPEND_NUMBER).'</p><p>Deleting will <strong class="color-red">permanently remove</strong> the tag from those appearances!</p><p>Are you <em class="color-red">REALLY</em> sure about this?</p>', ['confirm' => true]);

				$Tag->delete();

				if (!empty(CGUtils::GROUP_TAG_IDS_ASSOC[$Tag->id]))
					Appearances::getSortReorder($this->_EQG);
				foreach ($Uses as $use)
					Appearances::updateIndex($use->appearance);

				if ($AppearanceID !== null && $Tag->type === 'ep'){
					$Appearance = Appearance::find($AppearanceID);
					$resp = [
						'needupdate' => true,
						'eps' => Appearances::getRelatedEpisodesHTML($Appearance, $this->_EQG),
					];
				}
				else $resp = null;
				Response::success('Tag deleted successfully', $resp);
			break;
			case 'unsynon':
				if ($Tag->synonym_of === null)
					Response::done();

				$keep_tagged = isset($_POST['keep_tagged']);
				$uses = 0;
				if (!empty($Tag->synonym)){
					$TargetTagged = Tagged::by_tag($Tag->synonym->id);
					if ($keep_tagged){
						foreach ($TargetTagged as $tg){
							if (!Tagged::make($Tag->id, $tg->appearance_id)->save())
								Response::fail('Tag synonym removal process failed, please re-try.<br>Technical details: '.$tg->to_json());
							$uses++;
						}
					}
					else {
						foreach ($TargetTagged as $tg)
							Appearances::updateIndex($tg->appearance);
					}
				}
				else $keep_tagged = false;

				if (!$Tag->update_attributes(['synonym_of' => null, 'uses' => $uses]))
					Response::dbError('Could not update tag');

				Response::done(['keep_tagged' => $keep_tagged]);
			break;
			case 'make':
			case 'set':
				$data['name'] = CGUtils::validateTagName('name');

				$epTagName = CGUtils::checkEpisodeTagName($data['name']);
				$surelyAnEpisodeTag = $epTagName !== false;
				$type = (new Input('type',function($value){
					if (!isset(Tags::TAG_TYPES[$value]))
						return Input::ERROR_INVALID;
				}, [
					Input::IS_OPTIONAL => true,
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_INVALID => 'Invalid tag type: @value',
					]
				]))->out();
				if (empty($type)){
					if ($surelyAnEpisodeTag)
						$data['name'] = $epTagName;
					$data['type'] = $epTagName === false ? null : 'ep';
				}
				else {
					if ($type === 'ep'){
						if (!$surelyAnEpisodeTag){
							$errmsg = <<<HTML
Episode tags must be in one of the following formats:
<ol>
<li>
	<code>s<var>S</var>e<var>E<sub>1</sub></var>[-<var>E<sub>2</sub></var>]</code> where
	<ul>
		<li><var>S</var> ∈ <var>{1, 2, 3, &hellip; 8}</var></li>
		<li><var>E<sub>1</sub></var>, <var>E<sub>2</sub></var> ∈ <var>{1, 2, 3, &hellip; 26}</var></li>
		<li>if specified: <var>E<sub>1</sub></var>+1 = <var>E<sub>2</sub></var></li>
	</ul>
</li>
<li>
	<code>movie<var>M</var></code> where <var>M</var> ∈ <var>&#x2124;<sup>+</sup></var>
</li>
</ol>
HTML;

							Response::fail($errmsg);
						}
						$data['name'] = $epTagName;
					}
					else if ($surelyAnEpisodeTag)
						$type = 'ep';
					$data['type'] = $type;
				}

				if (!$adding)
					DB::$instance->where('id', $Tag->id,'!=');
				if (DB::$instance->where('name', $data['name'])->where('type', $data['type'])->has('tags'))
					Response::fail('A tag with the same name and type already exists');

				$data['title'] = (new Input('title','string', [
					Input::IS_OPTIONAL => true,
					Input::IN_RANGE => [null,255],
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_RANGE => 'Tag title cannot be longer than @max characters'
					]
				]))->out();

				if ($adding){
					$Tag = new Tag($data);
					if (!$Tag->save())
						Response::dbError();

					$AppearanceID = (new Input('addto','int', [Input::IS_OPTIONAL => true]))->out();
					if ($AppearanceID !== null){
						if ($AppearanceID === 0)
							Response::success('The tag was created, <strong>but</strong> it could not be added to the appearance because it can’t be tagged.');

						$Appearance = Appearance::find($AppearanceID);
						if (empty($Appearance))
							Response::success("The tag was created, <strong>but</strong> it could not be added to the appearance (<a href='/cg/v/$AppearanceID'>#$AppearanceID</a>) because it doesn’t seem to exist. Please try adding the tag manually.");

						if (!Tagged::make($Tag->id, $Appearance->id)->save()) Response::dbError();
						Appearances::updateIndex($Appearance);
						Tags::updateUses($Tag->id);
						$r = ['tags' => Appearances::getTagsHTML($Appearance->id, NOWRAP)];
						if ($this->_appearancePage){
							$r['needupdate'] = true;
							$r['eps'] = Appearances::getRelatedEpisodesHTML($Appearance, $this->_EQG);
						}
						Response::done($r);
					}
				}
				else {
					$Tag->update_attributes($data);
					$data = $Tag->to_array();
					$AppearanceID = !empty($this->_appearancePage) ? (int) $_POST['APPEARANCE_PAGE'] : null;
					$tagrelations = Tagged::by_tag($Tag->id);
					foreach ($tagrelations as $tagged){
						Appearances::updateIndex($tagged->appearance);

						if ($tagged->appearance_id === $AppearanceID){
							$data['needupdate'] = true;
							$Appearance = Appearance::find($AppearanceID);
							$data['eps'] = Appearances::getRelatedEpisodesHTML($Appearance, $this->_EQG);
						}
					}
				}

				Response::done($data);
			break;
		}

		// TODO Untangle spaghetti
		$merging = $action === 'merge';
		$synoning = $action === 'synon';
		if ($merging || $synoning){
			if ($synoning && $Tag->synonym_of !== null)
				Response::fail('The selected tag is already a synonym of the "'.$Tag->synonym->name.'" ('.Tags::TAG_TYPES[$Tag->synonym->type].') tag');

			$targetid = (new Input('targetid','int', [
				Input::CUSTOM_ERROR_MESSAGES => [
					Input::ERROR_MISSING => 'Missing target tag ID',
				]
			]))->out();
			$Target = Tag::find($targetid);
			if (empty($Target))
				Response::fail('Target tag does not exist');
			if ($Target->synonym_of !== null)
				Response::fail('The selected tag is already a synonym of the "'.$Target->synonym->name.'" ('.Tags::TAG_TYPES[$Target->synonym->type].') tag');

			$TargetTagged = Tagged::by_tag($Target->id);
			$TaggedAppearanceIDs = [];
			foreach ($TargetTagged as $tg)
				$TaggedAppearanceIDs[] = $tg->appearance_id;

			$Tagged = Tagged::by_tag($Tag->id);
			foreach ($Tagged as $tg){
				if (in_array($tg->appearance_id, $TaggedAppearanceIDs, true))
					continue;

				if (!Tagged::make($Target->id, $tg->appearance_id)->save())
					Response::fail('Tag '.($merging?'merging':'synonimizing').' failed, please re-try.<br>Technical details: '.$tg->to_json());
			}
			if ($merging)
				// No need to delete "tagged" table entries, constraints do it for us
				$Tag->delete();
			else {
				Tagged::delete_all(['conditions' => ['tag_id = ?', $Tag->id]]);
				$Tag->update_attributes([
					'synonym_of' => $Target->id,
					'uses' => 0,
				]);
			}
			foreach ($TaggedAppearanceIDs as $id)
				Appearances::updateIndex($id);

			Tags::updateUses($Target->id);
			Response::success('Tags successfully '.($merging?'merged':'synonymized'), $synoning || $merging ? ['target' => $Target] : null);
		}

		CoreUtils::notFound();
	}

	public function colorGroupAction($params){
		global $HEX_COLOR_REGEX;

		$this->_initPersonal($params, false);
		if (!$this->_isOwnedByUser && Permission::insufficient('staff'))
			Response::fail();

		$action = $params['action'];
		$adding = $action === 'make';

		if (!$adding){
			if (empty($params['id']))
				Response::fail('Missing color group ID');
			$GroupID = intval($params['id'], 10);
			$Group = ColorGroup::find($GroupID);
			if (empty($Group))
				Response::fail("There’s no color group with the ID of $GroupID");

			if ($action === 'get'){
				$out = $Group->to_array();
				$out['Colors'] = [];
				foreach ($Group->colors as $c)
					$out['Colors'][] = $c->to_array([
						'except' => 'group_id',
					]);
				Response::done($out);
			}

			if ($action === 'del'){
				$Group->delete();

				Logs::logAction('cgs', [
					'action' => 'del',
					'group_id' => $Group->id,
					'appearance_id' => $Group->appearance_id,
					'label' => $Group->label,
					'order' => $Group->order,
				]);

				Response::success('Color group deleted successfully');
			}
		}
		else $Group = new ColorGroup();

		if (!$adding)
			$oldlabel = $Group->label;
		$label = (new Input('label','string', [
			Input::IN_RANGE => [2,30],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Color group label is missing',
				Input::ERROR_RANGE => 'Color group label must be between @min and @max characters long',
			]
		]))->out();
		CoreUtils::checkStringValidity($label, 'Color group label', INVERSE_PRINTABLE_ASCII_PATTERN, true);
		$Group->label = $label;
		$major = isset($_POST['major']);
		if ($major){
			$reason = (new Input('reason','string', [
				Input::IN_RANGE => [null,255],
				Input::CUSTOM_ERROR_MESSAGES => [
					Input::ERROR_MISSING => 'Please specify a reason for the changes',
					Input::ERROR_RANGE => 'The reason cannot be longer than @max characters',
				],
			]))->out();
			CoreUtils::checkStringValidity($reason, 'Change reason', INVERSE_PRINTABLE_ASCII_PATTERN);
		}

		if ($adding){
			$ponyid = (new Input('ponyid','int', [
				Input::CUSTOM_ERROR_MESSAGES => [
					Input::ERROR_MISSING => 'Missing appearance ID',
				]
			]))->out();
			$params['id'] = $ponyid;
			$this->_getAppearance($params);
			$Group->appearance_id = $ponyid;
		}

		$Group->save();

		$oldcolors = $adding ? null : $Group->colors;

		$recvColors = (new Input('Colors','json', [
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Missing list of colors',
				Input::ERROR_INVALID => 'List of colors is invalid',
			]
		]))->out();
		$newcolors = [];
		foreach ($recvColors as $part => $c){
			$append = new Color([
				'group_id' => $Group->id,
				'order' => $part,
			]);
			$index = "(index: $part)";

			if (empty($c['label']))
				Response::fail("You must specify a color name $index");
			$label = CoreUtils::trim($c['label']);
			CoreUtils::checkStringValidity($label, "Color $index name", INVERSE_PRINTABLE_ASCII_PATTERN);
			$ll = CoreUtils::length($label);
			if ($ll < 3 || $ll > 30)
				Response::fail("The color name must be between 3 and 30 characters in length $index");
			$append->label = $label;

			if (empty($c['hex']))
				Response::fail("You must specify a color code $index");
			$hex = CoreUtils::trim($c['hex']);
			if (!$HEX_COLOR_REGEX->match($hex, $_match))
				Response::fail("HEX color is in an invalid format $index");
			$append->hex = '#'.strtoupper($_match[1]);

			$newcolors[] = $append;
		}
		// Don't wipe colors until the ones provided pass validation
		if (!$adding)
			$Group->wipeColors();
		$colorError = false;
		foreach ($newcolors as $c){
			if ($c->save())
				continue;

			$colorError = true;
			error_log('Database error triggered by user '.Auth::$user->name.' ('.Auth::$user->id.") while saving colors:\n".JSON::encode($c->errors, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
		}
		unset($c);
		/** @var $newcolors Color[] */
		if ($colorError)
			Response::fail("There were some issues while saving the colors. Please <a class='send-feedback'>let us know</a> about this error, so we can look into why it might've happened.");

		$colon = !$this->_appearancePage;
		$outputNames = $this->_appearancePage;

		if ($adding) $response = ['cgs' => Appearances::getColorsHTML($this->_appearance, NOWRAP, $colon, $outputNames)];
		else $response = ['cg' => $Group->getHTML(null, NOWRAP, $colon, $outputNames)];

		if ($major){
			Logs::logAction('major_changes', [
				'appearance_id' => $Group->appearance_id,
				'reason' => $reason,
			]);
			if ($this->_appearancePage){
				$FullChangesSection = isset($_POST['FULL_CHANGES_SECTION']);
				$response['changes'] = CGUtils::getChangesHTML(MajorChange::get($Group->appearance_id), $FullChangesSection);
				if ($FullChangesSection)
					$response['changes'] = str_replace('@',$response['changes'],CGUtils::CHANGES_SECTION);
			}
			else $response['update'] = Appearances::getUpdatesHTML($Group->appearance_id);
		}
		CGUtils::clearRenderedImages($Group->appearance_id, [CGUtils::CLEAR_PALETTE, CGUtils::CLEAR_PREVIEW]);

		if (isset($_POST['APPEARANCE_PAGE']))
			$response['cm_img'] = "/cg/v/{$Group->appearance_id}.svg?t=".time();
		else $response['notes'] = Appearance::find($Group->appearance_id)->getNotesHTML(NOWRAP);

		$logdata = [];
		if ($adding) Logs::logAction('cgs', [
			'action' => 'add',
			'group_id' => $Group->id,
			'appearance_id' => $Group->appearance_id,
			'label' => $Group->label,
			'order' => $Group->order,
		]);
		else if ($Group->label !== $oldlabel){
			$logdata['oldlabel'] = $oldlabel;
			$logdata['newlabel'] = $Group->label;
		}

		$oldcolorstr = CGUtils::stringifyColors($oldcolors);
		$newcolorstr = CGUtils::stringifyColors($newcolors);
		$colorsChanged = $oldcolorstr !== $newcolorstr;
		if ($colorsChanged){
			$logdata['oldcolors'] = $oldcolorstr;
			$logdata['newcolors'] = $newcolorstr;
		}
		if (!empty($logdata)){
			$logdata['group_id'] = $Group->id;
			$logdata['appearance_id'] = $Group->appearance_id;
			Logs::logAction('cg_modify', $logdata);
		}

		Response::done($response);
	}

	public function blending(){
		global $HEX_COLOR_REGEX;

		CoreUtils::fixPath('/cg/blending');

		$HexPattern = preg_replace(new RegExp('^/(.*)/.*$'),'$1',$HEX_COLOR_REGEX->jsExport());
		CoreUtils::loadPage([
			'title' => 'Color Blending Calculator',
			'view' => "{$this->do}-blending",
			'css' => "{$this->do}-blending",
			'js' => "{$this->do}-blending",
			'import' => [
				'HexPattern' => $HexPattern,
				'nav_blending' => true,
			],
		]);
	}

	public function picker(){
		CoreUtils::loadPage([
			'title' => 'Color Picker',
			'view' => "{$this->do}-picker",
			'css' => "{$this->do}-picker",
			'import' => ['nav_picker' => true],
		]);
	}

	public function pickerFrame(){
		header('Content-Type: text/html; charset=utf-8;');
		include INCPATH.'views/colorpicker.php';
	}

	public function getSpriteColors($params){
		$this->_getAppearance($params);

		if (empty($this->_appearance))
			Response::fail('Could not find appearance');

		$DefaultMapping = [
			'Coat Outline' => '#443633',
			'Coat Shadow Outline' => '#404433',
			'Coat Fill' => '#70605D',
			'Coat Shadow Fill' => '#6C7260',
			'Eyes Top' => '#3B3B3B',
			'Eyes Middle' => '#606060',
			'Eyes Bottom' => '#BEBEBE',
			'Eyes Highlight Top' => '#542727',
			'Eyes Highlight Bottom' => '#7E3A3A',
			'Magic Aura' => '#B7B7B7',
		];

		$ColorMappings = CGUtils::getColorMapping($this->_appearance->id, $DefaultMapping);

		$colors = [];
		foreach ($DefaultMapping as $k => $v){
			if (!isset($ColorMappings[$k]))
				continue;

			$colors[$v] = $ColorMappings[$k];
		}

		Response::done(['colors' => $colors]);
	}
}
