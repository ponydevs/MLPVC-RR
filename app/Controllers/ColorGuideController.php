<?php

namespace App\Controllers;

use App\Appearances;
use App\Auth;
use App\CGUtils;
use App\CoreUtils;
use App\DB;
use App\HTTP;
use App\Input;
use App\Models\Appearance;
use App\Models\MajorChange;
use App\Models\DeviantartUser;
use App\Models\PinnedAppearance;
use App\Models\User;
use App\Pagination;
use App\Permission;
use App\Regexes;
use App\Response;
use App\Time;
use App\UserPrefs;
use League\Uri\Components\Query;
use League\Uri\Uri;
use League\Uri\UriModifier;

class ColorGuideController extends Controller {
  /** @var bool */
  protected bool $appearance_page = false;
  /** @var string|null Guide identifier or null for personal color guides */
  protected ?string $guide = 'pony';

  protected ?User $owner = null;
  protected bool $is_owner = false;

  public function __construct() {
    parent::__construct();

    $this->appearance_page = isset($_REQUEST['APPEARANCE_PAGE']);
    if (isset($_REQUEST['owner_id']))
      $this->guide = null;
  }

  protected function _initialize($params):void {
    if (!empty($params['guide']) && isset(CGUtils::GUIDE_MAP[$params['guide']])) {
      $this->guide = $params['guide'];
    }
    $user_id_set = isset($params['user_id']);

    if ($user_id_set){
      $this->owner = User::find($params['user_id']);
      if (empty($this->owner))
        CoreUtils::notFound();
      $this->guide = null;
    }
    $this->is_owner = $user_id_set ? (Auth::$signed_in && Auth::$user->id === $this->owner->id) : false;

    if ($user_id_set)
      $this->path = "{$this->owner->toURL()}/cg";
    else $this->path = rtrim("/cg/{$this->guide}", '/');
  }

  protected ?Appearance $appearance;

  public function load_appearance($params, bool $set_properties = true):void {
    if (!isset($params['id']))
      Response::fail('Missing appearance ID');
    $this->appearance = Appearance::find($params['id']);
    if (empty($this->appearance))
      CoreUtils::notFound();
    if (!$set_properties)
      return;

    if ($this->appearance->owner_id !== null) {
      $this->guide = null;
      $this->owner = $this->appearance->owner;
    }
    if ($this->guide === null){
      $owner_path = $this->appearance->owner->toURL();
      $this->path = "$owner_path/cg";
      $this->is_owner = Auth::$signed_in && ($this->appearance->owner_id === Auth::$user->id);
    }
    else if ($this->guide !== $this->appearance->guide){
      $this->guide = $this->appearance->guide;
      $this->path = '/cg/eqg';
    }
  }

  protected const GUIDE_MANAGE_JS = [
    'jquery.uploadzone',
    'pages/colorguide/tag-list',
    'pages/colorguide/manage',
  ];
  protected const GUIDE_MANAGE_CSS = [
    'pages/colorguide/manage',
  ];
  protected const GUIDE_MANAGE_LIBS = [
    'autocomplete',
    'sortable',
    'blob',
    'canvas-to-blob',
    'file-saver',
  ];

  protected static function _appendManageAssets(&$settings):void {
    $settings['js'] = array_merge($settings['js'] ?? [], self::GUIDE_MANAGE_JS);
    $settings['css'] = array_merge($settings['css'] ?? [], self::GUIDE_MANAGE_CSS);
    $settings['libs'] = isset($settings['libs']) ? array_merge($settings['libs'], self::GUIDE_MANAGE_LIBS) : self::GUIDE_MANAGE_LIBS;
  }

  public const FULL_LIST_ORDER = [
    'label' => 'alphabetically',
    'relevance' => 'by relevance',
    'added' => 'by date added',
  ];

