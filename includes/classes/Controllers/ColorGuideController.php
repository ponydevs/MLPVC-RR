<?php

namespace App\Controllers;
use App\CGUtils;
use App\CoreUtils;
use App\CSRFProtection;
use App\Cutiemarks;
use App\Exceptions\MismatchedProviderException;
use App\Exceptions\NoPCGSlotsException;
use App\ImageProvider;
use App\Input;
use App\JSON;
use App\Logs;
use App\Models\Cutiemark;
use App\Models\User;
use App\Pagination;
use App\Permission;
use App\RegExp;
use App\Response;
use App\Updates;
use App\UserPrefs;
use App\Appearances;
use App\Tags;
use App\ColorGroups;
use App\Users;
use Elasticsearch\Common\Exceptions\ClientErrorResponseException;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use Elasticsearch\Common\Exceptions\ServerErrorResponseException;
use GuzzleHttp\Ring\Core;
use ONGR\ElasticsearchDSL;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use Elasticsearch\Common\Exceptions\Missing404Exception as ElasticMissing404Exception;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException as ElasticNoNodesAvailableException;

class ColorGuideController extends Controller {
	public $do = 'colorguide';
	function __construct(){
		parent::__construct();

		if (POST_REQUEST)
			CSRFProtection::protect();
	}

	/** @var bool */
	private $_EQG, $_appearancePage, $_personalGuide;
	/** @var string */
	private $_cgPath;
	private function _initialize($params, bool $setPath = true){
		$this->_EQG = !empty($params['eqg']) ? 1 : 0;
		if ($setPath)
			$this->_cgPath = "/cg".($this->_EQG?'/eqg':'');
		$this->_appearancePage = isset($_POST['APPEARANCE_PAGE']);
		$this->_personalGuide = isset($_POST['PERSONAL_GUIDE']);
	}

	/** @var User */
	private $_owner;
	/** @var bool */
	private $_isOwner;
	private function _initPersonal($params, $force = true){
		global $currentUser, $signedIn;

		$this->_initialize($params, !$force);

		$nameSet = isset($params['name']);
		if (!$nameSet && $force)
			CoreUtils::notFound();
		$this->_owner = $nameSet ? Users::get($params['name'], 'name') : null;
		$this->_isOwner = $nameSet ? ($signedIn && $currentUser->id === $this->_owner->id) : false;

		if ($nameSet)
			$this->_cgPath = "/@{$this->_owner->name}/cg";
	}

	/** @var array */
	private $_appearance;
	function _getAppearance($params){
		global $Database;

		$asFile = isset($params['ext']);
		if (!isset($params['id']))
			Response::fail('Missing appearance ID');
		$this->_appearance = $Database->where('id', $params['id'])->getOne('appearances', $asFile ? 'id,label,ishuman,owner' : '*');
		if (empty($this->_appearance))
			CoreUtils::notFound();

		if (!isset($this->_appearance['owner'])){
			if ($this->_appearance['ishuman'] && !$this->_EQG){
				$this->_EQG = 1;
				$this->_cgPath = '/cg/eqg';
			}
			else if (!$this->_appearance['ishuman'] && $this->_EQG){
				$this->_EQG = 0;
				$this->_cgPath = '/cg';
			}
		}
		else {
			$this->_EQG = null;
			$OwnerName = Users::get($this->_appearance['owner'],'id','name')->name;
			$this->_cgPath = "/@$OwnerName/cg";
		}
	}

	function spriteColors($params){
		if (Permission::insufficient('member'))
			CoreUtils::notFound();

		global $Database, $currentUser;

		$this->_getAppearance($params);
		if (Permission::insufficient('staff') && ($this->_appearance['owner'] ?? null) != $currentUser->id)
			CoreUtils::notFound();

		$Map = CGUtils::getSpriteImageMap($this->_appearance['id']);
		if (empty($Map))
			CoreUtils::notFound();

		$Colors = array();
		$loop = isset($this->_appearance['owner']) ? [$this->_appearance['id']] : [0, $this->_appearance['id']];
		foreach ($loop as $AppearanceID){
			$ColorGroups = ColorGroups::get($AppearanceID);
			$SortedColorGroups = array();
			foreach ($ColorGroups as $cg)
				$SortedColorGroups[$cg['groupid']] = $cg;

			$AllColors = ColorGroups::getColorsForEach($ColorGroups);
			foreach ($AllColors as $cg){
				foreach ($cg as $c)
					$Colors[] = array(
						'hex' => $c['hex'],
						'label' => $SortedColorGroups[$c['groupid']]['label'].' | '.$c['label'],
					);
			}
		}
		if (!isset($this->_appearance['owner']))
			$Colors = array_merge($Colors,
				array(
					array(
						'hex' => '#D8D8D8',
						'label' => 'Mannequin | Outline',
					),
					array(
		                'hex' => '#E6E6E6',
		                'label' => 'Mannequin | Fill',
					),
					array(
		                'hex' => '#BFBFBF',
		                'label' => 'Mannequin | Shadow Outline',
					),
					array(
		                'hex' => '#CCCCCC',
		                'label' => 'Mannequin | Shdow Fill',
					)
				)
			);

		$this->_initialize($params);

		$SafeLabel = Appearances::getSafeLabel($this->_appearance);
		CoreUtils::fixPath("{$this->_cgPath}/sprite/{$this->_appearance['id']}-$SafeLabel");

		CoreUtils::loadPage(array(
			'view' => "{$this->do}-sprite",
			'title' => "Sprite of {$this->_appearance['label']}",
			'css' => "{$this->do}-sprite",
			'js' => "{$this->do}-sprite",
			'import' => [
				'Appearance' => $this->_appearance,
				'ColorGroups' => $ColorGroups,
				'Colors' => $Colors,
				'AllColors' => $AllColors,
				'Map' => $Map,
			],
		));
	}

	function appearanceAsFile($params, User $Owner = null){
		if (!isset($Owner))
			$this->_initialize($params);
		$this->_getAppearance($params);

		switch ($params['ext']){
			case 'png':
				if (!empty($params['type'])) switch ($params['type']){
					case "s": CGUtils::renderSpritePNG($this->_cgPath, $this->_appearance['id']);
					case "p": CGUtils::renderAppearancePNG($this->_cgPath, $this->_appearance);
					default: CoreUtils::notFound();
				}

			case 'svg':
				if (!empty($params['type'])) switch ($params['type']){
					case "s": CGUtils::renderSpriteSVG($this->_cgPath, $this->_appearance['id']);
					case "p": CGUtils::renderPreviewSVG($this->_cgPath, $this->_appearance['id']);
					case "d": CGUtils::renderCMDirectionSVG($this->_cgPath, $this->_appearance['id']);
					default: CoreUtils::notFound();
				}
			case 'json': CGUtils::getSwatchesAI($this->_appearance);
			case 'gpl': CGUtils::getSwatchesInkscape($this->_appearance);
		}
		# rendering functions internally call die(), so execution stops above #

		CoreUtils::notFound();
	}

	function personalAppearanceAsFile($params){
		$this->_initPersonal($params);

		self::appearanceAsFile($params, $this->_owner);
	}

	private $_GUIDE_MANAGE_JS = array(
		'jquery.uploadzone',
		'jquery.autocomplete',
		'handlebars-v3.0.3',
		'Sortable',
		"colorguide-tags",
		"colorguide-manage",
	);
	private $_GUIDE_MANAGE_CSS = array(
		"colorguide-manage",
	);

