<?php

namespace App\Controllers;
use ActiveRecord\Table;
use App\Auth;
use App\CGUtils;
use App\CoreUtils;
use App\CSRFProtection;
use App\Cutiemarks;
use App\DB;
use App\Exceptions\MismatchedProviderException;
use App\Exceptions\NoPCGSlotsException;
use App\File;
use App\ImageProvider;
use App\Input;
use App\JSON;
use App\Logs;
use App\Models\Appearance;
use App\Models\CachedDeviation;
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
use App\UploadedFile;
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
	public function _getAppearance($params, $set_properties = true){
		$asFile = isset($params['ext']);
		if (!isset($params['id']))
			Response::fail('Missing appearance ID');
		$this->_appearance = Appearance::find($params['id']);
		if (empty($this->_appearance))
			CoreUtils::notFound();
		if (!$set_properties)
			return;

		if ($this->_appearance->owner_id === null){
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
			$this->_isOwnedByUser = Auth::$signed_in && $this->_appearance->owner_id === Auth::$user->id;
		}
	}

	public function sprite($params){
		if (Permission::insufficient('member'))
			CoreUtils::noPerm();

		$this->_getAppearance($params);
		if ($this->_appearance->owner_id != Auth::$user->id && Permission::insufficient('staff'))
			CoreUtils::noPerm();

		if ($this->_appearance->owner_id !== null)
			$params['name'] = $this->_appearance->owner->name;
		$this->_initPersonal($params, false);

		$Map = CGUtils::getSpriteImageMap($this->_appearance->id);
		if (empty($Map))
			CoreUtils::notFound();

		[$Colors,$ColorGroups,$AllColors] = $this->_appearance->getSpriteRelevantColors();

		$SafeLabel = $this->_appearance->getSafeLabel();
		CoreUtils::fixPath("{$this->_cgPath}/sprite/{$this->_appearance->id}-$SafeLabel");

		CoreUtils::loadPage(__METHOD__, [
			'title' => "Sprite of {$this->_appearance->label}",
			'css' => [true],
			'js' => [true],
			'import' => [
				'Appearance' => $this->_appearance,
				'ColorGroups' => $ColorGroups,
				'Colors' => $Colors,
				'AllColors' => $AllColors,
				'Map' => $Map,
				'Owner' => $this->_appearance->owner,
			],
		]);
	}

	public function appearanceAsFile($params, User $Owner = null){
		if ($Owner === null)
			$this->_initialize($params);
		$this->_getAppearance($params);

		switch ($params['ext']){
			case 'png':
				switch ($params['type']){
					case 's': CGUtils::renderSpritePNG($this->_cgPath, $this->_appearance->id, $_GET['s'] ?? null);
					case 'p':
					default: CGUtils::renderAppearancePNG($this->_cgPath, $this->_appearance);
				}
			break;
			case 'svg':
				if (!empty($params['type'])) switch ($params['type']){
					case 's': CGUtils::renderSpriteSVG($this->_cgPath, $this->_appearance->id);
					case 'p': CGUtils::renderPreviewSVG($this->_cgPath, $this->_appearance);
					case 'f': CGUtils::renderCMFacingSVG($this->_cgPath, $this->_appearance);
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

		$this->appearanceAsFile($params, $this->_ownedBy);
	}

	private const GUIDE_MANAGE_JS = [
		'jquery.uploadzone',
		'jquery.autocomplete',
		'handlebars-v3.0.3',
		'Sortable',
		'pages/colorguide/tag-list',
		'pages/colorguide/manage',
	];
	private const GUIDE_MANAGE_CSS = [
		'pages/colorguide/manage',
	];

	public function appearance($params){
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
			'css' => ['pages/colorguide/guide', true],
			'js' => ['jquery.qtip', 'jquery.ctxmenu', 'pages/colorguide/guide', true],
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
		if ($this->_isOwnedByUser || Permission::sufficient('staff')){
			$settings['css'] = array_merge($settings['css'], self::GUIDE_MANAGE_CSS);
			$settings['js'] = array_merge($settings['js'], self::GUIDE_MANAGE_JS);
		}
		CoreUtils::loadPage(__METHOD__, $settings);
	}

	public function personalAppearancePage($params){
		$this->_initPersonal($params);

		$this->appearance($params);
	}

	public function fullList($params){
		$this->_initialize($params);

		$GuideOrder = !isset($_REQUEST['alphabetically']);
		if (!$GuideOrder)
			DB::$instance->orderBy('label');
		$Appearances = Appearances::get($this->_EQG,null,null,'id,label,private');

		if (isset($_REQUEST['ajax'])){
			CoreUtils::detectUnexpectedJSON();

			Response::done(['html' => CGUtils::getFullListHTML($Appearances, $GuideOrder, $this->_EQG, NOWRAP)]);
		}

		$js = [];
		if (Permission::sufficient('staff'))
			$js[] = 'Sortable';
		$js[] = true;

		CoreUtils::loadPage(__METHOD__, [
			'title' => 'Full List - '.($this->_EQG?'EQG ':'').'Color Guide',
			'css' => [true],
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

		Response::done(['html' => CGUtils::getFullListHTML(Appearances::get($this->_EQG,null,null,'id,label'), true, $this->_EQG, NOWRAP)]);
	}

	public function changeList(){
		$Pagination = new Pagination('cg/changes', 50, MajorChange::count());

		CoreUtils::fixPath("/cg/changes/{$Pagination->page}");
		$heading = 'Major Color Changes';
		$title = "Page $Pagination->page - $heading - Color Guide";

		$Changes = MajorChange::get(null, $Pagination->getLimitString());

		$Pagination->respondIfShould(CGUtils::getChangesHTML($Changes, NOWRAP, SHOW_APPEARANCE_NAMES), '#changes');

		CoreUtils::loadPage(__METHOD__, [
			'title' => $title,
			'heading' => $heading,
			'css' => [true],
			'js' => ['paginate'],
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
			$js[] = true;

		CoreUtils::loadPage(__METHOD__, [
			'title' => $title,
			'heading' => $heading,
			'css' => [true],
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
		$searching = !empty($_GET['q']) && CoreUtils::length(CoreUtils::trim($_GET['q'])) > 0;
		$jsResponse = CoreUtils::isJSONExpected();
		if ($elasticAvail){
			$search = new ElasticsearchDSL\Search();
			$inOrder = true;
		    $Pagination = new Pagination(ltrim($this->_cgPath, '/'), $AppearancesPerPage);

			// Search query exists
			if ($searching){
				$SearchQuery = preg_replace(new RegExp('[^\w\s*?]'),'',CoreUtils::trim($_GET['q']));
				$title .= "$SearchQuery - ";
				$multiMatch = new ElasticsearchDSL\Query\FullText\MultiMatchQuery(
					['label','tags'],
					$SearchQuery,
					[
						'type' => 'cross_fields',
						'minimum_should_match' => '100%',
					]
				);
				$search->addQuery($multiMatch);
				$score = new FunctionScoreQuery(new MatchAllQuery());
				$score->addFieldValueFactorFunction('order',1.5);
				$score->addParameter('boost_mode','sum');
				$search->addQuery($score);
				$sort = new ElasticsearchDSL\Sort\FieldSort('_score', 'asc');
				$search->addSort($sort);
				$inOrder = false;
			}
			else {
				$sort = new ElasticsearchDSL\Sort\FieldSort('order', 'asc');
				$search->addSort($sort);
			}

			$boolquery = new BoolQuery();
			if (Permission::insufficient('staff'))
				$boolquery->add(new TermQuery('private', false), BoolQuery::MUST);
			$boolquery->add(new TermQuery('ishuman', $this->_EQG), BoolQuery::MUST);
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

			if (!empty($search)){
				$Pagination->calcMaxPages($search['hits']['total']);
				if (!empty($search['hits']['hits'])){
					$ids = [];
					/** @noinspection ForeachSourceInspection */
					foreach($search['hits']['hits'] as $i => $hit)
						$ids[$hit['_id']] = $i;

					if ($inOrder)
						DB::$instance->orderBy('order');
					DB::$instance->where('id', array_keys($ids));
					$Ponies = Appearances::get($this->_EQG);
					if (!empty($Ponies) && !$inOrder)
						uasort($Ponies, function(Appearance $a, Appearance $b) use ($ids){
							return $ids[$a->id] <=> $ids[$b->id];
						});
				}
			}
		}
		else {
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
			Response::done(['goto' => $Ponies[0]->toURL()]);
		}

		CoreUtils::fixPath("$this->_cgPath/{$Pagination->page}?q=".(!empty($SearchQuery) ? $SearchQuery : CoreUtils::FIXPATH_EMPTY));
		$heading = ($this->_EQG?'EQG ':'').'Color Guide';
		$title .= "Page {$Pagination->page} - $heading";

		$Pagination->respondIfShould(Appearances::getHTML($Ponies, NOWRAP), '#list');

		$settings = [
			'title' => $title,
			'heading' => $heading,
			'css' => [true],
			'js' => ['jquery.qtip', 'jquery.ctxmenu', true, 'paginate'],
			'import' => [
				'EQG' => $this->_EQG,
				'Ponies' => $Ponies,
				'Pagination' => $Pagination,
				'elasticAvail' => $elasticAvail,
			],
		];
		if (Permission::sufficient('staff')){
			$settings['css'] = array_merge($settings['css'], self::GUIDE_MANAGE_CSS);
			$settings['js'] = array_merge($settings['js'], self::GUIDE_MANAGE_JS);
		}
		CoreUtils::loadPage(__METHOD__, $settings);
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
			'css' => ['pages/colorguide/guide'],
			'js' => ['jquery.qtip', 'jquery.ctxmenu', 'pages/colorguide/guide', 'paginate'],
			'import' => [
				'Ponies' => $Ponies,
				'Pagination' => $Pagination,
				'User' => $this->_ownedBy,
				'isOwner' => $this->_isOwnedByUser,
			],
		];
		if ($this->_isOwnedByUser || Permission::sufficient('staff')){
			$settings['css'] = array_merge($settings['css'], self::GUIDE_MANAGE_CSS);
			$settings['js'] = array_merge($settings['js'], self::GUIDE_MANAGE_JS);
		}
		CoreUtils::loadPage('UserController::colorGuide', $settings);
	}

	const CM_BASIC_COLS = 'id,favme,favme_rotation,preview_src,facing';
	public function export(){
		if (Permission::insufficient('developer'))
			CoreUtils::noPerm();
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
			$AppendAppearance = [
				'id'      => $p->id,
				'order'   => $p->order,
				'label'   => $p->label,
				'notes'   => $p->notes_src === null ? '' : CoreUtils::trim($p->notes_src,true),
				'ishuman' => $p->ishuman,
				'added'   => gmdate('Y-m-d\TH:i:s\Z',$p->added->getTimestamp()),
				'private' => $p->private,
			];

			$CMs = Cutiemarks::get($p);
			if (!empty($CMs)){
				$AppendCMs = [];
				foreach ($CMs as $CM){
					$arr = [
						'facing' => $CM->facing,
						'svg' => $CM->getRenderedRelativeURL(),
					];
					if ($CM->favme !== null)
						$arr['source'] = "http://fav.me/{$CM->favme}";
					if ($CM->contributor_id !== null)
						$arr['contributor'] = $CM->contributor->toDALink();
					$AppendCMs[$CM->id] = $arr;
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
									'except' => ['id', 'group_id', 'linked_to'],
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

		$data = JSON::encode($JSON);
		$data = preg_replace_callback('/^\s+/m', function($match){
			return str_pad('',CoreUtils::length($match[0])/4,"\t", STR_PAD_LEFT);
		}, $data);

		CoreUtils::downloadAsFile($data, 'mlpvc-colorguide.json');
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
			$TagCheck = CGUtils::normalizeEpisodeTagName($query);
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
					'notes' => $this->_appearance->notes_src,
					'private' => $this->_appearance->private,
				]);
			break;
			case 'set':
			case 'make':
				/** @var $data array */
				$data = [
					'ishuman' => $this->_personalGuide ? null : $this->_EQG,
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
					Response::fail("An appearance <a href='{$dupe->toURL()}' target='_blank'>already esists</a> in the ".($this->_EQG?'EQG':'Pony').' guide with this exact name. Consider adding an identifier in backets or choosing a different name.');
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
				if ($notes !== null){
					CoreUtils::checkStringValidity($notes, 'Appearance notes', INVERSE_PRINTABLE_ASCII_PATTERN);
					if ($creating || $notes !== $this->_appearance->notes_src)
						$data['notes_src'] = $notes;
				}
				else $data['notes_src'] = null;

				$data['private'] = isset($_POST['private']);

				if ($creating){
					if ($this->_personalGuide || Permission::insufficient('staff')){
						$data['owner_id'] = Auth::$user->id;
						$ownerName = Auth::$user->name;
					}
					if (empty($data['owner_id'])){
						$biggestOrder = DB::$instance->disableAutoClass()->where('ishuman', $data['ishuman'])->getOne('appearances','MAX("order") as "order"');
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
						'goto' => $newAppearance->toURL(),
					];
					$usetemplate = isset($_POST['template']);
					if ($usetemplate){
						try {
							$newAppearance->applyTemplate();
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
						'notes' => $newAppearance->notes_src,
						'ishuman' => $newAppearance->ishuman,
						'usetemplate' => $usetemplate,
						'private' => $newAppearance->private,
						'owner_id' => $newAppearance->owner_id,
					]);
					Response::done($response);
				}

				$this->_appearance->clearRenderedImages([Appearance::CLEAR_PALETTE, Appearance::CLEAR_PREVIEW]);

				if (!$creating){
					$diff = [];
					foreach (['label' => true, 'notes_src' => 'notes', 'private' => true, 'owner_id' => true] as $orig => $mapped){
						$key = $mapped === true ? $orig : $mapped;
						if ($EditedAppearance->{$orig} !== $olddata[$orig]){
							$diff["old$key"] = $olddata[$orig];
							$diff["new$key"] = $EditedAppearance->{$orig};
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
						$response['newurl'] = $EditedAppearance->toURL();
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
					CoreUtils::elasticClient()->delete($this->_appearance->toElasticArray(true));
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
					foreach($Tagged as $tag)
						Tags::updateUses($tag->id);

				$fpath = SPRITE_PATH."{$this->_appearance->id}.png";
				CoreUtils::deleteFile($fpath);

				$this->_appearance->clearRenderedImages();

				Logs::logAction('appearances', [
					'action' => 'del',
					'id' => $this->_appearance->id,
					'order' => $this->_appearance->order,
					'label' => $this->_appearance->label,
					'notes' => $this->_appearance->notes_src,
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
				/** @var $order int[] */
				$order = (new Input('cgs','int[]', [
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Color group order data missing',
						Input::ERROR_INVALID => 'Color group order data (@value) is invalid',
					]
				]))->out();
				$oldCGs = DB::$instance->where('appearance_id', $this->_appearance->id)->get('color_groups');
				$possibleIDs = [];
				foreach ($oldCGs as $cg)
					$possibleIDs[$cg->id] = true;
				foreach ($order as $i => $GroupID){
					if (empty($possibleIDs[$GroupID]))
						Response::fail("There’s no group with the ID of $GroupID on this appearance");

					DB::$instance->where('id', $GroupID)->update('color_groups', ['order' => $i]);
				}
				Table::clear_cache();
				$newCGs = DB::$instance->where('appearance_id', $this->_appearance->id)->get('color_groups');

				$this->_appearance->clearRenderedImages([Appearance::CLEAR_PALETTE, Appearance::CLEAR_PREVIEW]);

				$oldCGs = CGUtils::stringifyColorGroups($oldCGs);
				$newCGs = CGUtils::stringifyColorGroups($newCGs);
				if ($oldCGs !== $newCGs) Logs::logAction('cg_order', [
					'appearance_id' => $this->_appearance->id,
					'oldgroups' => $oldCGs,
					'newgroups' => $newCGs,
				]);

				Response::done(['cgs' => $this->_appearance->getColorsHTML(NOWRAP, !$this->_appearancePage, $this->_appearancePage)]);
			break;
			case 'delsprite':
			case 'getsprite':
			case 'setsprite':
				$finalpath = SPRITE_PATH.$this->_appearance->id.'.png';

				switch ($action){
					case 'setsprite':
						CGUtils::processUploadedImage('sprite', $finalpath, ['image/png'], [300], [700, 300]);
						$this->_appearance->clearRenderedImages();

						$this->_appearance->checkSpriteColors();
					break;
					case 'delsprite':
						if (empty($this->_appearance->getSpriteURL())){
							if ($noResponse)
								return;
							Response::fail('No sprite file found');
						}

						if (!CoreUtils::deleteFile($finalpath))
							Response::fail('File could not be deleted');
						$this->_appearance->clearRenderedImages();
						Appearances::clearSpriteColorIssueNotifications($this->_appearance->id, 'del', null);

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

				/** @var $AppearanceIDs int[] */
				$AppearanceIDs = (new Input('ids','int[]', [
					Input::IS_OPTIONAL => true,
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_INVALID => 'Appearance ID list is invalid',
					]
				]))->out();
				/** @var $MutualIDs int[] */
				$MutualIDs = (new Input('mutuals','int[]', [
					Input::IS_OPTIONAL => true,
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_INVALID => 'Mutial relation ID list is invalid',
					]
				]))->out();

				$appearances = [];
				if (!empty($AppearanceIDs))
					foreach ($AppearanceIDs as $id)
						$appearances[$id] = true;

				$mutuals = [];
				if (!empty($MutualIDs))
					foreach ($MutualIDs as $id)
						$mutuals[$id] = true;

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
				$CMs = Cutiemarks::get($this->_appearance);
				foreach ($CMs as &$CM)
					$CM = $CM->to_js_response();
				unset($CM);

				$ProcessedCMs = Cutiemarks::get($this->_appearance);

				Response::done(['cms' => $CMs, 'preview' => Cutiemarks::getListForAppearancePage($ProcessedCMs, NOWRAP)]);
			break;
			case 'setcms':
				$GrabCMs = Cutiemarks::get($this->_appearance);
				/** @var $CurrentCMs Cutiemark[] */
				$CurrentCMs = [];
				foreach ($GrabCMs as $cm)
					$CurrentCMs[$cm->id] = $cm;
				/** @var $data array */
				$data = (new Input('CMData','json',[
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Cutie mark data is missing',
						Input::ERROR_INVALID => 'Cutie mark data (@value) is invalid',
					]
				]))->out();
				if (count($data) > 4)
					Response::fail('Appearances can only have a maximum of 4 cutie marks.');
				/** @var $NewCMs Cutiemark[] */
				$NewCMs = [];
				$NewIDs = [];
				$labels = [];
				foreach ($data as $i => $item){
					if (isset($item['id'])){
						$cm = Cutiemark::find($item['id']);
						if (empty($cm))
							Response::fail("The cutie mark you're trying to update (#{$item['id']}) does not exist");
						$NewIDs[] = $cm->id;
					}
					else $cm = new Cutiemark([
						'appearance_id' => $this->_appearance->id,
					]);

					$svg_data_missing = empty($item['svgdata']);
					if ($cm->id === null || !$svg_data_missing){
						if ($svg_data_missing)
							Response::fail('SVG data is missing');
						if (CoreUtils::stringSize($item['svgdata']) > UploadedFile::SIZES['megabyte'])
							Response::fail('SVG data exceeds the maximum size of 1 MB');
						if (CoreUtils::validateSvg($item['svgdata']) !== Input::ERROR_NONE)
							Response::fail('SVG data is invalid');
						$svgdata = $item['svgdata'];
					}
					else $svgdata = null;

					$label = null;
					if (isset($item['label'])){
						$item['label'] = CoreUtils::trim($item['label']);
						if (!empty($item['label'])){
							CoreUtils::checkStringValidity($item['label'], 'Cutie Mark label', INVERSE_PRINTABLE_ASCII_PATTERN);
							if (Input::checkStringLength($item['label'], [1, 32]) === Input::ERROR_RANGE)
								Response::fail('Cutie mark label must be between 1 and 32 chars long');
							if (isset($labels[$item['label']]))
								Response::fail('Cutie mark labels must be unique within an appearance');
							else $labels[$item['label']] = true;
							$label = $item['label'];
						}
					}
					$cm->label = $label;

					if (isset($item['facing'])){
						$facing = CoreUtils::trim($item['facing']);
						if (empty($facing))
							$facing = null;
						else if (!in_array($facing,Cutiemarks::VALID_FACING_VALUES,true))
							Response::fail('Body orientation "'.CoreUtils::escapeHTML($facing).'" is invalid');
					}
					else $facing = null;
					$cm->facing = $facing;

					switch ($item['attribution']){
						case 'deviation':
							if (empty($item['deviation']))
								Response::fail('Deviation link is missing');

							try {
								$Image = new ImageProvider(CoreUtils::trim($item['deviation']), ImageProvider::PROV_DEVIATION, true);
								/** @var $deviation CachedDeviation */
								$deviation = $Image->extra;
							}
							catch (MismatchedProviderException $e){
								Response::fail('The link must point to a DeviantArt submission, '.$e->getActualProvider().' links are not allowed');
							}
							catch (\Exception $e){ Response::fail('Error while checking deviation link: '.$e->getMessage()); }

							if (empty($deviation))
								Response::fail('The provided deviation could not be fetched');
							$cm->favme = $deviation->id;
							$contributor = Users::get($deviation->author, 'name');
							if (empty($contributor))
								Response::fail("The provided deviation's creator could not be fetched");
							$cm->contributor_id = $contributor->id;
						break;
						case 'user':
							global $USERNAME_REGEX;
							if (empty($item['username']))
								Response::fail('Username is missing');
							if (!preg_match($USERNAME_REGEX, $item['username']))
								Response::fail("Username ({$item['username']}) is invalid");
							$contributor = Users::get($item['username'], 'name');
							if (empty($contributor))
								Response::fail("The provided deviation's creator could not be fetched");
							$cm->favme = null;
							$cm->contributor_id = $contributor->id;
						break;
						case 'none':
							$cm->favme = null;
							$cm->contributor_id = null;
						break;
						default:
							Response::fail('The specified attribution method is invalid');
					}

					if (!isset($item['rotation']))
						Response::fail('Preview rotation amount is missing');
					if (!is_numeric($item['rotation']))
						Response::fail('Preview rotation must be a number');
					$rotation = (int) $item['rotation'];
					if (abs($rotation) > 45)
						Response::fail('Preview rotation must be between -45 and 45');
					$cm->rotation = $rotation;

					$NewCMs[$i] = $cm;
					$NewSVGs[$i] = $svgdata;
				}

				if (!empty($NewCMs)){
					CoreUtils::createFoldersFor(Cutiemark::SOURCE_FOLDER);
					foreach ($NewCMs as $i => $cm){
						if (!$cm->save())
							Response::dbError("Saving cutie mark (index $i) failed");

						if ($NewSVGs[$i] !== null){
							if (false !== File::put($cm->getSourceFilePath(), $NewSVGs[$i])){
								CoreUtils::deleteFile($cm->getTokenizedFilePath());
								CoreUtils::deleteFile($cm->getRenderedFilePath());
								continue;
							}

							Response::fail("Saving SVG data for cutie mark (index $i) failed");
						}
					}

					$RemovedIDs = CoreUtils::array_subtract(array_keys($CurrentCMs), $NewIDs);
					if (!empty($RemovedIDs)){
						foreach ($RemovedIDs as $removedID)
							$CurrentCMs[$removedID]->delete();
					}

					$CutieMarks = Cutiemarks::get($this->_appearance);
					$olddata = Cutiemarks::convertDataForLogs($CurrentCMs);
					$newdata = Cutiemarks::convertDataForLogs($CutieMarks);
					if ($olddata !== $newdata)
						Logs::logAction('cm_modify',[
							'appearance_id' => $this->_appearance->id,
							'olddata' => $olddata,
							'newdata' => $newdata,
						]);
				}
				else {
					foreach ($CurrentCMs as $cm)
						$cm->delete();

					$this->_appearance->clearRenderedImages([Appearance::CLEAR_CMDIR]);

					Logs::logAction('cm_delete',[
						'appearance_id' => $this->_appearance->id,
						'data' => Cutiemarks::convertDataForLogs($CMs),
					]);

					$CutieMarks = [];
				}

				$data = [];
				if ($this->_appearancePage && !empty($CutieMarks))
					$data['html'] = Cutiemarks::getListForAppearancePage(Cutiemarks::get($this->_appearance));
				Response::done($data);
			break;
			case 'clear-cache':
				if (!$this->_appearance->clearRenderedImages())
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

						$TagCheck = CGUtils::normalizeEpisodeTagName($tag_name);
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

				$this->_appearance->updateIndex();

				Tags::updateUses($Tag->id);
				if (!empty(CGUtils::GROUP_TAG_IDS_ASSOC[$this->_EQG?'eqg':'pony'][$Tag->id]))
					Appearances::getSortReorder($this->_EQG);

				$response = ['tags' => $this->_appearance->getTagsHTML(NOWRAP)];
				if ($this->_appearancePage && $Tag->type === 'ep'){
					$response['needupdate'] = true;
					$response['eps'] = $this->_appearance->getRelatedEpisodesHTML($this->_EQG);
				}
				Response::done($response);
			break;
			case 'applytemplate':
				try {
					$this->_appearance->applyTemplate();
				}
				catch (\Exception $e){
					Response::fail('Applying the template failed. Reason: '.$e->getMessage());
				}

				Response::done(['cgs' => $this->_appearance->getColorsHTML(NOWRAP, !$this->_appearancePage, $this->_appearancePage)]);
			break;
			case 'selectiveclear':
				$wipe_cache = (new Input('wipe_cache','bool',[
					Input::IS_OPTIONAL => true,
				]))->out();
				if ($wipe_cache)
					$this->_execAppearanceAction('clear-cache',null,true);

				$wipe_cms = (new Input('wipe_cms','bool',[
					Input::IS_OPTIONAL => true,
				]))->out();
				if ($wipe_cms)
					$this->_execAppearanceAction('delcms',null,true);

				$wipe_cm_tokenized = (new Input('wipe_cm_tokenized','bool',[
					Input::IS_OPTIONAL => true,
				]))->out();
				if ($wipe_cm_tokenized){
					foreach ($this->_appearance->cutiemarks as $cm)
						CoreUtils::deleteFile($cm->getTokenizedFilePath());
				}

				$wipe_cm_source = (new Input('wipe_cm_source','bool',[
					Input::IS_OPTIONAL => true,
				]))->out();
				if ($wipe_cm_source){
					foreach ($this->_appearance->cutiemarks as $cm)
						CoreUtils::deleteFile($cm->getSourceFilePath());
				}

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
						if ($this->_appearance->hasColors(true)){
							/** @noinspection NestedPositiveIfStatementsInspection */
							if (!DB::$instance->query('UPDATE colors SET hex = null WHERE group_id IN (SELECT id FROM color_groups WHERE appearance_id = ?)', [$this->_appearance->id]))
								Response::dbError();
						}
					break;
					case 'color_all':
						if ($this->_appearance->hasColors()){
							/** @noinspection NestedPositiveIfStatementsInspection */
							if (!DB::$instance->query('DELETE FROM colors WHERE group_id IN (SELECT id FROM color_groups WHERE appearance_id = ?)', [$this->_appearance->id]))
								Response::dbError();
						}
					break;
					case 'all':
						if (ColorGroup::exists(['conditions' => ['appearance_id = ?', $this->_appearance->id]])){
							/** @noinspection NestedPositiveIfStatementsInspection */
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

		/** @var $tagIDs int[] */
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
			CoreUtils::noPerm();

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
				$AppearanceID = CGUtils::validateAppearancePageID();

				$tid = $Tag->synonym_of ?? $Tag->id;
				$Uses = Tagged::by_tag($tid);
				$UseCount = count($Uses);
				if (!isset($_POST['sanitycheck']) && $UseCount > 0)
					Response::fail('<p>This tag is currently used on '.CoreUtils::makePlural('appearance',$UseCount,PREPEND_NUMBER).'</p><p>Deleting will <strong class="color-red">permanently remove</strong> the tag from those appearances!</p><p>Are you <em class="color-red">REALLY</em> sure about this?</p>', ['confirm' => true]);

				$Tag->delete();

				if (!empty(CGUtils::GROUP_TAG_IDS_ASSOC[$this->_EQG?'eqg':'pony'][$Tag->id]))
					Appearances::getSortReorder($this->_EQG);
				foreach ($Uses as $use)
					$use->appearance->updateIndex();

				if ($AppearanceID !== null && $Tag->type === 'ep'){
					$Appearance = Appearance::find($AppearanceID);
					$resp = [
						'needupdate' => true,
						'eps' => $Appearance->getRelatedEpisodesHTML($this->_EQG),
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
							$tg->appearance->updateIndex();
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

				$epTagName = CGUtils::normalizeEpisodeTagName($data['name']);
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
						$Appearance->updateIndex();
						Tags::updateUses($Tag->id);
						$r = ['tags' => $Appearance->getTagsHTML(NOWRAP)];
						if ($this->_appearancePage){
							$r['needupdate'] = true;
							$r['eps'] = $Appearance->getRelatedEpisodesHTML($this->_EQG);
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
						$tagged->appearance->updateIndex();

						if ($tagged->appearance_id === $AppearanceID){
							$data['needupdate'] = true;
							$Appearance = Appearance::find($AppearanceID);
							$data['eps'] = $Appearance->getRelatedEpisodesHTML($this->_EQG);
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
				Appearance::find($id)->updateIndex();

			Tags::updateUses($Target->id);
			Response::success('Tags successfully '.($merging?'merged':'synonymized'), $synoning || $merging ? ['target' => $Target] : null);
		}

		CoreUtils::notFound();
	}

	public function colorGroupAction($params){
		global $HEX_COLOR_REGEX;

		$this->_initPersonal($params, false);
		if (Permission::insufficient('member'))
			Response::fail();
		$isStaff = Permission::sufficient('staff');

		$action = $params['action'];
		$adding = $action === 'make';

		if (!$adding){
			if (empty($params['id']))
				Response::fail('Missing color group ID');
			$GroupID = intval($params['id'], 10);
			$Group = ColorGroup::find($GroupID);
			if (empty($Group))
				Response::fail("There’s no color group with the ID of $GroupID");
			if (!$isStaff && ($Group->appearance->owner_id === null || $Group->appearance->owner_id !== Auth::$user->id))
				Response::fail();

			if ($action === 'get'){
				$out = $Group->to_array();
				$out['Colors'] = [];
				foreach ($Group->colors as $c){
					$append = $c->to_array([
						'except' => 'group_id',
					]);
					if ($c->linked_to !== null)
						$append['appearance'] = DB::$instance->querySingle(
							'SELECT p.id, p.label FROM appearances p
							LEFT JOIN color_groups cg ON cg.appearance_id = p.id
							LEFT JOIN colors c ON c.group_id = cg.id
							WHERE c.id = ?', [$c->linked_to]);
					$out['Colors'][] = $append;
				}
				Response::done($out);
			}

			if ($action === 'del'){
				$Appearance = $Group->appearance;

				$Group->delete();

				Logs::logAction('cgs', [
					'action' => 'del',
					'group_id' => $Group->id,
					'appearance_id' => $Group->appearance_id,
					'label' => $Group->label,
					'order' => $Group->order,
				]);

				$Appearance->checkSpriteColors();

				Response::success('Color group deleted successfully');
			}
		}
		else $Group = new ColorGroup();

		if ($adding){
			$ponyid = (new Input('ponyid','int', [
				Input::CUSTOM_ERROR_MESSAGES => [
					Input::ERROR_MISSING => 'Missing appearance ID',
				]
			]))->out();
			$params['id'] = $ponyid;
			$this->_getAppearance($params);
			if (!$isStaff && !$this->_isOwnedByUser)
				Response::fail();
			$Group->appearance_id = $ponyid;
		}

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
		if (!$adding)
			DB::$instance->where('id',$Group->id,'!=');
		if (DB::$instance->where('appearance_id', $Group->appearance_id)->where('label', $label)->has(ColorGroup::$table_name))
			Response::fail('There is already a color group with the same name on this appearance.');
		$Group->label = $label;

		if ($Group->appearance->owner_id === null){
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
		}

		$Group->save();

		$oldcolors = $adding ? null : $Group->colors;
		$oldColorIDs = [];
		if (!$adding){
			foreach ($oldcolors as $oc)
				$oldColorIDs[] = $oc->id;
		}

		/** @var $recvColors array */
		$recvColors = (new Input('Colors','json', [
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Missing list of colors',
				Input::ERROR_INVALID => 'List of colors is invalid',
			]
		]))->out();
		/** @var $newcolors Color[] */
		$newcolors = [];
		/** @var $recvColorIDs int[] */
		$recvColorIDs = [];
		/** @var $check_colors_of Appearance[] */
		$check_colors_of = [];
		foreach ($recvColors as $part => $c){
			if (!empty($c['id'])){
				$append = Color::find($c['id']);
				if (empty($append))
					Response::fail("Trying to edit color with ID {$c['id']} which does not exist");
				if ($append->group_id !== $Group->id)
					Response::fail("Trying to modify color with ID {$c['id']} which is not part of the color group you're editing");
				$append->order = $part+1;
				$index = "(ID: {$c['id']})";
				$recvColorIDs[] = $c['id'];
			}
			else {
				$append = new Color([
					'group_id' => $Group->id,
					'order' => $part+1,
				]);
				$index = "(index: $part)";
			}

			if (empty($c['label']))
				Response::fail("You must specify a color name $index");
			$label = CoreUtils::trim($c['label']);
			CoreUtils::checkStringValidity($label, "Color $index name", INVERSE_PRINTABLE_ASCII_PATTERN);
			$ll = CoreUtils::length($label);
			if ($ll < 3 || $ll > 30)
				Response::fail("The color name must be between 3 and 30 characters in length $index");
			$append->label = $label;

			if (empty($c['hex'])){
				if (!empty($c['linked_to'])){
					$link_target = Color::find($c['linked_to']);
					if (empty($link_target))
						Response::fail("Link target color does not exist $index");
					// Regular guide
					if ($link_target->appearance->owner_id === null){
						// linking to PCG
						if ($append->appearance->owner_id !== null)
							Response::fail("Colors of appearances in the official guide cannot link to colors in personal color guides $index");
						// not Staff
						if (Permission::insufficient('staff'))
							Response::fail("Only staff members can edit colors in the official guide $index");
					}
					// Personal color guide
					else {
						// linking to regular guide
						if ($append->appearance->owner_id === null)
							Response::fail("Colors of appearances in personal color guides cannot link to colors in the official guide $index");
						// not (owner of both appearances) and not Staff
						if ($append->appearance->owner_id !== Auth::$user->id && $link_target->appearance->owner_id !== Auth::$user->id && Permission::insufficient('staff'))
							Response::fail();
					}
					if ($link_target->linked_to !== null)
						Response::fail("The target color is already linked to a different color $index");
					if (!empty((array)$append->dependant_colors))
						Response::fail("Some colors point to this color which means it cannot be changed to a link $index");
					$append->linked_to = $link_target->id;
					$append->hex = $link_target->hex;
					if (!isset($check_colors_of[$link_target->appearance_id]))
						$check_colors_of[$link_target->appearance_id] = $link_target->appearance;
				}
			}
			else {
				$hex = CoreUtils::trim($c['hex']);
				if (!$HEX_COLOR_REGEX->match($hex, $_match))
					Response::fail('Hex color '.CoreUtils::escapeHTML($hex)." is invalid, please leave empty or fix $index");
				$append->hex = CGUtils::roundHex('#'.strtoupper($_match[1]));
				$append->linked_to = null;
			}

			$newcolors[] = $append;
		}
		if (!$adding){
			/** @var $removedColorIDs int[] */
			$removedColorIDs = CoreUtils::array_subtract($oldColorIDs, $recvColorIDs);
			$removedColors = [];
			if (!empty($removedColorIDs)){
				/** @var $Affected Color[] */
				$Affected = DB::$instance->where('id', $removedColorIDs)->get('colors');
				foreach ($Affected as $color){
					if (count((array) $color->dependant_colors) > 0){
						$links = [];
						foreach ($color->dependant_colors as $dep){
							$arranged[$dep->appearance->id][$dep->group_id][$dep->id] = $dep;
							$links[] = implode(' &rsaquo; ',[
								$dep->appearance->toAnchor(),
								$dep->color_group->label,
								$dep->label
							]);
						}
						Response::fail("<p>The colors listed below depend on color #{$color->id} (".CoreUtils::escapeHTML($color->label).'). Please unlink them before deleting this color.</p><ul><li>'.implode('</li><li>', $links).'</li></ul>');
					}

					$removedColors[] = $color;
				}
			}
		}
		$newlabels = [];
		foreach ($newcolors as $color){
			if (isset($newlabels[$color->label]))
				Response::fail('The color name "'.CoreUtils::escapeHTML($color->label).'" appears in this color group more than once. Please choose a unique name or add numbering to the colors.');

			$newlabels[$color->label] = true;
		}
		unset($newlabels);
		#### Validation ends here - No removal/modification of any colors before this point ####

		$colorError = false;
		foreach ($newcolors as $c){
			if ($c->save())
				continue;

			$colorError = true;
			error_log(__METHOD__.': Database error triggered by user '.Auth::$user->name.' ('.Auth::$user->id.") while saving colors:\n".JSON::encode($c->errors, JSON_PRETTY_PRINT));
		}
		if (!$adding && !empty($removedColors)){
			foreach ($removedColors as $color)
				$color->delete();
		}
		/** @var $newcolors Color[] */
		if ($colorError)
			Response::fail("There were some issues while saving the colors. Please <a class='send-feedback'>let us know</a> about this error, so we can look into why it might've happened.");

		if (!isset($check_colors_of[$Group->appearance_id]))
			$check_colors_of[$Group->appearance_id] = $Group->appearance;
		$isCMGroup = $Group->label === 'Cutie Mark';
		foreach ($check_colors_of as $appearance){
			$appearance->checkSpriteColors();
			$appearance->clearRenderedImages([Appearance::CLEAR_CMDIR, Appearance::CLEAR_PALETTE, Appearance::CLEAR_PREVIEW]);
			if ($isCMGroup)
				$appearance->clearRenderedImages([Appearance::CLEAR_CM]);
		}

		$colon = !$this->_appearancePage;
		$outputNames = $this->_appearancePage;

		$response = ['cgs' => $Group->appearance->getColorsHTML(NOWRAP, $colon, $outputNames)];

		if ($Group->appearance->owner_id === null && $major){
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
			else $response['update'] = $Group->appearance->getUpdatesHTML();
		}

		if (isset($_POST['APPEARANCE_PAGE']))
			$response['cm_list'] = Cutiemarks::getListForAppearancePage(CutieMarks::get($Group->appearance), NOWRAP);
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

	public function colorGroupAppearanceList(){
		$list = [];
		$personalGuide = $_POST['PERSONAL_GUIDE'] ?? null;
		if ($personalGuide !== null){
			$owner = Users::get($personalGuide, 'name');
			if (empty($owner))
				Response::fail('Personal Color Guide owner could not be found');
			$cond = ['owner_id = ?', $owner->id];
		}
		else $cond = 'owner_id IS NULL';

		foreach (Appearance::all([
			'conditions' => $cond,
			'select' => 'id, label, ishuman',
			'order' => 'label asc',
		]) as $item)
			$list[] = $item->to_array();
		Response::done([ 'list' =>  $list, 'pcg' => $personalGuide !== null ]);
	}

	public function colorGroupList($params){
		$this->_getAppearance($params);

		$list = [];
		foreach ($this->_appearance->color_groups as $item){
			$group = [
				'label' => $item->label,
				'colors' => []
			];
			foreach ($item->colors as $c){
				$arr = $c->to_array(['only' => ['id','label']]);
				if ($c->linked_to !== null)
					unset($arr['id']);
				$group['colors'][] = $arr;
			}
			if (count($group['colors']) > 0)
				$list[] = $group;
		}
		Response::done([ 'list' =>  $list ]);
	}

	public function blending(){
		global $HEX_COLOR_REGEX;

		CoreUtils::fixPath('/cg/blending');

		$HexPattern = preg_replace(new RegExp('^/(.*)/.*$'),'$1',$HEX_COLOR_REGEX->jsExport());
		CoreUtils::loadPage(__METHOD__, [
			'title' => 'Color Blending Calculator',
			'css' => [true],
			'js' => [true],
			'import' => [
				'HexPattern' => $HexPattern,
				'nav_blending' => true,
			],
		]);
	}

	public function blendingReverse(){
		global $HEX_COLOR_REGEX;

		if (Permission::insufficient('staff'))
			CoreUtils::noPerm();

		CoreUtils::fixPath('/cg/blending-reverse');

		$HexPattern = preg_replace(new RegExp('^/(.*)/.*$'),'$1',$HEX_COLOR_REGEX->jsExport());
		CoreUtils::loadPage(__METHOD__, [
			'title' => 'Blending Reverser',
			'css' => [true],
			'js' => ['nouislider', 'Blob', 'canvas-toBlob', 'FileSaver', true],
			'import' => [
				'HexPattern' => $HexPattern,
				'nav_blendingrev' => true,
			],
		]);
	}

	public function picker(){
		CoreUtils::loadPage(__METHOD__, [
			'title' => 'Color Picker',
			'view' => [true],
			'css' => [true],
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

		$ColorMappings = $this->_appearance->getColorMapping($DefaultMapping);

		$colors = [];
		foreach ($DefaultMapping as $k => $v){
			if (!isset($ColorMappings[$k]))
				continue;

			$colors[$v] = $ColorMappings[$k];
		}

		Response::done(['colors' => $colors]);
	}

	public function spriteColorCheckup(){
		if (Permission::insufficient('staff'))
			Response::fail();

		ini_set('max_execution_time', '0');

		$SpriteDir = new \DirectoryIterator(SPRITE_PATH);
		foreach ($SpriteDir as $item){
			if ($item->isDot())
				continue;

			$id = (int)preg_replace(new RegExp('\..+$'),'',$item->getFilename());
			$Appearance = Appearance::find($id);
			if (empty($Appearance))
				continue;

			$Appearance->checkSpriteColors();
		}

		Response::success('Checkup finished');
	}

	public function cutiemarkView($params){
		$cutiemark = Cutiemark::find($params['id']);
		if (empty($cutiemark))
			CoreUtils::notFound();

		CGUtils::renderCMSVG($cutiemark);
	}

	public function cutiemarkDownload($params){
		$cutiemark = Cutiemark::find($params['id']);
		if (empty($cutiemark))
			CoreUtils::notFound();

		$source = isset($_REQUEST['source']) && Permission::sufficient('staff');

		$file = $source ? $cutiemark->getSourceFilePath() : $cutiemark->getRenderedFilePath();

		if (!$source && !file_exists($file))
			CGUtils::renderCMSVG($cutiemark, false);

		$filename = $cutiemark->label === null
			? CoreUtils::posess($cutiemark->appearance->label).' Cutie Mark'
			: $cutiemark->appearance->label.' - '.$cutiemark->label;

		CoreUtils::downloadFile($file, $filename.($source?' (source)':'').'.svg');
	}

	public function sanitizeSvg($params){
		if (Permission::insufficient('member'))
			Response::fail();

		CSRFProtection::protect();

		$this->_getAppearance($params, false);

		$svgdata = (new Input('file','svg_file',[
			Input::SOURCE => 'FILES',
			Input::IN_RANGE => [null, UploadedFile::SIZES['megabyte']],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'SVG data is missing',
				Input::ERROR_INVALID => 'SVG data is invalid',
				Input::ERROR_RANGE => 'SVG file size exceeds @max bytes.',
			]
		]))->out();

		$svgel = CGUtils::untokenizeSvg(CGUtils::tokenizeSvg(CoreUtils::sanitizeSvg($svgdata), $this->_appearance->id), $this->_appearance->id);

		Response::done(['svgel' => $svgel, 'svgdata' => $svgdata, 'keep_dialog' => true]);
	}
}
