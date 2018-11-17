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
use App\Regexes;
use App\RegExp;
use App\Response;
use App\Twig;
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

		$this->_appearancePage = isset($_REQUEST['APPEARANCE_PAGE']);
		$this->_personalGuide = isset($_REQUEST['PERSONAL_GUIDE']);
	}

	/** @var bool|int|null */
	protected $_EQG;
	/** @var bool */
	protected $_appearancePage, $_personalGuide;
	/** @var string */
	protected $_guide;
	protected function _initCGPath():void {
		$this->path = rtrim("/cg/{$this->_guide}", '/');
	}
	/** @var User|null|false */
	protected $owner;
	/** @var bool */
	protected $ownerIsCurrentUser = false;
	protected function _initialize($params):void {
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

	/** @var Appearance */
	protected $appearance;
	public function load_appearance($params, bool $set_properties = true):void {
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
		'jquery.ponycolorpalette',
		'pages/colorguide/tag-list',
		'pages/colorguide/manage',
	];
	protected const GUIDE_MANAGE_CSS = [
		'pages/colorguide/manage',
	];
	protected const GUIDE_MANAGE_LIBS = [
		'autocomplete',
		'sortable',
	];
	protected static function _appendManageAssets(&$settings):void {
		$settings['js'] = array_merge($settings['js'], self::GUIDE_MANAGE_JS);
		$settings['css'] = array_merge($settings['css'], self::GUIDE_MANAGE_CSS);
		$settings['libs'] = isset($settings['libs']) ? array_merge($settings['libs'], self::GUIDE_MANAGE_LIBS) : self::GUIDE_MANAGE_LIBS;
	}

	public const FULL_LIST_ORDER = [
		'label' => 'alphabetically',
		'relevance' => 'by relevance',
		'added' => 'by date added',
	];

	public function fullList($params):void {
		$this->_initialize($params);

		$sort_by = $_GET['sort_by'] ?? null;
		if (!isset(self::FULL_LIST_ORDER[$sort_by]))
			$sort_by = 'relevance';
		switch ($sort_by){
			case 'label':
				DB::$instance->orderBy('label');
			break;
			case 'added';
				DB::$instance->orderBy('added', 'DESC');
			break;
		}
		$appearances = Appearances::get($this->_EQG,null,null,'id,label,private');
		$eqg = $this->_EQG;

		$path = new NSUriBuilder("{$this->path}/full");
		if ($sort_by !== 'relevance')
			$path->append_query_param('sort_by', $sort_by);

		if (CoreUtils::isJSONExpected())
			Response::done([
				'html' => CGUtils::getFullListHTML($appearances, $sort_by, $eqg, NOWRAP),
				'stateUrl' => (string)$path,
			]);

		CoreUtils::fixPath($path);

		$is_staff = Permission::sufficient('staff');

		$libs = [];
		if ($is_staff)
			$libs[] = 'sortable';

		$import = [
			'eqg' => $eqg,
			'appearances' => $appearances,
			'sort_by' => $sort_by,
			'is_staff' => $is_staff,
			'full_list' => CGUtils::getFullListHTML($appearances, $sort_by, $eqg),
		];
		if ($is_staff){
			$import['max_upload_size'] = CoreUtils::getMaxUploadSize();
			$import['hex_color_pattern'] = Regexes::$hex_color;
		}
		CoreUtils::loadPage(__METHOD__, [
			'title' => 'Full List - '.($this->_EQG?'EQG':'Pony').' Color Guide',
			'css' => [true],
			'libs' => $libs,
			'js' => [true],
			'import' => $import,
		]);
	}

	public function reorderFullList($params):void {
		if ($this->action !== 'POST')
			CoreUtils::notAllowed();

		$this->_initialize($params);

		if (Permission::insufficient('staff'))
			Response::fail();

		Appearances::reorder((new Input('list','int[]', [
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'The list of IDs is missing',
				Input::ERROR_INVALID => 'The list of IDs is not formatted properly',
			]
		]))->out());

		$ordering = (new Input('ordering','string', [
			Input::IS_OPTIONAL => true,
		]))->out();

		Response::done(['html' => CGUtils::getFullListHTML(Appearances::get($this->_EQG), $ordering, $this->_EQG, NOWRAP)]);
	}

	public function changeList($params):void {
		$this->_initialize($params);
		$pagination = new Pagination("{$this->path}/changes", 9, MajorChange::total($this->_EQG));

		CoreUtils::fixPath($pagination->toURI());
		$heading = 'Major '.CGUtils::GUIDE_MAP[$this->_guide].' Color Changes';
		$title = "Page {$pagination->getPage()} - $heading - Color Guide";

		$changes = MajorChange::get(null, $this->_EQG, $pagination->getLimitString());

		CoreUtils::loadPage(__METHOD__, [
			'title' => $title,
			'heading' => $heading,
			'css' => [true],
			'js' => ['paginate'],
			'import' => [
				'eqg' => $this->_EQG,
				'changes' => $changes,
				'pagination' => $pagination,
			],
		]);
	}

	public function guide($params):void {
		$this->_initialize($params);

		$title = '';
		/** @var $appearances_per_page int */
		$appearances_per_page = UserPrefs::get('cg_itemsperpage');
		$appearances = [];
		try {
			$elastic_avail = CoreUtils::elasticClient()->ping();
		}
		catch (NoNodesAvailableException|ServerErrorResponseException $e){
			$elastic_avail = false;
		}
		$searching = !empty($_GET['q']) && mb_strlen(CoreUtils::trim($_GET['q'])) > 0;
		$json_response = CoreUtils::isJSONExpected();
		if ($elastic_avail){
			$search = new ElasticsearchDSL\Search();
			$in_order = true;
		    $pagination = new Pagination($this->path, $appearances_per_page);

			// Search query exists
			if ($searching){
				$search_query = preg_replace(new RegExp('[^\w\s*?-]'),'',CoreUtils::trim($_GET['q']));
				$title .= "$search_query - ";
				$multi_match = new ElasticsearchDSL\Query\FullText\MultiMatchQuery(
					['label','tags'],
					$search_query,
					[
						'type' => 'cross_fields',
						'minimum_should_match' => '100%',
					]
				);
				$search->addQuery($multi_match);
				$score = new FunctionScoreQuery(new MatchAllQuery());
				$score->addFieldValueFactorFunction('order',1.5);
				$score->addParameter('boost_mode','sum');
				$search->addQuery($score);
				$sort = new ElasticsearchDSL\Sort\FieldSort('_score', 'asc');
				$search->addSort($sort);
				$in_order = false;
			}
			else {
				$sort = new ElasticsearchDSL\Sort\FieldSort('order', 'asc');
				$search->addSort($sort);
			}

			$bool_query = new BoolQuery();
			if (Permission::insufficient('staff'))
				$bool_query->add(new TermQuery('private', 'false'), BoolQuery::MUST);
			$bool_query->add(new TermQuery('ishuman', $this->_EQG ? 'true' : 'false'), BoolQuery::MUST);
			$search->addQuery($bool_query);

			$search->setSource(false);
			$search = $search->toArray();
			try {
				$search = CGUtils::searchElastic($search, $pagination);
			}
			catch (Missing404Exception $e){
				$elastic_avail = false;
				$search = [];
			}
			catch (ServerErrorResponseException | BadRequest400Exception $e){
				$message = $e->getMessage();
				if (
					!CoreUtils::contains($message, 'Result window is too large, from + size must be less than or equal to')
					&& !CoreUtils::contains($message, 'Failed to parse int parameter [from] with value')
				){
					throw $e;
				}

				$search = [];
				$pagination->calcMaxPages(0);
			}

			if (!empty($search)){
				$pagination->calcMaxPages($search['hits']['total']);
				if (!empty($search['hits']['hits'])){
					$ids = [];
					/** @noinspection ForeachSourceInspection */
					foreach($search['hits']['hits'] as $i => $hit)
						$ids[$hit['_id']] = $i;

					if ($in_order)
						DB::$instance->orderBy('order');
					DB::$instance->where('id', array_keys($ids));
					$appearances = Appearances::get($this->_EQG);
					if (!empty($appearances) && !$in_order)
						uasort($appearances, function(Appearance $a, Appearance $b) use ($ids){
							return $ids[$a->id] <=> $ids[$b->id];
						});
				}
			}
		}
		else {
			if ($searching && $json_response)
				Response::fail('The ElasticSearch server is currently down and search is not available, sorry for the inconvenience.<br>Please <a class="send-feedback">let us know</a> about this issue.', ['unavail' => true]);

			$search_query = null;
		    $entry_count = DB::$instance->where('ishuman',$this->_EQG)->where('id != 0')->count('appearances');

		    $pagination = new Pagination($this->path, $appearances_per_page, $entry_count);
		    $appearances = Appearances::get($this->_EQG, $pagination->getLimit());
		}

		if (isset($_REQUEST['btnl'])){
			$found = !empty($appearances[0]->id);
			if (CoreUtils::isJSONExpected()){
				if (!$found)
					Response::fail('Your search returned no results.');
				Response::done([ 'goto' => $appearances[0]->toURL() ]);
			}
			if ($found)
				HTTP::tempRedirect($appearances[0]->toURL());
		}

		$path = $pagination->toURI();
		$remove_params = null;
		if (!empty($search_query))
			$path->append_query_param('q', $search_query);
		else $remove_params = ['q'];
		CoreUtils::fixPath($path, $remove_params);
		$heading = ($this->_EQG?'EQG':'Pony').' Color Guide';
		$title .= "Page {$pagination->getPage()} - $heading";

		$json_export_url = CoreUtils::cachedAssetLink('mlpvc-colorguide','dist','json');
		$json_export_time = \App\Time::tag((int) explode('?',$json_export_url)[1]);
		$universal_appearance = Appearance::find(0);
		$settings = [
			'title' => $title,
			'heading' => $heading,
			'noindex' => $searching,
			'css' => [true],
			'js' => ['jquery.ctxmenu', true, 'paginate'],
			'import' => [
				'eqg' => $this->_EQG,
				'appearances' => $appearances,
				'pagination' => $pagination,
				'elastic_avail' => $elastic_avail,
				'json_export_url' => $json_export_url,
				'json_export_time' => $json_export_time,
				'universal_appearance' => $universal_appearance,
				'search_query' => $search_query ?? null,
			],
		];
		if (Permission::sufficient('staff')){
			self::_appendManageAssets($settings);
			$settings['import']['max_upload_size'] = CoreUtils::getMaxUploadSize();
			$settings['import']['hex_color_regex'] = Regexes::$hex_color;
		}
		CoreUtils::loadPage(__METHOD__, $settings);
	}

	public function export():void {
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		if (Permission::insufficient('developer'))
			CoreUtils::noPerm();

		CoreUtils::downloadAsFile(ElasticNoNodesAvailableException::getExportData(), 'mlpvc-colorguide.json');
	}

	public function reindex():void {
		if ($this->action !== 'POST')
			CoreUtils::notAllowed();

		if (Permission::insufficient('developer'))
			Response::fail();
		Appearances::reindex();
	}

	public function blending():void {
		CoreUtils::fixPath('/cg/blending');

		$hex_pattern = preg_replace(new RegExp('^/(.*)/.*$'),'$1', Regexes::$hex_color->jsExport());
		CoreUtils::loadPage(__METHOD__, [
			'title' => 'Color Blending Calculator',
			'css' => [true],
			'js' => [true],
			'import' => [
				'hex_pattern' => $hex_pattern,
				'nav_blending' => true,
				'dasprid_link' => Users::get('dasprid', 'name')->toAnchor(User::WITH_AVATAR),
				'hex_color_regex' => Regexes::$hex_color,
			],
		]);
	}

	public function blendingReverse():void {
		if (Permission::insufficient('staff'))
			CoreUtils::noPerm();

		CoreUtils::fixPath('/cg/blending-reverse');

		CoreUtils::loadPage(__METHOD__, [
			'title' => 'Blending Reverser',
			'libs' => [
				'no-ui-slider',
				'blob',
				'canvas-to-blob',
				'file-saver',
			],
			'css' => [true],
			'js' => [true],
			'import' => [
				'nav_blendingrev' => true,
				'hex_color_regex' => Regexes::$hex_color,
			],
		]);
	}

	public function picker():void {
		CoreUtils::loadPage(__METHOD__, [
			'title' => 'Color Picker',
			'view' => [true],
			'css' => [true],
			'import' => ['nav_picker' => true],
		]);
	}

	public function pickerFrame():void {
		CoreUtils::loadPage(__METHOD__, [
			'noindex' => true,
			'title' => 'Color Picker',
			'libs' => [
				'jquery',
				'ba-throttle-debounce',
				'md5',
				'dragscroll',
				'no-ui-slider',
				'paste',
				'cuid',
				'font-awesome',
			],
			'css' => [true],
			'default-js' => false,
			'default-libs' => false,
			'js' => [
				'shared-utils',
				'dialog',
				'lib/canvas.hdr',
				true,
			],
		]);
	}

	public function spriteColorCheckup():void {
		if ($this->action !== 'POST')
			CoreUtils::notAllowed();

		if (Permission::insufficient('staff'))
			Response::fail();

		CoreUtils::callScript('sprite_color_checkup');

		$nagUser = User::find(Appearances::SPRITE_NAG_USERID);
		$The_authorities = Appearances::SPRITE_NAG_USERID === Auth::$user->id ? 'You' : $nagUser->toAnchor();
		Response::success('Checkup started.'.($nagUser !== null ? " $The_authorities will be notified if there are any issues.":''));
	}
}