	function appearancePage($params){
		if (!isset($this->_owner))
			$this->_initialize($params);
		$this->_getAppearance($params);

		global $Color, $color, $signedIn, $currentUser;

		$SafeLabel = Appearances::getSafeLabel($this->_appearance);
		CoreUtils::fixPath("$this->_cgPath/v/{$this->_appearance['id']}-$SafeLabel");
		$title = $heading = Appearances::processLabel($this->_appearance['label']);
		if ($this->_appearance['id'] === 0 && $color !== 'color')
			$title = str_replace('color',$color,$title);


		$settings = array(
			'title' => "$title - $Color Guide",
			'heading' => $heading,
			'view' => "{$this->do}-appearance",
			'css' => array($this->do, "{$this->do}-appearance"),
			'js' => array('jquery.qtip', 'jquery.ctxmenu', $this->do, "{$this->do}-appearance"),
			'import' => [
				'Appearance' => $this->_appearance,
				'EQG' => $this->_EQG,
				'isOwner' => false,
			],
		);
		if (isset($this->_appearance['owner'])){
			$settings['import']['Owner'] = $this->_owner;
			$settings['import']['isOwner'] = $this->_isOwner;
		}
		else $settings['import']['Changes'] = Updates::get($this->_appearance['id']);
		if (Permission::sufficient('staff') || $this->_isOwner){
			$settings['css'] = array_merge($settings['css'], $this->_GUIDE_MANAGE_CSS);
			$settings['js'] = array_merge($settings['js'], $this->_GUIDE_MANAGE_JS);
		}
		CoreUtils::loadPage($settings);
	}

	function personalAppearancePage($params){
		$this->_initPersonal($params);

		self::appearancePage($params);
	}

	function fullList($params){
		global $Database, $Color;

		$this->_initialize($params);

		$GuideOrder = !isset($_REQUEST['alphabetically']) && !$this->_EQG;
		if (!$GuideOrder)
			$Database->orderBy('label','ASC');
		$Appearances = Appearances::get($this->_EQG,null,null,'id,label,private');

		if (isset($_REQUEST['ajax']))
			Response::done(array('html' => CGUtils::getFullListHTML($Appearances, $GuideOrder, NOWRAP)));

		$js = array();
		if (Permission::sufficient('staff'))
			$js[] = 'Sortable';
		$js[] = "{$this->do}-full";

		CoreUtils::loadPage(array(
			'title' => "Full List - $Color Guide",
			'view' => "{$this->do}-full",
			'css' => "{$this->do}-full",
			'js' => $js,
			'import' => [
				'EQG' => $this->_EQG,
				'Appearances' => $Appearances,
				'GuideOrder' => $GuideOrder,
			]
		));
	}

	function reorderFullList($params){
		$this->_initialize($params);

		if (!Permission::sufficient('staff'))
			Response::fail();

		Appearances::reorder((new Input('list','int[]',array(
			Input::CUSTOM_ERROR_MESSAGES => array(
				Input::ERROR_MISSING => 'The list of IDs is missing',
				Input::ERROR_INVALID => 'The list of IDs is not formatted properly',
			)
		)))->out());

		Response::done(array('html' => CGUtils::getFullListHTML(Appearances::get($this->_EQG,null,null,'id,label'), true, NOWRAP)));
	}

	function changeList(){
		global $Database, $Color;

		$Pagination = new Pagination("cg/changes", 50, $Database->count('log__color_modify'));

		CoreUtils::fixPath("/cg/changes/{$Pagination->page}");
		$heading = "Major $Color Changes";
		$title = "Page $Pagination->page - $heading - $Color Guide";

		$Changes = Updates::get(null, $Pagination->getLimitString());

		if (isset($_GET['js']))
			$Pagination->respond(CGUtils::getChangesHTML($Changes, NOWRAP, SHOW_APPEARANCE_NAMES), '#changes');

		CoreUtils::loadPage(array(
			'title' => $title,
			'heading' => $heading,
			'view' => "{$this->do}-changes",
			'css' => "{$this->do}-changes",
			'js' => 'paginate',
			'import' => [
				'Changes' => $Changes,
				'Pagination' => $Pagination,
			],
		));
	}

	function tagList(){
		global $Database, $Color;

		$Pagination = new Pagination("cg/tags", 20, $Database->count('tags'));

		CoreUtils::fixPath("/cg/tags/{$Pagination->page}");
		$heading = "Tags";
		$title = "Page $Pagination->page - $heading - $Color Guide";

		$Tags = Tags::getFor(null,$Pagination->getLimit(), true);

		if (isset($_GET['js']))
			$Pagination->respond(Tags::getTagListHTML($Tags, NOWRAP), '#tags tbody');

		$js = array('paginate');
		if (Permission::sufficient('staff'))
			$js[] = "{$this->do}-tags";

		CoreUtils::loadPage(array(
			'title' => $title,
			'heading' => $heading,
			'view' => "{$this->do}-tags",
			'css' => "{$this->do}-tags",
			'js' => $js,
			'import' => [
				'Tags' => $Tags,
				'Pagination' => $Pagination,
			],
		));
	}

	function guide($params){
		$this->_initialize($params);

		global $Database, $Color;

		$title = '';
		$AppearancesPerPage = UserPrefs::get('cg_itemsperpage');
		$Ponies = [];
		try {
			$elasticAvail = CoreUtils::elasticClient()->ping();
		}
		catch (NoNodesAvailableException $e){
			$elasticAvail = false;
		}
		catch (ServerErrorResponseException $e){
			$elasticAvail = false;
		}
		if ($elasticAvail){
			$search = new ElasticsearchDSL\Search();
			$orderByID = true;
		    $Pagination = new Pagination('cg', $AppearancesPerPage);

			// Search query exists
			if (!empty($_GET['q']) && mb_strlen(trim($_GET['q'])) > 0){
				$SearchQuery = preg_replace(new RegExp('[^\w\d\s\*\?]'),'',trim($_GET['q']));
				$title .= "$SearchQuery - ";
				if (preg_match(new RegExp('[\*\?]'), $SearchQuery)){
					$queryString = new ElasticsearchDSL\Query\FullText\QueryStringQuery(
						$SearchQuery,
						[
							'fields' => ['label^20','tags'],
							'default_operator' => 'and',
							'phrase_slop' => 3,
						]
					);
					$search->addQuery($queryString);
					$orderByID = false;
				}
				else {
					$multiMatch = new ElasticsearchDSL\Query\FullText\MultiMatchQuery(
						['label^20','tags'],
						$SearchQuery,
						[
							'type' => 'cross_fields',
							'minimum_should_match' => '100%',
						]
					);
					$search->addQuery($multiMatch);
				}
			}
			else {
				$sort = new ElasticsearchDSL\Sort\FieldSort('order','asc');
		        $search->addSort($sort);
			}

			$boolquery = new BoolQuery();
			if (Permission::insufficient('staff'))
				$boolquery->add(new TermQuery('private', true), BoolQuery::MUST_NOT);
			$boolquery->add(new TermQuery('ishuman', $this->_EQG), BoolQuery::MUST);
			$search->addQuery($boolquery);

			$search->setSource(false);
			$search = $search->toArray();
			$search = CGUtils::searchElastic($search, $Pagination);
			$Pagination->calcMaxPages($search['hits']['total']);

			if (!empty($search['hits']['hits'])){
				$ids = [];
				foreach($search['hits']['hits'] as $hit)
					$ids[] = $hit['_id'];

				$Ponies = $Database->where('id IN ('.implode(',', $ids).')')->orderBy('order','ASC')->get('appearances');
			}
		}
		if (!$elasticAvail) {
		    $_EntryCount = $Database->where('ishuman',$this->_EQG)->where('id != 0')->count('appearances');

		    $Pagination = new Pagination('cg', $AppearancesPerPage, $_EntryCount);
		    $Ponies = Appearances::get($this->_EQG, $Pagination->getLimit());
		}

		if (isset($_REQUEST['GOFAST'])){
			if (empty($Ponies[0]['id']))
				Response::fail('The search returned no results.');
			Response::done(array('goto' => "$this->_cgPath/v/{$Ponies[0]['id']}-".Appearances::getSafeLabel($Ponies[0])));
		}

		CoreUtils::fixPath("$this->_cgPath/{$Pagination->page}".(!empty($Restrictions)?"?q=$SearchQuery":''));
		$heading = ($this->_EQG?'EQG ':'')."$Color Guide";
		$title .= "Page {$Pagination->page} - $heading";

		if (isset($_GET['js']))
			$Pagination->respond(Appearances::getHTML($Ponies, NOWRAP), '#list');

		$settings = array(
			'title' => $title,
			'heading' => $heading,
			'css' => array($this->do),
			'js' => array('jquery.qtip', 'jquery.ctxmenu', $this->do, 'paginate'),
			'import' => [
				'EQG' => $this->_EQG,
				'Ponies' => $Ponies,
				'Pagination' => $Pagination,
				'elasticAvail' => $elasticAvail,
			],
		);
		if (Permission::sufficient('staff')){
			$settings['css'] = array_merge($settings['css'], $this->_GUIDE_MANAGE_CSS);
			$settings['js'] = array_merge($settings['js'], $this->_GUIDE_MANAGE_JS);
		}
		CoreUtils::loadPage($settings, $this);
	}

