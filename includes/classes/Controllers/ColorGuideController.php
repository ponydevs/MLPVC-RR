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
use App\HTTP;
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
use App\Models\PCGSlotHistory;
use App\Models\RelatedAppearance;
use App\Models\Tag;
use App\Models\Tagged;
use App\Models\User;
use App\NSUriBuilder;
use App\Pagination;
use App\Permission;
use App\RegExp;
use App\Response;
use App\UploadedFile;
use App\UserPrefs;
use App\Appearances;
use App\Tags;
use App\Users;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
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

		$this->_appearancePage = isset($_REQUEST['APPEARANCE_PAGE']);
		$this->_personalGuide = isset($_REQUEST['PERSONAL_GUIDE']);
	}

	/** @var bool|int|null */
	protected $_EQG;
	/** @var bool */
	protected $_appearancePage, $_personalGuide;
	/** @var string */
	protected $_guide;
	protected function _initCGPath(){
		$this->path = rtrim("/cg/{$this->_guide}", '/');
	}
	/** @var User|null|false */
	protected $owner;
	/** @var bool */
	protected $ownerIsCurrentUser = false;
	protected function _initialize($params){
		$this->_guide = strtolower($params['guide'] ?? 'pony');
		$this->_EQG = $this->_guide === 'eqg' || isset($_REQUEST['eqg']);
		$nameSet = isset($params['name']);

		if ($nameSet){
			$this->owner = Users::get($params['name'], 'name');
			if (empty($this->owner))
				CoreUtils::notFound();
		}
		$this->ownerIsCurrentUser = $nameSet ? (Auth::$signed_in && Auth::$user->id === $this->owner->id) : false;

		if ($nameSet)
			$this->path = "/@{$this->owner->name}/cg";
		else $this->_initCGPath();
	}

	/** @var \App\Models\Appearance */
	protected $appearance;
	public function _getAppearance($params, bool $set_properties = true){
		if (!isset($params['id']))
			Response::fail('Missing appearance ID');
		$this->appearance = Appearance::find($params['id']);
		if (empty($this->appearance))
			CoreUtils::notFound();
		if (!$set_properties)
			return;

		$this->_personalGuide = $this->appearance->owner_id !== null;
		$this->owner = $this->appearance->owner;
		if (!$this->_personalGuide){
			if ($this->appearance->ishuman && !$this->_EQG){
				$this->_EQG = 1;
				$this->path = '/cg/eqg';
			}
			else if (!$this->appearance->ishuman && $this->_EQG){
				$this->_EQG = 0;
				$this->path = '/cg';
			}
			else if ($this->owner !== null){
				$this->owner = null;
				$this->ownerIsCurrentUser = false;
				$this->path = '/cg';
			}
			else {
				$this->_EQG = $this->appearance->ishuman;
			}
		}
		else {
			$this->_EQG = $this->appearance->ishuman;
			$OwnerName = $this->appearance->owner->name;
			$this->path = "/@$OwnerName/cg";
			$this->ownerIsCurrentUser = Auth::$signed_in && ($this->appearance->owner_id === Auth::$user->id);
		}
	}

	protected const GUIDE_MANAGE_JS = [
		'jquery.uploadzone',
		'jquery.autocomplete',
		'jquery.ponycolorpalette',
		'Sortable',
		'pages/colorguide/tag-list',
		'pages/colorguide/manage',
	];
	protected const GUIDE_MANAGE_CSS = [
		'pages/colorguide/manage',
	];

	public function fullList($params){
		$this->_initialize($params);

		$GuideOrder = !isset($_GET['alphabetically']);
		if (!$GuideOrder)
			DB::$instance->orderBy('label');
		$Appearances = Appearances::get($this->_EQG,null,null,'id,label,private');

		$path = new NSUriBuilder("{$this->path}/full");
		if (!$GuideOrder)
			$path->append_query_param('alphabetically', null);

		if (CoreUtils::isJSONExpected())
			Response::done([
				'html' => CGUtils::getFullListHTML($Appearances, $GuideOrder, $this->_EQG, NOWRAP),
				'stateUrl' => (string)$path,
			]);

		CoreUtils::fixPath($path);

		$js = [];
		if (Permission::sufficient('staff'))
			$js[] = 'Sortable';
		$js[] = true;

		CoreUtils::loadPage(__METHOD__, [
			'title' => 'Full List - '.($this->_EQG?'EQG':'Pony').' Color Guide',
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
		if ($this->action !== 'POST')
			CoreUtils::notAllowed();

		$this->_initialize($params);

		if (!Permission::sufficient('staff'))
			Response::fail();

		Appearances::reorder((new Input('list','int[]', [
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'The list of IDs is missing',
				Input::ERROR_INVALID => 'The list of IDs is not formatted properly',
			]
		]))->out());

		Response::done(['html' => CGUtils::getFullListHTML(Appearances::get($this->_EQG), true, $this->_EQG, NOWRAP)]);
	}

	public function changeList($params){
		$this->_initialize($params);
		$Pagination = new Pagination("{$this->path}/changes", 9, MajorChange::total($this->_EQG));

		CoreUtils::fixPath($Pagination->toURI());
		$heading = 'Major '.CGUtils::GUIDE_MAP[$this->_guide].' Color Changes';
		$title = "Page {$Pagination->getPage()} - $heading - Color Guide";

		$Changes = MajorChange::get(null, $this->_EQG, $Pagination->getLimitString());

		CoreUtils::loadPage(__METHOD__, [
			'title' => $title,
			'heading' => $heading,
			'css' => [true],
			'js' => ['paginate'],
			'import' => [
				'EQG' => $this->_EQG,
				'Changes' => $Changes,
				'Pagination' => $Pagination,
			],
		]);
	}

	public function guide($params){
		$this->_initialize($params);

		$title = '';
		/** @var $AppearancesPerPage int */
		$AppearancesPerPage = UserPrefs::get('cg_itemsperpage');
		$Ponies = [];
		try {
			$elasticAvail = CoreUtils::elasticClient()->ping();
		}
		catch (NoNodesAvailableException|ServerErrorResponseException $e){
			$elasticAvail = false;
		}
		$searching = !empty($_GET['q']) && mb_strlen(CoreUtils::trim($_GET['q'])) > 0;
		$jsResponse = CoreUtils::isJSONExpected();
		if ($elasticAvail){
			$search = new ElasticsearchDSL\Search();
			$inOrder = true;
		    $Pagination = new Pagination($this->path, $AppearancesPerPage);

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
				$boolquery->add(new TermQuery('private', 'false'), BoolQuery::MUST);
			$boolquery->add(new TermQuery('ishuman', $this->_EQG ? 'true' : 'false'), BoolQuery::MUST);
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
			catch (ServerErrorResponseException | BadRequest400Exception $e){
				$message = $e->getMessage();
				if (
					strpos($message, 'Result window is too large, from + size must be less than or equal to') === false
					&& strpos($message, 'Failed to parse int parameter [from] with value') === false
				){
					throw $e;
				}

				$search = [];
				$Pagination->calcMaxPages(0);
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

			$SearchQuery = null;
		    $_EntryCount = DB::$instance->where('ishuman',$this->_EQG)->where('id != 0')->count('appearances');

		    $Pagination = new Pagination($this->path, $AppearancesPerPage, $_EntryCount);
		    $Ponies = Appearances::get($this->_EQG, $Pagination->getLimit());
		}

		if (isset($_REQUEST['btnl'])){
			$found = !empty($Ponies[0]->id);
			if (CoreUtils::isJSONExpected()){
				if (!$found)
					Response::fail('Your search returned no results.');
				Response::done([ 'goto' => $Ponies[0]->toURL() ]);
			}
			if ($found)
				HTTP::tempRedirect($Ponies[0]->toURL());
		}

		$path = $Pagination->toURI();
		$path->append_query_param('q', !empty($SearchQuery) ? $SearchQuery : CoreUtils::FIXPATH_EMPTY);
		CoreUtils::fixPath($path);
		$heading = ($this->_EQG?'EQG':'Pony').' Color Guide';
		$title .= "Page {$Pagination->getPage()} - $heading";

		$settings = [
			'title' => $title,
			'heading' => $heading,
			'noindex' => $searching,
			'css' => [true],
			'js' => ['jquery.ctxmenu', true, 'paginate'],
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

	public function export(){
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

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
		CoreUtils::downloadAsFile($data, 'mlpvc-colorguide.json');
	}

	public function reindex(){
		if ($this->action !== 'POST')
			CoreUtils::notAllowed();

		if (Permission::insufficient('developer'))
			Response::fail();
		Appearances::reindex();
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

	public function spriteColorCheckup(){
		if ($this->action !== 'POST')
			CoreUtils::notAllowed();

		if (Permission::insufficient('staff'))
			Response::fail();

		CoreUtils::callScript('sprite_color_checkup');

		$nagUser = Users::get(Appearances::SPRITE_NAG_USERID);
		Response::success('Checkup started.'.($nagUser !== null ? " {$nagUser->toAnchor()} will be notified if there are any issues.":''));
	}
}