  public function preferredGuide() {
    CGUtils::redirectToPreferredGuidePath();
  }

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
        DB::$instance->orderBy('created_at', 'DESC');
      break;
    }
    $appearances = Appearances::get($this->guide, null, null, 'id,label,private');

    $path = Uri::createFromString("{$this->path}/full");
    if ($sort_by !== 'relevance')
      $path = UriModifier::appendQuery($path, Query::createFromParams(['sort_by'=>$sort_by]));

    if (CoreUtils::isJSONExpected())
      Response::done([
        'html' => CGUtils::getFullListHTML($appearances, $sort_by, $this->guide, NOWRAP),
        'stateUrl' => (string)$path,
      ]);

    CoreUtils::fixPath($path);

    $is_staff = Permission::sufficient('staff');

    $libs = [];
    if ($is_staff)
      $libs[] = 'sortable';

    $import = [
      'guide' => $this->guide,
      'appearances' => $appearances,
      'sort_by' => $sort_by,
      'is_staff' => $is_staff,
      'full_list' => CGUtils::getFullListHTML($appearances, $sort_by, $this->guide),
    ];
    if ($is_staff){
      $import['max_upload_size'] = CoreUtils::getMaxUploadSize();
      $import['hex_color_pattern'] = Regexes::$hex_color;
    }
    CoreUtils::loadPage(__METHOD__, [
      'title' => 'Full List - '.CGUtils::GUIDE_MAP[$this->guide].' Color Guide',
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

    Appearances::reorder((new Input('list', 'int[]', [
      Input::CUSTOM_ERROR_MESSAGES => [
        Input::ERROR_MISSING => 'The list of IDs is missing',
        Input::ERROR_INVALID => 'The list of IDs is not formatted properly',
      ],
    ]))->out());

    $ordering = (new Input('ordering', 'string', [
      Input::IS_OPTIONAL => true,
    ]))->out();

    Response::done(['html' => CGUtils::getFullListHTML(Appearances::get($this->guide), $ordering, $this->guide, NOWRAP)]);
  }

  public function changeList($params):void {
    $this->_initialize($params);
    $pagination = new Pagination("{$this->path}/changes", 9, MajorChange::total($this->guide));

    CoreUtils::fixPath($pagination->toURI());
    $heading = 'Major '.CGUtils::GUIDE_MAP[$this->guide].' Color Changes';
    $title = "Page {$pagination->getPage()} - $heading - Color Guide";

    $changes = MajorChange::get(null, $this->guide, $pagination->getLimitString());

    CoreUtils::loadPage(__METHOD__, [
      'title' => $title,
      'heading' => $heading,
      'css' => [true],
      'js' => ['paginate'],
      'import' => [
        'guide' => $this->guide,
        'changes' => $changes,
        'pagination' => $pagination,
      ],
    ]);
  }

  public function index():void {
    $title = 'Color Guide List';
    $subheading = 'List of all color guides maintained by the club staff';
    $guide_counts_raw = DB::$instance->query('SELECT guide, count(guide) as count FROM appearances GROUP BY guide');
    $guide_counts = [];
    foreach ($guide_counts_raw as $item)
      $guide_counts[$item['guide']] = $item['count'];

    $json_export_url = CoreUtils::cachedAssetLink('mlpvc-colorguide', 'dist', 'json');
    $json_export_time = Time::tag((int)explode('?', $json_export_url)[1]);
    $settings = [
      'title' => $title,
      'heading' => $title,
      'css' => [true],
      'og' => [
        'title' => $title,
        'description' => $subheading,
      ],
      'import' => [
        'guides' => CGUtils::GUIDE_MAP,
        'guide_counts' => $guide_counts,
        'subheading' => $subheading,
        'json_export_url' => $json_export_url,
        'json_export_time' => $json_export_time,
      ],
    ];
    if (Permission::sufficient('staff')){
      self::_appendManageAssets($settings);
    }
    CoreUtils::loadPage(__METHOD__, $settings);
  }

  public function guide($params):void {
    $this->_initialize($params);

    $title = '';
    /** @var $appearances_per_page int */
    $appearances_per_page = UserPrefs::get('cg_itemsperpage');
    $elastic_avail = CGUtils::isElasticAvailable();
    $searching = !empty($_GET['q']) && CoreUtils::trim($_GET['q']) !== '';
    $json_response = CoreUtils::isJSONExpected();
    if ($elastic_avail){
      $pagination = new Pagination($this->path, $appearances_per_page);
      [$appearances, $search_query] = CGUtils::searchGuide($pagination, $this->guide, $searching, $title);
    }
    else {
      if ($searching && $json_response)
        Response::fail('The ElasticSearch server is currently down and search is not available, sorry for the inconvenience.<br>Please <a class="send-feedback">let us know</a> about this issue.', ['unavail' => true]);

      $search_query = null;
      $entry_count = DB::$instance->where('guide', $this->guide)->where('id != 0')->count('appearances');

      $pagination = new Pagination($this->path, $appearances_per_page, $entry_count);
      $appearances = Appearances::get($this->guide, $pagination->getLimit());
    }

    if (isset($_REQUEST['btnl'])){
      $found = !empty($appearances[0]->id);
      if (CoreUtils::isJSONExpected()){
        if (!$found)
          Response::fail('Your search returned no results.');
        Response::done(['goto' => $appearances[0]->toURL()]);
      }
      if ($found)
        HTTP::tempRedirect($appearances[0]->toURL());
    }

    $path = $pagination->toURI();
    $remove_params = null;
    if (!empty($search_query))
      $path = UriModifier::appendQuery($path, Query::createFromParams(['q' => $search_query]));
    else $remove_params = ['q'];
    CoreUtils::fixPath($path, $remove_params);
    $heading = CGUtils::GUIDE_MAP[$this->guide].' Color Guide';
    $title .= "Page {$pagination->getPage()} - $heading";

    if (!file_exists(CGUtils::GUIDE_EXPORT_PATH))
      CGUtils::saveExportData();

    $settings = [
      'title' => $title,
      'heading' => $heading,
      'noindex' => $searching,
      'css' => [true],
      'js' => ['jquery.ctxmenu', true, 'paginate'],
      'libs' => ['autocomplete'],
      'import' => [
        'guide' => $this->guide,
        'appearances' => $appearances,
        'pagination' => $pagination,
        'elastic_avail' => $elastic_avail,
        'pinned_appearances' => PinnedAppearance::getGuideAppearances($this->guide),
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

    CoreUtils::downloadAsFile(CGUtils::getExportData(), 'mlpvc-colorguide.json');
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

    $hex_pattern = preg_replace('~^/(.*)/.*$~', '$1', Regexes::$hex_color->jsExport());
    $dasprid = DeviantartUser::find_by_name('dasprid');
    $dasprid_link = empty($dasprid)
      ? "<a href='https://www.deviantart.com/dasprid'>dasprid</a>"
      : $dasprid->user->toAnchor(WITH_AVATAR);
    CoreUtils::loadPage(__METHOD__, [
      'title' => 'Color Blending Calculator',
      'css' => [true],
      'js' => [true],
      'import' => [
        'hex_pattern' => $hex_pattern,
        'nav_blending' => true,
        'dasprid_link' => $dasprid_link,
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

    $nag_da_user = DeviantartUser::find(Appearances::SPRITE_NAG_USER_ID);
    $The_authorities = Appearances::SPRITE_NAG_USER_ID === Auth::$user->id ? 'You' : $nag_da_user->user->toAnchor();
    Response::success('Checkup started.'.($nag_da_user !== null ? " $The_authorities will be notified if there are any issues." : ''));
  }
}