	function personalGuide($params){
		global $Database, $Color;

		$this->_initPersonal($params);

		$title = '';
		$AppearancesPerPage = UserPrefs::get('cg_itemsperpage');
		$Ponies = [];
	    $_EntryCount = $Database->where('owner',$this->_owner->id)->count('appearances');

	    $Pagination = new Pagination("@{$this->_owner->name}/cg", $AppearancesPerPage, $_EntryCount);
	    $Ponies = $this->_owner->getPCGAppearances($Pagination);

		CoreUtils::fixPath("$this->_cgPath/{$Pagination->page}");
		$heading = CoreUtils::posess($this->_owner->name)." Personal $Color Guide";
		$title .= "Page {$Pagination->page} - $heading";

		if (isset($_GET['js']))
			$Pagination->respond(Appearances::getHTML($Ponies, NOWRAP), '#list');

		$settings = array(
			'title' => $title,
			'heading' => $heading,
			'css' => array('colorguide'),
			'js' => array('jquery.qtip', 'jquery.ctxmenu', 'colorguide', 'paginate'),
			'view' => 'user-colorguide',
			'import' => [
				'Ponies' => $Ponies,
				'Pagination' => $Pagination,
				'Owner' => $this->_owner,
				'isOwner' => $this->_isOwner,
			],
		);
		if ($this->_isOwner){
			$settings['css'] = array_merge($settings['css'], $this->_GUIDE_MANAGE_CSS);
			$settings['js'] = array_merge($settings['js'], $this->_GUIDE_MANAGE_JS);
		}
		CoreUtils::loadPage($settings, $this);
	}

	const CM_BASIC_COLS = 'cmid,favme,favme_rotation,preview_src,facing';
	function export(){
		global $Database;

		if (!Permission::sufficient('developer'))
			CoreUtils::notFound();
		$JSON = array(
			'Appearances' => array(),
			'Tags' => array(),
		);

		$Tags = $Database->orderBy('tid','ASC')->get('tags');
		if (!empty($Tags)) foreach ($Tags as $t){
			$JSON['Tags'][$t['tid']] = $t;
		}

		$Appearances = Appearances::get(null);
		if (!empty($Appearances)) foreach ($Appearances as $p){
			$AppendAppearance = $p;
			unset($AppendAppearance['owner']);

			$AppendAppearance['notes'] = isset($AppendAppearance['notes'])
				? CoreUtils::trim($AppendAppearance['notes'],true)
				: '';

			$Database->disableAutoClass();
			$CM = Cutiemarks::get($p['id'], '\'http://fav.me/\'||favme as link, facing', false);
			if (!empty($CM))
				$AppendAppearance['CutieMark'] = $CM;

			$AppendAppearance['ColorGroups'] = array();
			if (empty($AppendAppearance['private'])){
				$ColorGroups = ColorGroups::get($p['id']);
				if (!empty($ColorGroups)){
					$AllColors = ColorGroups::getColorsForEach($ColorGroups);
					foreach ($ColorGroups as $cg){
						$AppendColorGroup = $cg;
						unset($AppendColorGroup['ponyid']);

						$AppendColorGroup['Colors'] = array();
						if (!empty($AllColors[$cg['groupid']]))
							foreach ($AllColors[$cg['groupid']] as $c){
								unset($c['groupid']);
								$AppendColorGroup['Colors'][] = $c;
							};

						$AppendAppearance['ColorGroups'][$cg['groupid']] = $AppendColorGroup;
					}
				}
			}
			else $AppendAppearance['ColorGroups']['_hidden'] = true;

			$AppendAppearance['TagIDs'] = array();
			$TagIDs = Tags::getFor($p['id'],null,null,true);
			if (!empty($TagIDs)){
				foreach ($TagIDs as $t)
					$AppendAppearance['TagIDs'][] = $t['tid'];
			}

			$AppendAppearance['RelatedAppearances'] = array();
			$RelatedIDs = Appearances::getRelated($p['id']);
			if (!empty($RelatedIDs))
				foreach ($RelatedIDs as $rel)
					$AppendAppearance['RelatedAppearances'][] = $rel['id'];

			$JSON['Appearances'][$p['id']] = $AppendAppearance;
		}

		$data = JSON::encode($JSON, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		$data = preg_replace_callback('/^\s+/m', function($match){
			return str_pad('',CoreUtils::length($match[0])/4,"\t", STR_PAD_LEFT);
		}, $data);

		CoreUtils::downloadFile($data, 'mlpvc-colorguide.json');
	}

	function getTags(){
		global $Database, $TAG_NAME_REGEX;

		if (!Permission::sufficient('staff'))
			Response::fail();

		$except = (new Input('not','int',array(Input::IS_OPTIONAL => true)))->out();
		if ((new Input('action','string',array(Input::IS_OPTIONAL => true)))->out() === 'synon'){
			if (isset($except))
				$Database->where('tid',$except);
			$Tag = $Database->where('"synonym_of" IS NOT NULL')->getOne('tags');
			if (!empty($Tag)){
				$Syn = Tags::getSynonymOf($Tag,'name');
				Response::fail("This tag is already a synonym of <strong>{$Syn['name']}</strong>.<br>Would you like to remove the synonym?",array('undo' => true));
			}
		}

		$viaAutocomplete = !empty($_GET['s']);
		$limit = null;
		$cols = "tid, name, type";
		if ($viaAutocomplete){
			if (!preg_match($TAG_NAME_REGEX, $_GET['s']))
				CGUtils::autocompleteRespond('[]');

			$query = CoreUtils::trim(strtolower($_GET['s']));
			$TagCheck = CGUtils::checkEpisodeTagName($query);
			if ($TagCheck !== false)
				$query = $TagCheck;
			$Database->where('name',"%$query%",'LIKE');
			$limit = 5;
			$cols = "tid, name, 'typ-'||type as type";
			$Database->orderBy('uses','DESC');
		}
		else $Database->orderBy('type','ASC')->where('"synonym_of" IS NULL');

		if (isset($except))
			$Database->where('tid',$except,'!=');
		$Tags = $Database->orderBy('name','ASC')->get('tags',$limit,"$cols, uses, synonym_of");
		if ($viaAutocomplete)
			foreach ($Tags as &$t){
				if (empty($t['synonym_of']))
					continue;
				$Syn = $Database->where('tid', $t['synonym_of'])->getOne('tags','name');
				if (!empty($Syn))
					$t['synonym_target'] = $Syn['name'];
			};

		CGUtils::autocompleteRespond(empty($Tags) ? '[]' : $Tags);
	}

	function reindex(){
		if (Permission::insufficient('developer'))
			Response::fail();
		Appearances::reindex();
	}

	function appearanceAction($params){
		global $Database, $Color, $currentUser;

		$this->_initPersonal($params, false);

		if (Permission::insufficient('member'))
			Response::fail();

		$action = $params['action'];
		$creating = $action === 'make';

		if ($creating){
			if (Permission::insufficient('staff') && !$this->_personalGuide)
				Response::fail("You don’t have permission to add appearances to the official Color Guide");

			if ($this->_personalGuide){
				try {
					$availSlots = $currentUser->getPCGAvailableSlots();
				}
				catch (NoPCGSlotsException $e){
					Response::fail("You don’t have any slots. If you’d like to know how to get some, click the blue <strong class='color-darkblue'>What?</strong> button on your <a href='/u'>Account page</a> to learn more about this feature.");
				}
				if ($availSlots === 0){
					$remain = Users::calculatePersonalCGNextSlot($currentUser->getPCGAppearances(null, true));
					Response::fail("You don’t have enough slots to create another appearance. Delete other ones or finish $remain more ".CoreUtils::makePlural('request',$remain).'.');
				}
			}
		}
		else {
			$this->_getAppearance($params);

			if (Permission::insufficient('staff') && !$this->_isOwner)
				Response::fail();
		}

		switch ($action){
			case "get":
				Response::done(array(
					'label' => $this->_appearance['label'],
					'notes' => $this->_appearance['notes'],
					'private' => $this->_appearance['private'],
				));
			break;
			case "set":
			case "make":
				/** @var $data array */
				$data = array(
					'ishuman' => $this->_personalGuide ? null : $this->_EQG,
				);

				$label = (new Input('label','string',array(
					Input::IN_RANGE => [4,70],
					Input::CUSTOM_ERROR_MESSAGES => array(
						Input::ERROR_MISSING => 'Appearance name is missing',
						Input::ERROR_RANGE => 'Appearance name must be beetween @min and @max characters long',
					)
				)))->out();
				CoreUtils::checkStringValidity($label, "Appearance name", INVERSE_PRINTABLE_ASCII_PATTERN);
				if (!$creating)
					$Database->where('id', $this->_appearance['id'], '!=');
				if ($this->_personalGuide)
					$dupe = $Database->where('owner', $currentUser->id)->where('label', $label)->getOne('appearances');
				else $dupe = $Database->where('ishuman', $data['ishuman'])->where('label', $label)->getOne('appearances');
				if (!empty($dupe)){
					if ($this->_personalGuide)
						Response::fail('You already have an appearance with the same name in your Personal Color Guide');

					$eqg_url = $this->_EQG ? '/eqg':'';
					Response::fail("An appearance <a href='/cg$eqg_url/v/{$dupe['id']}' target='_blank'>already esists</a> in the ".($this->_EQG?'EQG':'Pony').' guide with this exact name. Consider adding an identifier in backets or choosing a different name.');
				}
				$data['label'] = $label;

				$notes = (new Input('notes','text',array(
					Input::IS_OPTIONAL => true,
					Input::IN_RANGE => $creating || $this->_appearance['id'] !== 0 ? [null,1000] : null,
					Input::CUSTOM_ERROR_MESSAGES => array(
						Input::ERROR_RANGE => 'Appearance notes cannot be longer than @max characters',
					)
				)))->out();
				if (isset($notes)){
					CoreUtils::checkStringValidity($notes, "Appearance notes", INVERSE_PRINTABLE_ASCII_PATTERN);
					$notes = CoreUtils::sanitizeHtml($notes);
					if ($creating || $notes !== $this->_appearance['notes'])
						$data['notes'] = $notes;
				}
				else $data['notes'] = null;

				$data['private'] = isset($_POST['private']);

				if ($creating){
					$data['order'] = $Database->getOne('appearances','MAX("order") as "order"')['order']+1;
					if (Permission::insufficient('staff') || $this->_personalGuide){
						$data['owner'] = $currentUser->id;
						$ownerName = $currentUser->name;
					}
				}

				$query = $creating
					? $Database->insert('appearances', $data, 'id')
					: $Database->where('id', $this->_appearance['id'])->update('appearances', $data);
				if (!$query)
					Response::dbError();

				$EditedAppearance = Appearances::updateIndex($creating ? $query : $this->_appearance['id'], '*');

				if ($creating){
					$data['id'] = $query;
					$response = array(
						'message' => 'Appearance added successfully',
						'goto' => (isset($ownerName) ? "/@$ownerName" : '')."/cg/v/$query",
					);
					$usetemplate = isset($_POST['template']);
					if ($usetemplate){
						try {
							Appearances::applyTemplate($query, $this->_EQG);
						}
						catch (\Exception $e){
							$response['message'] .= ", but applying the template failed";
							$response['info'] = "The common color groups could not be added.<br>Reason: ".$e->getMessage();
							$usetemplate = false;
						}
					}

					Logs::logAction('appearances',array(
						'action' => 'add',
					    'id' => $data['id'],
					    'order' => $data['order'],
					    'label' => $data['label'],
					    'notes' => $data['notes'],
					    'ishuman' => $data['ishuman'],
						'usetemplate' => $usetemplate ? 1 : 0,
						'private' => $data['private'] ? 1 : 0,
						'owner' => $data['owner'] ?? null,
					));
					Response::done($response);
				}

				CGUtils::clearRenderedImages($this->_appearance['id'], array(CGUtils::CLEAR_PALETTE, CGUtils::CLEAR_PREVIEW));

				$response = array();
				$diff = array();
				foreach (array('label','notes','private','owner') as $key){
					if ($EditedAppearance[$key] !== $this->_appearance[$key]){
						$diff["old$key"] = $this->_appearance[$key];
						$diff["new$key"] = $EditedAppearance[$key];
					}
				}
				if (!empty($diff)) Logs::logAction('appearance_modify',array(
					'ponyid' => $this->_appearance['id'],
					'changes' => JSON::encode($diff),
				));

				if (!$this->_appearancePage){
					$response['label'] = $EditedAppearance['label'];
					if ($data['label'] !== $this->_appearance['label'])
						$response['newurl'] = $this->_appearance['id'].'-'.Appearances::getSafeLabel($EditedAppearance);
					$response['notes'] = Appearances::getNotesHTML($EditedAppearance, NOWRAP);
				}

				Response::done($response);
			break;
			case "delete":
				if ($this->_appearance['id'] === 0)
					Response::fail('This appearance cannot be deleted');

				$Tagged = Tags::getFor($this->_appearance['id'], null, true, false);

				if (!$Database->where('id', $this->_appearance['id'])->delete('appearances'))
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
					error_log('ElasticSearch server was down when server attempted to remove appearance '.$this->_appearance['id']);
				}

				if (!empty($Tagged))
					foreach($Tagged as $tag){
						Tags::updateUses($tag['tid']);
					};

				$fpath = APPATH."img/cg/{$this->_appearance['id']}.png";
				if (file_exists($fpath))
					unlink($fpath);

				CGUtils::clearRenderedImages($this->_appearance['id']);

				Logs::logAction('appearances',array(
					'action' => 'del',
				    'id' => $this->_appearance['id'],
				    'order' => $this->_appearance['order'],
				    'label' => $this->_appearance['label'],
				    'notes' => $this->_appearance['notes'],
				    'ishuman' => $this->_appearance['ishuman'],
				    'added' => $this->_appearance['added'],
				    'private' => $this->_appearance['private'],
				    'owner' => $this->_appearance['owner'],
				));

				Response::success('Appearance removed');
			break;
			case "getcgs":
				$cgs = ColorGroups::get($this->_appearance['id'],'groupid, label');
				if (empty($cgs))
					Response::fail('This appearance does not have any color groups');
				Response::done(array('cgs' => $cgs));
			break;
			case "setcgs":
				$order = (new Input('cgs','int[]',array(
					Input::CUSTOM_ERROR_MESSAGES => array(
						Input::ERROR_MISSING => "$Color group order data missing"
					)
				)))->out();
				$oldCGs = ColorGroups::get($this->_appearance['id']);
				$possibleIDs = array();
				foreach ($oldCGs as $cg)
					$possibleIDs[$cg['groupid']] = true;
				foreach ($order as $i => $GroupID){
					if (empty($possibleIDs[$GroupID]))
						Response::fail("There’s no group with the ID of $GroupID on this appearance");

					$Database->where('groupid', $GroupID)->update('colorgroups',array('order' => $i));
				}
				$newCGs = ColorGroups::get($this->_appearance['id']);

				CGUtils::clearRenderedImages($this->_appearance['id'], array(CGUtils::CLEAR_PALETTE, CGUtils::CLEAR_PREVIEW));

				$oldCGs = ColorGroups::stringify($oldCGs);
				$newCGs = ColorGroups::stringify($newCGs);
				if ($oldCGs !== $newCGs) Logs::logAction('cg_order',array(
					'ponyid' => $this->_appearance['id'],
					'oldgroups' => $oldCGs,
					'newgroups' => $newCGs,
				));

				Response::done(array('cgs' => Appearances::getColorsHTML($this->_appearance, NOWRAP, !$this->_appearancePage, $this->_appearancePage)));
			break;
			case "delsprite":
			case "getsprite":
			case "setsprite":
				$fname = $this->_appearance['id'].'.png';
				$finalpath = SPRITE_PATH.$fname;

				switch ($action){
					case "setsprite":
						CGUtils::processUploadedImage('sprite', $finalpath, array('image/png'), [300], [700,300]);
						CGUtils::clearRenderedImages($this->_appearance['id']);
					break;
					case "delsprite":
						if (empty(Appearances::getSpriteURL($this->_appearance['id'])))
							Response::fail('No sprite file found');

						if (!unlink($finalpath))
							Response::fail('File could not be deleted');
						CGUtils::clearRenderedImages($this->_appearance['id']);

						Response::done(array('sprite' => DEFAULT_SPRITE));
					break;
				}

				Response::done(array("path" => "/cg/v/{$this->_appearance['id']}s.png?t=".filemtime($finalpath)));
			break;
			case "getrelations":
				if (isset($this->_appearance['owner']))
					Response::fail('Relations are unavailable for appearances in personal guides');

				$CheckTag = array();

				$RelatedAppearances = Appearances::getRelated($this->_appearance['id']);
				$RelatedAppearanceIDs = array();
				foreach ($RelatedAppearances as $p)
					$RelatedAppearanceIDs[$p['id']] = $p['mutual'];

				$Appearances = $Database->where('ishuman', $this->_EQG)->where('"id" NOT IN (0,'.$this->_appearance['id'].')')->orderBy('label','ASC')->get('appearances',null,'id,label');

				$Sorted = array(
					'unlinked' => array(),
					'linked' => array(),
				);
				foreach ($Appearances as $a){
					$linked = isset($RelatedAppearanceIDs[$a['id']]);
					if ($linked)
						$a['mutual'] = $RelatedAppearanceIDs[$a['id']];
					$Sorted[$linked ? 'linked' : 'unlinked'][] = $a;
				}

				Response::done($Sorted);
			break;
			case "setrelations":
				if (isset($this->_appearance['owner']))
					Response::fail('Relations are unavailable for appearances in personal guides');

				$AppearanceIDs = (new Input('ids','int[]',array(
					Input::IS_OPTIONAL => true,
					Input::CUSTOM_ERROR_MESSAGES => array(
						Input::ERROR_INVALID => 'Appearance ID list is invalid',
					)
				)))->out();
				$MutualIDs = (new Input('mutuals','int[]',array(
					Input::IS_OPTIONAL => true,
					Input::CUSTOM_ERROR_MESSAGES => array(
						Input::ERROR_INVALID => 'Mutial relation ID list is invalid',
					)
				)))->out();

				$appearances = [];
				if (!empty($AppearanceIDs))
					foreach ($AppearanceIDs as $id){
						$appearances[$id] = true;
					};

				$mutuals = array();
				if (!empty($MutualIDs))
					foreach ($MutualIDs as $id){
						$mutuals[$id] = true;
						unset($appearances[$id]);
					};

				$Database->where('source', $this->_appearance['id'])->delete('appearance_relations');
				if (!empty($appearances))
					foreach ($appearances as $id => $_){
						@$Database->insert('appearance_relations', array(
							'source' => $this->_appearance['id'],
							'target' => $id,
							'mutual' => isset($mutuals[$id]),
						));
					};
				$Database->where('target', $this->_appearance['id'])->where('mutual', true)->delete('appearance_relations');
				if (!empty($mutuals))
					foreach ($MutualIDs as $id){
						@$Database->insert('appearance_relations', array(
							'source' => $id,
							'target' => $this->_appearance['id'],
							'mutual' => true,
						));
					};

				$out = [];
				if ($this->_appearancePage)
					$out['section'] = Appearances::getRelatedHTML(Appearances::getRelated($this->_appearance['id']));
				Response::done($out);
			break;
			case "getcms":
				$CMs = Cutiemarks::get($this->_appearance['id'],'cmid,favme,favme_rotation,preview_src,facing',false);
				$ProcessedCMs = Cutiemarks::get($this->_appearance['id'],'ponyid,favme,preview_src,facing');

				Response::done(['cms' => $CMs, 'preview' => Cutiemarks::getListForAppearancePage($ProcessedCMs, NOWRAP)]);
			break;
			case "getcmpreview":
				$CMs = [];

				$CM1 = [ 'ponyid' => $this->_appearance['id'] ];
				Cutiemarks::postProcess($CM1, 0);
				$CMs[] = new Cutiemark($CM1);

				$CM2 = [ 'ponyid' => $this->_appearance['id'] ];
				if (Cutiemarks::postProcess($CM2, 1))
					$CMs[] = new Cutiemark($CM2);

				Cutiemarks::processSymmetrical($CMs);
				Response::done(['html' => Cutiemarks::getListForAppearancePage($CMs, NOWRAP)]);
			break;
			case "setcms":
				$data = [];
				$newFacingValues = [];
				for ($i = 0; $i < 2; $i++){
					$data[$i] = [
						'ponyid' => $this->_appearance['id'],
					];
					if (Cutiemarks::postProcess($data[$i], $i) === false){
						unset($data[$i]);
						break;
					}
					if (isset($_POST['cmid'][$i])){
						$data[$i]['cmid'] = intval($_POST['cmid'][$i]);
						if (!$Database->where('cmid', $data[$i]['cmid'])->has('cutiemarks'))
							Response::fail('The cutie mark you\'re trying to update does not exist');
					}
					$newFacingValues[] = $data[$i]['facing'];
				}

				$CurrentCMs = Cutiemarks::get($this->_appearance['id']);
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
					CGUtils::clearRenderedImages($this->_appearance['id'], $cleanRendered);

				foreach ($data as $cmdata){
					if (isset($cmdata['cmid'])){
						$Database->where('cmid', $cmdata['cmid']);
						unset($cmdata['cmid']);
						$Database->update('cutiemarks', $cmdata);
					}
					else $Database->insert('cutiemarks', $cmdata);
				}

				$CutieMarks = Cutiemarks::get($this->_appearance['id'], '*', false);
				$olddata = Cutiemarks::convertDataForLogs($CurrentCMs);
				$newdata = Cutiemarks::convertDataForLogs($CutieMarks);
				if ($olddata !== $newdata)
					Logs::logAction('cm_modify',[
						'ponyid' => $this->_appearance['id'],
						'olddata' => $olddata,
						'newdata' => $newdata,
					]);

				$data = [];
				if ($this->_appearancePage && !empty($CutieMarks))
					$data['html'] = Cutiemarks::getListForAppearancePage($CutieMarks);
				Response::done($data);
			break;
			case "delcms":
				$CMs = Cutiemarks::get($this->_appearance['id']);
				if (empty($CMs))
					Response::done();
				if (!$Database->where('ponyid', $this->_appearance['id'])->delete('cutiemarks'))
					Response::dbError('Removing Cutie Marka failed');

				Logs::logAction('cm_delete',[
					'ponyid' => $this->_appearance['id'],
					'data' => Cutiemarks::convertDataForLogs($CMs),
				]);

				Response::done();
			break;
			case "clear-cache":
				if (!CGUtils::clearRenderedImages($this->_appearance['id']))
					Response::fail('Cache could not be purged');

				Response::success('Cached images have been removed, they will be re-generated on the next request');
			break;
			case "tag":
			case "untag":
				if (isset($this->_appearance['owner']))
					Response::fail('Tagging is unavailable for appearances in personal guides');

				if ($this->_appearance['id'] === 0)
					Response::fail("This appearance cannot be tagged");

				switch ($action){
					case "tag":
						$tag_name = CGUtils::validateTagName('tag_name');

						$TagCheck = CGUtils::checkEpisodeTagName($tag_name);
						if ($TagCheck !== false)
							$tag_name = $TagCheck;

						$Tag = Tags::getActual($tag_name, 'name');
						if (empty($Tag))
							Response::fail("The tag $tag_name does not exist.<br>Would you like to create it?",array(
								'cancreate' => $tag_name,
								'typehint' => $TagCheck !== false ? 'ep' : null,
							));

						if ($Database->where('ponyid', $this->_appearance['id'])->where('tid', $Tag['tid'])->has('tagged'))
							Response::fail('This appearance already has this tag');

						if (!$Database->insert('tagged',array(
							'ponyid' => $this->_appearance['id'],
							'tid' => $Tag['tid'],
						))) Response::dbError();
					break;
					case "untag":
						$tag_id = (new Input('tag','int',array(
							Input::CUSTOM_ERROR_MESSAGES => array (
								Input::ERROR_MISSING => 'Tag ID is missing',
								Input::ERROR_INVALID => 'Tag ID (@value) is invalid',
							)
						)))->out();
						$Tag = $Database->where('tid',$tag_id)->getOne('tags');
						if (empty($Tag))
							Response::fail('This tag does not exist');
						if (!empty($Tag['synonym_of'])){
							$Syn = Tags::getSynonymOf($Tag,'name');
							Response::fail('Synonym tags cannot be removed from appearances directly. '.
							        "If you want to remove this tag you must remove <strong>{$Syn['name']}</strong> or the synonymization.");
						}

						if ($Database->where('ponyid', $this->_appearance['id'])->where('tid', $Tag['tid'])->has('tagged')){
							if (!$Database->where('ponyid', $this->_appearance['id'])->where('tid', $Tag['tid'])->delete('tagged'))
								Response::dbError();
						}
					break;
				}

				Appearances::updateIndex($this->_appearance['id']);

				Tags::updateUses($Tag['tid']);
				if (!empty(CGUtils::GROUP_TAG_IDS_ASSOC[$Tag['tid']]))
					Appearances::getSortReorder($this->_EQG);

				$response = array('tags' => Appearances::getTagsHTML($this->_appearance['id'], NOWRAP));
				if ($this->_appearancePage && $Tag['type'] === 'ep'){
					$response['needupdate'] = true;
					$response['eps'] = Appearances::getRelatedEpisodesHTML($this->_appearance, $this->_EQG);
				}
				Response::done($response);
			break;
			case "applytemplate":
				try {
					Appearances::applyTemplate($this->_appearance['id'], $this->_EQG);
				}
				catch (\Exception $e){
					Response::fail("Applying the template failed. Reason: ".$e->getMessage());
				}

				Response::done(array('cgs' => Appearances::getColorsHTML($this->_appearance, NOWRAP, !$this->_appearancePage, $this->_appearancePage)));
			break;
			default: CoreUtils::notFound();
		}
	}

	function recountTagUses(){
		if (Permission::insufficient('staff'))
			Response::fail();

		$tagIDs = (new Input('tagids','int[]',array(
			Input::CUSTOM_ERROR_MESSAGES => array(
				Input::ERROR_MISSING => 'Missing list of tags to update',
				Input::ERROR_INVALID => 'List of tags is invalid',
			)
		)))->out();
		$counts = array();
		$updates = 0;
		foreach ($tagIDs as $tid){
			if (Tags::getActual($tid,'tid',RETURN_AS_BOOL)){
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
			array('counts' => $counts)
		);
	}

	function tagAction($params){
		global $Database;

		$this->_initialize($params);
		if (Permission::insufficient('staff'))
			CoreUtils::notFound();

		$action = $params['action'];
		$adding = $action === 'make';

		if (!$adding){
			if (!isset($params['id']))
				Response::fail('Missing tag ID');
			$TagID = intval($params['id'], 10);
			$Tag = $Database->where('tid', $TagID)->getOne('tags',isset($query) ? 'tid, name, type':'*');
			if (empty($Tag))
				Response::fail("This tag does not exist");
		}

		$data = array();

		switch ($action){
			case 'get':
				Response::done($Tag);
			case 'del':
				$AppearanceID = Appearances::validateAppearancePageID();

				$tid = !empty($Tag['synonym_of']) ? $Tag['synonym_of'] : $Tag['tid'];
				$Uses = $Database->where('tid',$tid)->get('tagged',null,'ponyid');
				$UseCount = count($Uses);
				if (!isset($_POST['sanitycheck'])){
					if ($UseCount > 0)
						Response::fail('<p>This tag is currently used on '.CoreUtils::makePlural('appearance',$UseCount,PREPEND_NUMBER).'</p><p>Deleting will <strong class="color-red">permanently remove</strong> the tag from those appearances!</p><p>Are you <em class="color-red">REALLY</em> sure about this?</p>',array('confirm' => true));
				}

				if (!$Database->where('tid', $Tag['tid'])->delete('tags'))
					Response::dbError();

				if (!empty(CGUtils::GROUP_TAG_IDS_ASSOC[$Tag['tid']]))
					Appearances::getSortReorder($this->_EQG);
				foreach ($Uses as $use)
					Appearances::updateIndex($use['ponyid']);

				$Appearance = $Database->where('id',$AppearanceID)->getOne('appearances','id,ishuman');
				Response::success('Tag deleted successfully', isset($AppearanceID) && $Tag['type'] === 'ep' ? array(
					'needupdate' => true,
					'eps' => Appearances::getRelatedEpisodesHTML($Appearance, $this->_EQG),
				) : null);
			break;
			case 'unsynon':
				if (empty($Tag['synonym_of']))
					Response::done();

				$keep_tagged = isset($_POST['keep_tagged']);
				$uses = 0;
				$Target = $Database->where('tid', $Tag['synonym_of'])->getOne('tags','tid');
				if (!empty($Target)){
					$TargetTagged = $Database->where('tid', $Target['tid'])->get('tagged', null, 'ponyid');
					if ($keep_tagged){
						foreach ($TargetTagged as $tg){
							if (!$Database->insert('tagged', array('tid' => $Tag['tid'], 'ponyid' => $tg['ponyid'])))
								Response::fail("Tag synonym removal process failed, please re-try.<br>Technical details: ponyid={$tg['ponyid']} tid={$Tag['tid']}");
							$uses++;
						}
					}
					else {
						foreach ($TargetTagged as $tg)
							Appearances::updateIndex($tg['ponyid']);
					}
				}
				else $keep_tagged = false;

				if (!$Database->where('tid', $Tag['tid'])->update('tags', array('synonym_of' => null, 'uses' => $uses)))
					Response::dbError();

				Response::done(array('keep_tagged' => $keep_tagged));
			break;
			case 'make':
			case 'set':
				$data['name'] = CGUtils::validateTagName('name');

				$epTagName = CGUtils::checkEpisodeTagName($data['name']);
				$surelyAnEpisodeTag = $epTagName !== false;
				$type = (new Input('type',function($value){
					if (!isset(Tags::$TAG_TYPES_ASSOC[$value]))
						return Input::ERROR_INVALID;
				},array(
					Input::IS_OPTIONAL => true,
					Input::CUSTOM_ERROR_MESSAGES => array(
						Input::ERROR_INVALID => 'Invalid tag type: @value',
					)
				)))->out();
				if (empty($type)){
					if ($surelyAnEpisodeTag)
						$data['name'] = $epTagName;
					$data['type'] = $epTagName === false ? null : 'ep';
				}
				else {
					if ($type == 'ep'){
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
					$Database->where('tid', $Tag['tid'],'!=');
				if ($Database->where('name', $data['name'])->where('type', $data['type'])->has('tags'))
					Response::fail("A tag with the same name and type already exists");

				$data['title'] = (new Input('title','string',array(
					Input::IS_OPTIONAL => true,
					Input::IN_RANGE => [null,255],
					Input::CUSTOM_ERROR_MESSAGES => array(
						Input::ERROR_RANGE => 'Tag title cannot be longer than @max characters'
					)
				)))->out();

				if ($adding){
					$TagID = $Database->insert('tags', $data, 'tid');
					if (!$TagID)
						Response::dbError();
					$data['tid'] = $TagID;

					$AppearanceID = (new Input('addto','int',array(Input::IS_OPTIONAL => true)))->out();
					if (isset($AppearanceID)){
						if ($AppearanceID === 0)
							Response::success("The tag was created, <strong>but</strong> it could not be added to the appearance because it can’t be tagged.");

						$Appearance = $Database->where('id', $AppearanceID)->getOne('appearances');
						if (empty($Appearance))
							Response::success("The tag was created, <strong>but</strong> it could not be added to the appearance (<a href='/cg/v/$AppearanceID'>#$AppearanceID</a>) because it doesn’t seem to exist. Please try adding the tag manually.");

						if (!$Database->insert('tagged',array(
							'tid' => $data['tid'],
							'ponyid' => $Appearance['id']
						))) Response::dbError();
						Appearances::updateIndex($Appearance['id']);
						Tags::updateUses($data['tid']);
						$r = array('tags' => Appearances::getTagsHTML($Appearance['id'], NOWRAP));
						if ($this->_appearancePage){
							$r['needupdate'] = true;
							$r['eps'] = Appearances::getRelatedEpisodesHTML($Appearance, $this->_EQG);
						}
						Response::done($r);
					}
				}
				else {
					$Database->where('tid', $Tag['tid'])->update('tags', $data);
					$data = array_merge($Tag, $data);
					$ponyid = !empty($this->_appearancePage) ? intval($_POST['APPEARANCE_PAGE'],10) : null;
					$tagrelations = $Database->where('tid', $Tag['tid'])->get('tagged');
					foreach ($tagrelations as $tagged){
						Appearances::updateIndex($tagged['ponyid']);

						if ($tagged['ponyid'] === $ponyid){
							$data['needupdate'] = true;
							$Appearance = $Database->where('id', $ponyid)->getOne('appearances');
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
			if ($synoning && !empty($Tag['synonym_of']))
				Response::fail('This tag is already synonymized with a different tag');

			$targetid = (new Input('targetid','int',array(
				Input::CUSTOM_ERROR_MESSAGES => array(
					Input::ERROR_MISSING => 'Missing target tag ID',
				)
			)))->out();
			$Target = $Database->where('tid', $targetid)->getOne('tags');
			if (empty($Target))
				Response::fail('Target tag does not exist');
			if (!empty($Target['synonym_of']))
				Response::fail('Synonym tags cannot be synonymization targets');

			$_TargetTagged = $Database->where('tid', $Target['tid'])->get('tagged',null,'ponyid');
			$TargetTagged = array();
			foreach ($_TargetTagged as $tg)
				$TargetTagged[] = $tg['ponyid'];

			$Tagged = $Database->where('tid', $Tag['tid'])->get('tagged',null,'ponyid');
			foreach ($Tagged as $tg){
				if (in_array($tg['ponyid'], $TargetTagged)) continue;

				if (!$Database->insert('tagged',array(
					'tid' => $Target['tid'],
					'ponyid' => $tg['ponyid']
				))) Response::fail('Tag '.($merging?'merging':'synonimizing')." failed, please re-try.<br>Technical details: ponyid={$tg['ponyid']} tid={$Target['tid']}");
			}
			if ($merging)
				// No need to delete "tagged" table entries, constraints do it for us
				$Database->where('tid', $Tag['tid'])->delete('tags');
			else {
				$Database->where('tid', $Tag['tid'])->delete('tagged');
				$Database->where('tid', $Tag['tid'])->update('tags', array('synonym_of' => $Target['tid'], 'uses' => 0));
			}
			foreach ($TargetTagged as $id)
				Appearances::updateIndex($id);

			Tags::updateUses($Target['tid']);
			Response::success('Tags successfully '.($merging?'merged':'synonymized'), $synoning || $merging ? array('target' => $Target) : null);
		}

		CoreUtils::notFound();
	}

	function colorGroupAction($params){
		global $Database, $color, $Color, $HEX_COLOR_REGEX, $currentUser;

		$this->_initPersonal($params, false);
		if (Permission::insufficient('staff') && !$this->_isOwner)
			Response::fail();

		$action = $params['action'];
		$adding = $action === 'make';

		if (!$adding){
			if (empty($params['id']))
				Response::fail('Missing color group ID');
			$GroupID = intval($params['id'], 10);
			$Group = $Database->where('groupid', $GroupID)->getOne('colorgroups');
			if (empty($GroupID))
				Response::fail("There’s no $color group with the ID of $GroupID");

			if ($action === 'get'){
				$Group['Colors'] = ColorGroups::getColors($Group['groupid']);
				Response::done($Group);
			}

			if ($action === 'del'){
				if (!$Database->where('groupid', $Group['groupid'])->delete('colorgroups'))
					Response::dbError();

				Logs::logAction('cgs',array(
					'action' => 'del',
					'groupid' => $Group['groupid'],
					'ponyid' => $Group['ponyid'],
					'label' => $Group['label'],
					'order' => $Group['order'] ?? null,
				));

				Response::success("$Color group deleted successfully");
			}
		}
		/** @var $data array */
		$data = array();

		$data['label'] = (new Input('label','string',array(
			Input::IN_RANGE => [2,30],
			Input::CUSTOM_ERROR_MESSAGES => array(
				Input::ERROR_MISSING => 'Please specify a group name',
				Input::ERROR_RANGE => 'The group name must be between @min and @max characters in length',
			)
		)))->out();
		CoreUtils::checkStringValidity($data['label'], "$Color group name", INVERSE_PRINTABLE_ASCII_PATTERN, true);

		$major = isset($_POST['major']);
		if ($major){
			$reason = (new Input('reason','string',array(
				Input::IN_RANGE => [null,255],
				Input::CUSTOM_ERROR_MESSAGES => array(
					Input::ERROR_MISSING => 'Please specify a reason for the changes',
					Input::ERROR_RANGE => 'The reason cannot be longer than @max characters',
				),
			)))->out();
			CoreUtils::checkStringValidity($reason, "Change reason", INVERSE_PRINTABLE_ASCII_PATTERN);
		}

		if ($adding){
			$params['id'] = (new Input('ponyid','int',array(
				Input::CUSTOM_ERROR_MESSAGES => array(
					Input::ERROR_MISSING => 'Missing appearance ID',
				)
			)))->out();
			$this->_getAppearance($params);
			$data['ponyid'] = $params['id'];

			// Attempt to get order number of last color group for the appearance
			$LastGroup = ColorGroups::get($params['id'], '"order"', 'DESC', 1);
			$data['order'] =  !empty($LastGroup['order']) ? $LastGroup['order']+1 : 1;

			$GroupID = $Database->insert('colorgroups', $data, 'groupid');
			if (!$GroupID)
				Response::dbError();
			$Group = array('groupid' => $GroupID);
		}
		else $Database->where('groupid', $Group['groupid'])->update('colorgroups', $data);

		$origColors = $adding ? null : ColorGroups::getColors($Group['groupid']);

		$recvColors = (new Input('Colors','json',array(
			Input::CUSTOM_ERROR_MESSAGES => array(
				Input::ERROR_MISSING => "Missing list of {$color}s",
				Input::ERROR_INVALID => "List of {$color}s is invalid",
			)
		)))->out();
		$colors = array();
		foreach ($recvColors as $part => $c){
			$append = array('order' => $part);
			$index = "(index: $part)";

			if (empty($c['label']))
				Response::fail("You must specify a $color name $index");
			$label = CoreUtils::trim($c['label']);
			CoreUtils::checkStringValidity($label, "$Color $index name", INVERSE_PRINTABLE_ASCII_PATTERN);
			$ll = CoreUtils::length($label);
			if ($ll < 3 || $ll > 30)
				Response::fail("The $color name must be between 3 and 30 characters in length $index");
			$append['label'] = $label;

			if (empty($c['hex']))
				Response::fail("You must specify a $color code $index");
			$hex = CoreUtils::trim($c['hex']);
			if (!$HEX_COLOR_REGEX->match($hex, $_match))
				Response::fail("HEX $color is in an invalid format $index");
			$append['hex'] = '#'.strtoupper($_match[1]);

			$colors[] = $append;
		}
		if (!$adding)
			$Database->where('groupid', $Group['groupid'])->delete('colors');
		$colorError = false;
		foreach ($colors as $c){
			$c['groupid'] = $Group['groupid'];
			if (!$Database->insert('colors', $c)){
				$colorError = true;
				error_log("Database error triggered by user {$currentUser->id} ({$currentUser->name}) while saving colors: ".$Database->getLastError());
			}
		}
		if ($colorError)
			Response::fail("There were some issues while saving some of the colors. Please let the developer know about this error, so he can look into why this might've happened.");

		$colon = !$this->_appearancePage;
		$outputNames = $this->_appearancePage;

		if ($adding) $response = array('cgs' => Appearances::getColorsHTML($this->_appearance, NOWRAP, $colon, $outputNames));
		else $response = array('cg' => ColorGroups::getHTML($Group['groupid'], null, NOWRAP, $colon, $outputNames));

		$AppearanceID = $adding ? $this->_appearance['id'] : $Group['ponyid'];
		if ($major){
			Logs::logAction('color_modify',array(
				'ponyid' => $AppearanceID,
				'reason' => $reason,
			));
			if ($this->_appearancePage){
				$FullChangesSection = isset($_POST['FULL_CHANGES_SECTION']);
				$response['changes'] = CGUtils::getChangesHTML(Updates::get($AppearanceID), $FullChangesSection);
				if ($FullChangesSection)
					$response['changes'] = str_replace('@',$response['changes'],CGUtils::CHANGES_SECTION);
			}
			else $response['update'] = Appearances::getUpdatesHTML($AppearanceID);
		}
		CGUtils::clearRenderedImages($AppearanceID, array(CGUtils::CLEAR_PALETTE, CGUtils::CLEAR_PREVIEW));

		if (isset($_POST['APPEARANCE_PAGE']))
			$response['cm_img'] = "/cg/v/$AppearanceID.svg?t=".time();
		else $response['notes'] = Appearances::getNotesHTML($Database->where('id', $AppearanceID)->getOne('appearances'),  NOWRAP);

		$logdata = array();
		if ($adding) Logs::logAction('cgs',array(
			'action' => 'add',
			'groupid' => $Group['groupid'],
			'ponyid' => $AppearanceID,
			'label' => $data['label'],
			'order' => $data['order'] ?? null,
		));
		else if ($data['label'] !== $Group['label']){
			$logdata['oldlabel'] = $Group['label'];
			$logdata['newlabel'] = $data['label'];
		}

		$origColors = ColorGroups::stringifyColors($origColors);
		$recvColors = ColorGroups::stringifyColors($recvColors);
		$colorsChanged = $origColors !== $recvColors;
		if ($colorsChanged){
			$logdata['oldcolors'] = $origColors;
			$logdata['newcolors'] = $recvColors;
		}
		if (!empty($logdata)){
			$logdata['groupid'] = $Group['groupid'];
			$logdata['ponyid'] = $AppearanceID;
			Logs::logAction('cg_modify', $logdata);
		}

		Response::done($response);
	}
}
