<?php

namespace App;

use ActiveRecord\ConnectionManager;
use ActiveRecord\DateTime;
use ActiveRecord\SQLBuilder;
use App\Exceptions\CURLRequestException;
use App\Models\Cacheable;
use App\Models\Event;
use App\Models\Notice;
use App\Models\Show;
use App\Models\UsefulLink;
use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use DOMDocument;
use DOMElement;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use ElephantIO\Engine\SocketIO\Version2X as SocketIOEngine;
use enshrined\svgSanitize\data\AllowedAttributes;
use enshrined\svgSanitize\data\AttributeInterface;
use enshrined\svgSanitize\data\TagInterface;
use enshrined\svgSanitize\Sanitizer;
use Exception;
use HTMLPurifier;
use HTMLPurifier_Config;
use HTMLPurifier_TagTransform_Simple;
use Monolog\Logger;
use Parsedown;
use RuntimeException;
use TypeError;
use WhichBrowser\Parser;
use function count;
use function dirname;
use function gettype;
use function in_array;
use function intval;
use function is_array;
use function is_int;
use function is_object;
use function is_string;
use function OpenApi\scan;

class CoreUtils {
  private static Inflector $inflector;

  /**
   * Contains the HTML of the navigation element
   *
   * @var string
   */
  public static $NavHTML;

  public const FIXPATH_EMPTY = [
    '§' => true,
    '#' => true,
  ];

  /** Used in Twig */
  public const LOGO_PATH_MAP = [
    'pony' => '/img/logos/pony.svg',
    'eqg' => '/img/logos/eqg.svg',
    'pl' => '/img/logos/pl.svg',
  ];

  /**
   * Forces an URL rewrite to the specified path
   *
   * @param string     $fix_uri       URL to forcibly redirect to
   * @param null|array $remove_params URL parameters to remove
   */
  public static function fixPath(string $fix_uri, ?array $remove_params = null):void {
    $split = explode('?', $_SERVER['REQUEST_URI'], 2);
    $path = urldecode($split[0]);
    $query = empty($split[1]) ? '' : "?{$split[1]}";

    $fix_split = explode('?', strtok($fix_uri, '#'), 2);
    $fix_path = $fix_split[0];
    $fix_query = self::mergeQuery($query, empty($fix_split[1]) ? '' : "?{$fix_split[1]}", $remove_params);
    $fragment = self::appendFragment($fix_uri);

    if ($path !== $fix_path || $query !== $fix_query)
      HTTP::tempRedirect("$fix_path$fix_query$fragment");
  }

  public static function mergeQuery(string $query, string $fix_query, ?array $remove_params = null):string {
    if (empty($fix_query))
      return $query;

    $to_remove = $remove_params !== null ? array_flip($remove_params) : [];
    $query_assoc = self::queryStringAssoc($query);
    $fix_query_assoc = self::queryStringAssoc($fix_query);
    $merged = $query_assoc;
    foreach ($fix_query_assoc as $key => $item)
      $merged[$key] = $item;
    $fix_query_arr = [];
    foreach ($merged as $key => $item){
      if ($item === null || isset($to_remove[$key]) || (is_string($item) && isset(self::FIXPATH_EMPTY[$item])))
        continue;

      $fix_query_arr[] = rtrim(urlencode($key).'='.urlencode($item), '=');
    }
    $fix_query = empty($fix_query_arr) ? '' : '?'.implode('&', $fix_query_arr);

    return $fix_query;
  }

  public static function appendFragment($fix_query):string {
    if (!self::contains($fix_query, '#'))
      return "";

    strtok($fix_query, '#');
    $fragment = strtok('#');

    return !empty($fragment) ? "#$fragment" : "";
  }

  /**
   * Turn query string into an associative array
   *
   * @param string $query
   *
   * @return array
   */
  public static function queryStringAssoc($query) {
    $assoc = [];
    if (!empty($query))
      parse_str(ltrim($query, '?'), $assoc);

    return $assoc;
  }

  /**
   * Apostrophe HTML encoding for attribute values
   *
   * @param string $str Input string
   *
   * @return string Encoded string
   */
  public static function aposEncode(?string $str):string {
    return self::escapeHTML($str, ENT_QUOTES);
  }

  public static function escapeHTML(?string $html, $mask = null) {
    $mask = $mask !== null ? $mask | ENT_HTML5 : ENT_HTML5;

    return htmlspecialchars($html, $mask);
  }

  /**
   * Display a 400 page
   */
  public static function badRequest() {
    HTTP::statusCode(400);

    if (self::isJSONExpected())
      Response::fail('HTTP 400: Bad Request (e.g. invalid characters in the URL)');

    Users::authenticate();
    self::checkNutshell();

    self::loadPage('ErrorController::badReq', [
      'title' => '400',
    ]);
  }

  /**
   * Display a 403 page
   */
  public static function noPerm() {
    HTTP::statusCode(403);

    if (self::isJSONExpected())
      Response::fail("HTTP 403: You don't have permission to access {$_SERVER['REQUEST_URI']}");

    Users::authenticate();
    self::checkNutshell();

    self::loadPage('ErrorController::noPerm', [
      'title' => '403',
    ]);
  }

  /**
   * Display a 404 page
   */
  public static function notFound() {
    HTTP::statusCode(404);

    if (self::isJSONExpected())
      Response::fail("HTTP 404: Endpoint ({$_SERVER['REQUEST_URI']}) does not exist");

    Users::authenticate();
    self::checkNutshell();

    self::loadPage('ErrorController::notFound', [
      'title' => '404',
    ]);
  }

  /**
   * Display a 405 page
   */
  public static function notAllowed() {
    HTTP::statusCode(405);

    if (self::isJSONExpected())
      Response::fail("HTTP 405: The endpoint {$_SERVER['REQUEST_URI']} does not support the {$_SERVER['REQUEST_METHOD']} method");

    Users::authenticate();
    self::checkNutshell();

    self::loadPage('ErrorController::notAllowed', [
      'title' => '405',
    ]);
  }

  public const DEFAULT_CSS = ['theme'];
  public const DEFAULT_JS = [
    'datastore',
    'lib/jquery.swipe',
    'lib/jquery.simplemarquee',
    'lib/codemirror-modes/colorguide',
    'shared-utils',
    'dialog',
    'global',
    'react-components',
    'websocket',
  ];
  public const DEFAULT_LIBS = [
    'polyfill-io',
    'dragscroll',
    'jquery',
    'moment',
    'react',
    'codemirror',
    'ba-throttle-debounce',
    'fluidbox',
    'inert',
    'typicons',
  ];

  /**
   * Page loading function
   * ---------------------
   *
   * @param string        $method_name Name of the method calling this function (typically the __METHOD__ constant)
   * @param array         $options     {
   *
   * @throws RuntimeException
   * @see View::processName for expected format when specifying it yourself
   * @var string          $title       Page title
   * @var bool            $noindex     Discourage crawlers (that respect meta tags)
   * @var bool            $default     -css  Set to false to disable loading of default CSS files
   * @var bool            $default     -js   Set to false to disable loading of default JS files
   * @var string[]|bool[] $css         Specify an array of CSS files to load (true = autodetect)
   * @var string[]|bool[] $js          Specify an array of JS files to load (true = autodetect)
   * @var string          $url         A URL which will replace the one sent to the browser (using JS)
   * @var mixed[]         $import      An array containing key-value pairs to pass to the view as local variables
   * @var string[]        $og          OpenGraph data replacement to override defaults
   * @var string          $canonical   If specified, provides the supplied URL as the canonical URL in a meta tag
   *      }
   */
  public static function loadPage(string $method_name, array $options = []) {
    if (self::isJSONExpected()){
      HTTP::statusCode(400);
      self::logError(__METHOD__.": JSON expected, but this was called instead.\nView: $method_name\nOptions:\n".var_export($options, true)."\nStacktrace:\n".(new Exception())->getTraceAsString());
      $path = self::escapeHTML($_SERVER['REQUEST_URI']);
      Response::fail("The requested endpoint ($path) does not support JSON responses");
    }

    // Clear any stray DB parameters before rendering
    DB::$instance->reset();

    // Variables
    $scope = $options['import'] ?? [];
    $fatal_error_page = isset($scope['fatal_error_page']);
    $scope['ws_server_host'] = self::env('WS_SERVER_HOST');
    $minimal = !empty($options['minimal']);

    // Add auth data
    $scope = array_merge($scope, Auth::to_array());

    // Resolve view
    $view = new View($method_name);

    // Disable crawling
    $scope['robots'] = !isset($options['noindex']) || $options['noindex'] === false;

    // Assets
    $scope['default_css'] = !isset($options['default-css']) || $options['default-css'] === true;
    $scope['default_js'] = !isset($options['default-js']) || $options['default-js'] === true;
    $scope['css'] = $scope['default_css'] ? self::DEFAULT_CSS : [];
    $scope['js'] = $scope['default_js'] ? self::DEFAULT_JS : [];
    self::_checkAssets($options, $scope['css'], 'css', $view);
    self::_checkAssets($options, $scope['js'], 'js', $view);
    $scope['is_2020_event'] = self::$useNutshellNames;
    if (self::$useNutshellNames) {
      $scope['css'][] = 'https://fonts.googleapis.com/css2?family=Comic+Neue:wght@400;700&display=swap';
    }

    // Libs
    LibHelper::process($scope, $options, self::DEFAULT_LIBS);

    // OpenGraph values
    $scope['og'] = self::processOpenGraph($options, [
      'url' => ABSPATH.ltrim($_SERVER['REQUEST_URI'], '/'),
      'title' => SITE_TITLE,
      'description' => 'Handling requests, reservations & the Color Guide since 2015',
      'image' => '/img/logo.png',
    ]);

    // Page <title>
    if (isset($options['title'])){
      $scope['title'] = $options['title'];
      $scope['og']['title'] = "{$scope['title']} - {$scope['og']['title']}";
    }

    if (!$minimal){
      // View values
      $scope['view_class'] = $view->class;
      $scope['view_name'] = $view->name;

      // Page heading
      if (isset($options['heading']))
        $scope['heading'] = $options['heading'];

      // Canonical URLs
      if (isset($options['canonical']))
        $scope['canonical_url'] = $options['canonical'];

      // Git info
      $scope['git_info'] = self::getFooterGitInfo();

      if (!$fatal_error_page){
        // Notifications & useful links
        if (Auth::$signed_in){
          $notifs = Notifications::get(Notifications::UNREAD_ONLY);
          $scope['have_notifs'] = count($notifs) > 0;
          $scope['notifications'] = Notifications::getHTML($notifs);
          $scope['useful_links'] = UsefulLink::in_order();
        }

        // Happening soon
        $scope['happening_soon'] = self::getSidebarUpcoming();
      }

      $scope['breadcrumbs'] = self::getBreadcrumbsHTML($fatal_error_page, $scope, $view ?? null);
      $scope['site_notices'] = Notice::list();
    }

    Twig::display($view, $scope);
  }

  public static function processOpenGraph(array $options, array $defaults = []):array {
    if (!empty($options['og'])){
      foreach ($options['og'] as $k => $v){
        if ($v !== null)
          $defaults[$k] = $v;
      }
    }
    self::toAbsoluteUrl($defaults['image']);
    self::toAbsoluteUrl($defaults['url']);

    return $defaults;
  }

  public static function toAbsoluteUrl(string &$url) {
    if (preg_match('~^/([^/].*)?$~', $url))
      $url = ABSPATH.ltrim($url, '/');
  }

  /**
   * Render upcoming episode HTML
   *
   * @param bool $wrap Whether to output the wrapper elements
   *
   * @return string
   */
  public static function getSidebarUpcoming($wrap = WRAP) {
    $HTML = [];
    /** @var $UpcomingEpisodes Show[] */
    $UpcomingEpisodes = Show::find('all', ['conditions' => "airs > NOW() AND airs < NOW() + INTERVAL '6 MONTH'", 'order' => 'airs asc']);
    $i = 0;
    if (!empty($UpcomingEpisodes)){
      foreach ($UpcomingEpisodes as $i => $Episode){
        $airtime = strtotime($Episode->airs);
        $month = date('M', $airtime);
        $day = date('j', $airtime);
        $time = self::_eventTimeTag($airtime, $i);

        $title = $Episode->is_episode
          ? $Episode->title
          : (
          Regexes::$ep_title_prefix->match($Episode->title)
            ? ShowHelper::shortenTitlePrefix($Episode->title)
            : self::capitalize($Episode->type).': '.$Episode->title
          );

        $type = $Episode->is_episode ? 'episode' : 'movie';
        $HTML[] = [
          $airtime, "<li><div class='calendar'><span class='top $type'>$month</span><span class='bottom'>$day</span></div>".
          "<div class='meta'><span class='title'><a href='{$Episode->toURL()}'>$title</a></span><span class='time'>Airs $time</span></div></li>",
        ];
      }
    }
    else $i = 0;

    $upcoming_events = Event::upcoming();
    if (!empty($upcoming_events)){
      foreach ($upcoming_events as $j => $event){
        $time = strtotime($event->starts_at);
        $before_start_date = time() < $time;
        if (!$before_start_date){
          $time = strtotime($event->ends_at);
        }
        $month = date('M', $time);
        $day = date('j', $time);
        $Verbs = $before_start_date ? 'Starts' : 'Ends';
        $time_tag = self::_eventTimeTag($time, $i + $j);

        $HTML[] = [
          $time, "<li><div class='calendar'><span class='top event'>$month</span><span class='bottom'>$day</span></div>".
          "<div class='meta'><span class='title'><a href='{$event->toURL()}'>{$event->name}</a></span><span class='time'>$Verbs $time_tag</span></div></li>",
        ];
      }
    }
    if (empty($HTML))
      return '';
    usort($HTML, function ($a, $b) {
      return $a[0] <=> $b[0];
    });
    foreach ($HTML as &$v)
      $v = $v[1];
    unset($v);
    $HTML = implode('', $HTML);

    return $wrap ? "<section id='upcoming'><h2>Happening soon</h2><ul>$HTML</ul></section>" : $HTML;
  }

  private static function _eventTimeTag(int $timestamp, int $index):string {
    if ($index === 0){
      $diff = Time::difference(time(), $timestamp);
      if ($diff['time'] < Time::IN_SECONDS['month']){
        $ret = 'in ';
        $tz = '('.date('T', $timestamp).')';
        if (!empty($diff['day'])){
          $ret .= "{$diff['day']} day".($diff['day'] !== 1 ? 's' : '').' & ';
        }
        if (!empty($diff['hour'])){
          $ret .= "{$diff['hour']}:";
        }
        foreach (['minute', 'second'] as $k){
          $diff[$k] = self::pad($diff[$k]);
        }
        $timec = date('c', $timestamp);

        return "<time datetime='$timec' class='dynt nodt'>$ret{$diff['minute']}:{$diff['second']} $tz</time>";
      }
    }

    return Time::tag($timestamp);
  }

  /**
   * Checks assets from loadPage()
   *
   * @param array    $options    Options array
   * @param string[] $customType Array of partial file names
   * @param string   $ext        The literal strings 'css' or 'js'
   * @param View     $view       The view class that enables the true shortcut
   *
   * @throws Exception
   */
  private static function _checkAssets(array $options, &$customType, string $ext, View $view) {
    if (isset($options[$ext])){
      if (!is_array($options[$ext]))
        throw new RuntimeException("\$options[$ext] must be an array");
      $customType = array_merge($customType, $options[$ext]);
    }

    foreach ($customType as $i => &$item){
      if ($item === true)
        $item = "pages/{$view->name}";
      self::_formatFilePath($item, $ext, "min.$ext");
    }
  }

  public static function cachedAssetLink(string $fname, string $relpath, string $type):string {
    self::_formatFilePath($fname, $relpath, $type);

    return $fname;
  }

  /**
   * Turns asset filenames into URLs & adds modification timestamp parameters
   *
   * @param string $item
   * @param string $relpath
   * @param string $type
   *
   * @return string
   */
  private static function _formatFilePath(string &$item, string $relpath, string $type) {
    $pathStart = APPATH.$relpath;
    $item .= ".$type";
    if (!file_exists("$pathStart/$item"))
      throw new RuntimeException("File /$relpath/$item does not exist");
    $item = "/$relpath/$item?".filemtime("$pathStart/$item");
  }

  /**
   * A wrapper around php's native str_pad with more fitting defaults
   *
   * @param mixed  $input
   * @param int    $pad_length
   * @param string $pad_string
   * @param int    $pad_type
   *
   * @return string
   */
  public static function pad($input, $pad_length = 2, $pad_string = '0', $pad_type = STR_PAD_LEFT) {
    return str_pad((string)$input, $pad_length, $pad_string, $pad_type);
  }

  /**
   * Capitalizes the first leter of a string
   *
   * @param string $str
   * @param bool   $all
   *
   * @return string
   */
  public static function capitalize($str, $all = false) {
    if (!isset(self::$inflector))
      self::$inflector = InflectorFactory::create()->build();

    $str = strtolower($str);

    if (!$all){
      $first_word = strtok($str, ' ');
      $rest = substr($str, strlen($first_word));

      return self::$inflector->capitalize($first_word) . $rest;
    }

    return self::$inflector->capitalize($str);
  }

  public static function rangeLimit(int $value, int $min, int $max) {
    return min($max, max($min, $value));
  }

  // Turns a file size ini setting value into bytes
  private static function _shortSizeInBytes($size) {
    $unit = mb_substr($size, -1);
    $value = (int)mb_substr($size, 0, -1);
    switch (strtoupper($unit)){
      case 'G':
        $value *= 1024;
      case 'M':
        $value *= 1024;
      case 'K':
        $value *= 1024;
      break;
    }

    return $value;
  }

  /**
   * Returns the maximum uploadable file size in a readable format
   *
   * @param array $sizes For use in tests
   *
   * @return string
   */
  public static function getMaxUploadSize($sizes = null):string {
    if ($sizes === null)
      $sizes = [ini_get('post_max_size'), ini_get('upload_max_filesize')];

    $workWith = $sizes[0];
    if ($sizes[1] !== $sizes[0]){
      $sizesBytes = array_map('self::_shortSizeInBytes', $sizes);
      if ($sizesBytes[1] < $sizesBytes[0])
        $workWith = $sizes[1];
    }

    return preg_replace('/^(\d+)([GMk])$/i', '$1 $2B', strtoupper($workWith));
  }

  /**
   * Export PHP variables to JS through a script tag
   *
   * @param array $export Associative aray where keys are the desired JS variable names
   *
   * @return string
   * @throws Exception
   */
  public static function exportVars(array $export):string {
    if (empty($export))
      return '';
    foreach ($export as $name => $value){
      if ($value instanceof RegExp)
        $export[$name] = $value->jsExport();
    }

    return '<aside class="datastore">'.self::escapeHTML(JSON::encode($export))."</aside>\n";
  }

  /**
   * Sanitizes HTML that comes from user input
   *
   * @param string   $dirty_html        HTML coming from the user
   * @param string[] $allowedTags       Additional allowed tags
   * @param string[] $allowedAttributes Allowed tag attributes
   *
   * @return string Sanitized HTML code
   */
  public static function sanitizeHtml(string $dirty_html, ?array $allowedTags = null, ?array $allowedAttributes = null) {
    $config = HTMLPurifier_Config::createDefault();
    $whitelist = ['strong', 'b', 'em', 'i'];
    if (!empty($allowedTags))
      $whitelist = array_merge($whitelist, $allowedTags);
    $config->set('HTML.AllowedElements', $whitelist);
    $config->set('HTML.AllowedAttributes', $allowedAttributes);
    $config->set('Core.EscapeInvalidTags', true);

    // Mapping old to new
    $def = $config->getHTMLDefinition();
    if ($def === null)
      throw new RuntimeException(__METHOD__.': $def should never be null');
    $def->info_tag_transform['b'] = new HTMLPurifier_TagTransform_Simple('strong');
    $def->info_tag_transform['i'] = new HTMLPurifier_TagTransform_Simple('em');

    $purifier = new HTMLPurifier($config);

    return self::trim($purifier->purify($dirty_html), true);
  }

  public static function minifySvgData(string $svgdata) {
    if (!file_exists(SVGO_BINARY) || !is_executable(SVGO_BINARY))
      throw new RuntimeException('svgo is required for SVG processing, please run `npm i` to install Node.js dependencies');
    $tmp_path = FSPATH.'tmp/sanitize/'.self::sha256($svgdata).'.svg';
    self::createFoldersFor($tmp_path);
    File::put($tmp_path, $svgdata);

    exec(SVGO_BINARY." $tmp_path ".
      '--disable=removeUnknownsAndDefaults,removeUselessStrokeAndFill,convertPathData,convertTransform,cleanupNumericValues,mergePaths,convertShapeToPath '.
      '--enable=removeRasterImages,removeDimensions,cleanupIDs');
    $read_file = File::get($tmp_path);
    if ($read_file === false)
      throw new RuntimeException(__METHOD__.": Failed to read file $tmp_path");
    /** @var string $read_file */
    $svgdata = $read_file;
    self::deleteFile($tmp_path);

    return $svgdata;
  }

  /**
   * Sanitizes SVG that comes from user input
   *
   * @param string $dirty_svg SVG data coming from the user
   * @param bool   $minify
   * @param array  $warnings
   *
   * @return string Sanitized SVG code
   */
  public static function sanitizeSvg(string $dirty_svg, bool $minify = true, ?array &$warnings = null) {
    // Remove bogous HTML entities
    $dirty_svg = preg_replace('/&ns_[a-z_]+;/', '', $dirty_svg);
    if ($minify)
      $dirty_svg = self::minifySvgData($dirty_svg);

    $sanitizer = new Sanitizer();
    $sanitizer->setAllowedTags(new class implements TagInterface {
      public static function getTags() {
        return [
          'svg', 'circle', 'clippath', 'clipPath', 'defs', 'ellipse', 'filter', 'font', 'g', 'line',
          'lineargradient', 'marker', 'mask', 'mpath', 'path', 'pattern', 'style',
          'polygon', 'polyline', 'radialgradient', 'rect', 'stop', 'switch', 'use', 'view',

          'feblend', 'fecolormatrix', 'fecomponenttransfer', 'fecomposite',
          'feconvolvematrix', 'fediffuselighting', 'fedisplacementmap',
          'feflood', 'fefunca', 'fefuncb', 'fefuncg', 'fefuncr', 'fegaussianblur',
          'femerge', 'femergenode', 'femorphology', 'feoffset',
          'fespecularlighting', 'fetile', 'feturbulence',
        ];
      }
    });
    $sanitizer->setAllowedAttrs(new class implements AttributeInterface {
      public static function getAttributes() {
        /** @var $allowed array */
        $allowed = array_flip(AllowedAttributes::getAttributes());
        unset($allowed['color']);

        return array_keys($allowed);
      }
    });
    $sanitizer->removeRemoteReferences(true);
    $sanitized = $sanitizer->sanitize($dirty_svg);

    $unifier = new DOMDocument('1.0', 'UTF-8');
    $unifier->loadXML($sanitized);
    if ($warnings !== null){
      $all_tags = $unifier->getElementsByTagName('*');
      $transform_attr = false;
      foreach ($all_tags as $tag){
        /** @var $tag DOMElement */
        if (empty($tag->getAttribute('transform')))
          continue;

        $transform_attr = true;
        break;
      }
      if ($transform_attr){
        $warnings[] = 'File contains one or more transform attributes (extremely likely to cause incorrect rendering in Illustrator)';
      }
    }
    // Make sure we add the default colors of paths to the file to make them replaceable (unless they have a class)
    $paths = $unifier->getElementsByTagName('path');
    foreach ($paths as $path){
      /** @var $path DOMElement */
      $fill_attr = $path->getAttribute('fill');
      $class_attr = $path->getAttribute('class');
      if ($fill_attr === null && $class_attr === null)
        $path->setAttribute('fill', '#000');
    }
    // Fix 1-stop linear gradients that would otherwise break in Illustrator
    $linear_gradients = $unifier->getElementsByTagName('linearGradient');
    if ($warnings !== null){
      $single_stop_warnings = [];
    }
    foreach ($linear_gradients as $grad){
      /** @var $grad DOMElement */
      if ($grad->childNodes->length !== 1)
        continue;

      /** @var $original_stop_node DOMElement */
      $original_stop_node = $grad->childNodes->item(0);
      $original_stop_color = $original_stop_node->getAttribute('stop-color');

      /** @var $stop_node DOMElement */
      $stop_node = $original_stop_node->cloneNode();
      $stop_node->setAttribute('offset', 1 - $stop_node->getAttribute('offset'));
      $stop_node->setAttribute('stop-color', $original_stop_color);
      $grad->appendChild($stop_node);

      if ($warnings !== null && !isset($single_stop_warnings[$original_stop_color])){
        $single_stop_warnings[$original_stop_color] = "Single-stop linear gradient found with color $original_stop_color which will break in Illustrator (this has been remedied by duplicating the color stop, but fixing the source file would be ideal)";
      }
    }
    if (!empty($single_stop_warnings)){
      $warnings = array_merge($warnings, array_values($single_stop_warnings));
    }

    return $unifier->saveXML($unifier->documentElement, LIBXML_NOEMPTYTAG);
  }

  public static function validateSvg(string $svg_data) {
    self::conditionalUncompress($svg_data);
    if ($svg_data === false)
      return Input::ERROR_INVALID;

    $parser = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $parser->loadXML(self::sanitizeSvg($svg_data));
    libxml_use_internal_errors();
    if ($parser->documentElement === null || strtolower($parser->documentElement->nodeName) !== 'svg' || count($parser->documentElement->childNodes) === 0)
      return Input::ERROR_INVALID;
    unset($parser);

    return Input::ERROR_NONE;
  }

  /**
   * Analyzes a file path and creates the folder structure necessary to sucessfully store it
   *
   * @param string $path Path to analyze
   *
   * @return bool Whether the folder was sucessfully created
   */
  public static function createFoldersFor(string $path):bool {
    $folder = dirname($path);

    return !is_dir($folder) ? mkdir($folder, FOLDER_PERM, true) : true;
  }

  /**
   * Formats a 1-dimensional array of stings naturally
   *
   * @param string|string[] $list
   * @param string          $append
   * @param string          $separator
   * @param bool            $noescape Set to true to prevent character escaping
   *
   * @return string
   */
  public static function arrayToNaturalString(array $list, string $append = 'and', string $separator = ',', $noescape = false):string {
    if (is_string($list))
      $list = explode($separator, $list);

    if (count($list) > 1){
      $list_str = $list;
      array_splice($list_str, count($list_str) - 1, 0, $append);
      $i = 0;
      $maxDest = count($list_str) - 3;
      while ($i < $maxDest){
        if ($i === count($list_str) - 1)
          continue;
        $list_str[$i] .= ',';
        $i++;
      }
      $list_str = implode(' ', $list_str);
    }
    else $list_str = $list[0];
    if (!$noescape)
      $list_str = self::escapeHTML($list_str);

    return $list_str;
  }

  /**
   * Checks validity of a string based on regex
   *  and responds if invalid chars are found
   *
   * @param string $string      The value bein checked
   * @param string $Thing       Human-readable name for $string
   * @param string $pattern     An inverse pattern that matches INVALID characters
   * @param bool   $returnError If true retursn the error message instead of responding
   *
   * @return null|string
   */
  public static function checkStringValidity($string, $Thing, $pattern = INVERSE_PRINTABLE_ASCII_PATTERN, $returnError = false) {
    if (preg_match_all(new RegExp($pattern, 'u'), $string, $fails)){
      /** @var $fails string[][] */
      $invalid = [];
      foreach ($fails[0] as $f)
        if (!in_array($f, $invalid, true)){
          switch ($f){
            case "\n":
              $invalid[] = '\n';
            break;
            case "\r":
              $invalid[] = '\r';
            break;
            case "\t":
              $invalid[] = '\t';
            break;
            default:
              $invalid[] = $f;
          }
        }

      $count = count($invalid);
      $s = $count !== 1 ? 's' : '';
      $the_following = $count !== 1 ? 'the following' : 'an';
      $Error = "$Thing (".self::escapeHTML($string).") contains $the_following invalid character$s: ".self::arrayToNaturalString($invalid);
      if ($returnError)
        return $Error;
      Response::fail($Error);
    }
  }

  /**
   * Returns the HTML raw GIT information
   *
   * @return array
   */
  public static function getFooterGitInfoRaw():array {
    $commit_info = RedisHelper::get('commit_info');
    if ($commit_info === null || !self::env('PRODUCTION')){
      $commit_info = rtrim(shell_exec('git log -1 --date=short --pretty="format:%h;%ci"'));
      RedisHelper::set('commit_info', $commit_info);
    }

    $data = [];
    if (!empty($commit_info)){
      [$commit_id, $commit_time] = explode(';', $commit_info);
      $data['commit_id'] = $commit_id;
      $data['commit_time'] = $commit_time;
    }

    return $data;
  }

  /**
   * Returns the HTML of the GIT information in the website's footer
   *
   * @param bool $reload_warning
   *
   * @return string
   */
  public static function getFooterGitInfo(bool $reload_warning = false):string {
    $data = self::getFooterGitInfoRaw();
    $data['reload_warning'] = $reload_warning;

    return Twig::$env->render('layout/_footer_git_info.html.twig', $data);
  }

  /**
   * Returns the HTML code of the main navigation in the header
   *
   * @param bool $disabled
   *
   * @return string
   * @deprecated Use Twig view
   */
  public static function getNavigationHTML($disabled = false) {
    if (!empty(self::$NavHTML))
      return self::$NavHTML;

    // Navigation items
    if (!$disabled){
      $NavItems = [
        ['/episode/latest', 'Latest episode'],
        ['/show', 'Show'],
        ['/cg', 'Color Guide'],
        ['/events', 'Events'],
      ];
      if (Auth::$signed_in)
        $NavItems[] = [Auth::$user->toURL(false), 'Account'];
      if (Permission::sufficient('staff')){
        $NavItems[] = ['/users', 'Users'];
        $NavItems[] = ['/admin', 'Admin'];
      }
      $NavItems[] = ['/about', 'About'];
    }
    else $NavItems = [];

    self::$NavHTML = '';
    foreach ($NavItems as $item)
      self::$NavHTML .= "<li><a href='{$item[0]}'>{$item[1]}</a></li>";
    self::$NavHTML .= '<li><a href="https://www.deviantart.com/mlp-vectorclub" target="_blank" rel="noopener">MLP-VectorClub</a></li>';

    return self::$NavHTML;
  }

  /**
   * Returns the HTML code of the secondary breadcrumbs navigation
   *
   * @param bool  $disabled
   * @param array $scope Contains the variables passed to the current page
   * @param View  $view  Contains the view object that the current page was resolved by
   *
   * @return string
   */
  public static function getBreadcrumbsHTML($disabled = false, array $scope = [], ?View $view = null):string {
    // Navigation items
    if (!$disabled){
      if ($view === null)
        return '';

      try {
        $breadcrumb = $view->getBreadcrumb($scope) ?? '';
      }
      catch (TypeError $e){
        $breadcrumb = '';
      }
    }
    else $breadcrumb = (new NavBreadcrumb('HTTP 503'))->setChild(new NavBreadcrumb('Service Temporarily Unavailable'));

    return (string)$breadcrumb;
  }

  /**
   * Adds possessive 's at the end of a word
   *
   * @param string $w
   * @param bool   $sOnly
   *
   * @return string
   */
  public static function posess($w, bool $sOnly = false) {
    $s = "'".(mb_substr($w, -1) !== 's' ? 's' : '');
    if ($sOnly)
      return $s;

    return $w.$s;
  }

  /**
   * Appends 's' to the end of string if input is not 1
   *
   * @param string $word    Text to pluralize
   * @param float  $count   Number to base pluralization off of
   * @param bool   $prepend Prepend number to text
   *
   * @return string
   */
  public static function makePlural($word, float $count = 0, $prepend = false):string {
    if (!isset(self::$inflector))
      self::$inflector = InflectorFactory::create()->build();

    if ((int)$count !== 1) {
      $word = self::$inflector->pluralize($word);
    }

    if ($prepend)
      return "$count $word";

    return $word;
  }

  /**
   * @param string $word
   *
   * @return string
   */
  public static function makeSingular(string $word):string {
    if (!isset(self::$inflector))
      self::$inflector = InflectorFactory::create()->build();

    return self::$inflector->singularize($word);
  }

  /**
   * Detect user's web browser based on user agent
   *
   * @param string|null $user_agent User-Agent string to check
   *
   * @return array
   */
  public static function detectBrowser($user_agent = null) {
    $return = ['user_agent' => !empty($user_agent) ? $user_agent : ($_SERVER['HTTP_USER_AGENT'] ?? '')];
    $result = new Parser($return['user_agent']);
    if (!empty($result->browser->name)){
      $return['browser_name'] = $result->browser->name;

      if (!empty($result->browser->version))
        $return['browser_ver'] = $result->browser->version->value;
    }
    $return['platform'] = $result->os->toString();

    return $return;
  }

  // Converts a browser name to it's equivalent class name
  public static function browserNameToClass($BrowserName) {
    return preg_replace('/[^a-z]/', '', strtolower($BrowserName));
  }

  /**
   * Trims a string while truncating consecutive spaces
   *
   * @param string $str
   * @param string $chars
   * @param bool   $multiline
   *
   * @return string
   */
  public static function trim(string $str, bool $multiline = false, string $chars = " \t\n\r\0\x0B\xC2\xA0"):string {
    $out = preg_replace('/ +/', ' ', trim($str, $chars));
    if ($multiline)
      $out = preg_replace('/(\r\n|\r)/', "\n", $out);

    return $out;
  }

  /**
   * Averages the numbers inside an array
   *
   * @param int[] $numbers
   *
   * @return float
   */
  public static function average(array $numbers):float {
    return array_sum($numbers) / count($numbers);
  }

  /**
   * Checks if a deviation is in the club
   *
   * @param int|string $DeviationID
   *
   * @return bool|int
   */
  public static function isDeviationInClub($DeviationID) {
    if (!is_int($DeviationID))
      $DeviationID = intval(mb_substr($DeviationID, 1), 36);

    try {
      $difi_request = HTTP::legitimateRequest("https://www.deviantart.com/global/difi/?c[]=\"DeviationView\",\"getAllGroups\",[\"$DeviationID\"]&t=json");
    }
    catch (CURLRequestException $e){
      return $e->getCode();
    }
    if (empty($difi_request['response']))
      return 1;

    $difi_request = JSON::decode($difi_request['response'], JSON::AS_OBJECT);
    if (empty($difi_request->DiFi->status))
      return 2;
    if ($difi_request->DiFi->status !== 'SUCCESS')
      return 3;
    if (empty($difi_request->DiFi->response->calls))
      return 4;
    if (empty($difi_request->DiFi->response->calls[0]))
      return 5;
    if (empty($difi_request->DiFi->response->calls[0]->response))
      return 6;
    if (empty($difi_request->DiFi->response->calls[0]->response->status))
      return 7;
    if ($difi_request->DiFi->response->calls[0]->response->status !== 'SUCCESS')
      return 8;
    if (empty($difi_request->DiFi->response->calls[0]->response->content->html))
      return 9;

    $html = $difi_request->DiFi->response->calls[0]->response->content->html;

    return self::contains($html, 'gmi-groupname="MLP-VectorClub">');
  }

  /**
   * Checks if a deviation is in the club and stops execution if it isn't
   *
   * @param string $favme
   * @param bool   $throw If true an Exception will be thrown instead of responding
   */
  public static function checkDeviationInClub($favme, $throw = false) {
    $Status = self::isDeviationInClub($favme);
    if ($Status !== true){
      $errmsg = (
      $Status === false
        ? 'The deviation has not been submitted to/accepted by the group yet'
        : "There was an issue while checking the acceptance status (Error code: $Status)"
      );
      if ($throw)
        throw new RuntimeException($errmsg);
      Response::fail($errmsg);
    }
  }

  /**
   * Cut a string to the specified length
   *
   * @param string $str
   * @param int    $len
   *
   * @return string
   */
  public static function cutoff(string $str, $len):string {
    $strlen = mb_strlen($str);

    return $strlen > $len ? self::trim(mb_substr($str, 0, $len - 1)).'…' : $str;
  }

  public static function socketEvent(string $event, array $data = [], string $origin = ORIGIN) {
    $elephant = new \ElephantIO\Client(new SocketIOEngine("https://".self::env('WS_SERVER_HOST'), [
      'context' => [
        'http' => [
          'header' => [
            'Cookie: access='.urlencode(self::env('WS_SERVER_KEY')),
            "Origin: $origin",
          ],
        ],
        'ssl' => ['verify_peer' => self::env('SOCKET_SSL_VERIFY_PEER')],
      ],
    ]));

    $elephant->initialize();
    $elephant->emit($event, $data);
    $elephant->close();
  }

  public const VECTOR_APPS = [
    '' => "(don't show)",
    'illustrator' => 'Adobe Illustrator',
    'inkscape' => 'Inkscape',
    'ponyscape' => 'Ponyscape',
  ];

  /**
   * Universal method for setting keys/properties of arrays/objects
   *
   * @param mixed  $on
   * @param string $key
   * @param mixed  $value
   */
  public static function set(&$on, $key, $value) {
    if (is_object($on))
      $on->{$key} = $value;
    else if (is_array($on))
      $on[$key] = $value;
    else throw new RuntimeException('$on is of invalid type ('.gettype($on).')');
  }

  /**
   * Checks if an image exists on the web
   * Specify raw HTTP codes as integers to $onlyFail to only report failure on those codes
   *
   * @param string $url
   * @param array  $only_fails
   * @param int    $response_code
   *
   * @return bool
   */
  public static function isURLAvailable(string $url, array $only_fails = [], &$response_code = null):bool {
    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $url,
      CURLOPT_NOBODY => 1,
      CURLOPT_FAILONERROR => 1,
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTPGET => true,
    ]);
    $available = curl_exec($ch) !== false;
    $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($available === false && !empty($only_fails))
      $available = !in_array($response_code, $only_fails, false);
    curl_close($ch);

    return $available;
  }

  public static function msleep(int $ms) {
    usleep($ms * 1000);
  }

  public static function sha256(string $data):string {
    return hash('sha256', $data);
  }

  public static function makeUrlSafe(string $string):string {
    return self::trim(preg_replace('/-+/', '-', preg_replace(new RegExp('[^A-Za-z\d\-]'), '-', $string)), false, '-');
  }

  /**
   * @param string $table_name
   *
   * @return SQLBuilder
   */
  public static function sqlBuilder(string $table_name) {
    $conn = ConnectionManager::get_connection();

    return new SQLBuilder($conn, $table_name);
  }

  public static function execSqlBuilderArgs(SQLBuilder $builder):array {
    return [$builder->to_s(), $builder->bind_values()];
  }

  public static function elasticClient():Client {
    /** @var $elastiClient Client */
    static $elastiClient;
    if ($elastiClient !== null)
      return $elastiClient;

    $elastiClient = ClientBuilder::create()->setHosts(['127.0.0.1:9200'])->build();

    return $elastiClient;
  }

  public static function isJSONExpected():bool {
    // "Cache" the result for this request
    static $return_value;
    if ($return_value !== null)
      return $return_value;

    if (empty($_SERVER['HTTP_ACCEPT']))
      $return_value = false;
    else {
      $htmlpos = stripos($_SERVER['HTTP_ACCEPT'], 'text/html');
      $jsonpos = stripos($_SERVER['HTTP_ACCEPT'], 'application/json');

      $return_value = $jsonpos !== false && ($htmlpos === false ? true : $jsonpos < $htmlpos);
    }

    return $return_value;
  }

  public static function detectUnexpectedJSON() {
    if (!self::isJSONExpected()){
      HTTP::statusCode(400);
      header('Content-Type: text/plain');
      die("This endpoint only serves JSON requests which your client isn't accepting");
    }
  }

  public static function gzread(string $path):string {
    $data = '';
    $file = gzopen($path, 'rb');
    while (!gzeof($file)){
      $data .= gzread($file, 4096);
    }
    gzclose($file);

    return $data;
  }

  public const USELESS_NODE_NAMES = [
    '#text' => true,
    'br' => true,
  ];

  public static function closestMeaningfulPreviousSibling(DOMElement $e) {
    do {
      $e = $e->previousSibling;
    } while ($e !== null && empty(self::trim($e->textContent)));

    return $e;
  }

  private static function _downloadHeaders(string $filename) {
    header('Content-Type: application/octet-stream');
    header('Content-Transfer-Encoding: Binary');
    header("Content-disposition: attachment; filename=\"$filename\"");
  }

  public static function downloadAsFile(string $data, string $name) {
    self::_downloadHeaders($name);
    echo $data;
    exit;
  }

  public static function downloadFile(string $path, ?string $dl_name = null) {
    self::_downloadHeaders($dl_name ?? basename($path));
    readfile($path);
    exit;
  }

  /**
   * Set theory equivalent: $initial ∖ $remove
   * Only works with non-associative arrays
   * I wouldn't rely on the order of the returned elements
   *
   * @param array $initial
   * @param array $remove
   *
   * @return array The inital array with the elements present in both arrays removed
   */
  public static function array_subtract(array $initial, array $remove):array {
    $initial = array_flip($initial);
    if ($initial === false)
      throw new RuntimeException(__METHOD__.': $initial could not be flipped');
    /** @var $initial array */
    $remove = array_flip($remove);
    if ($remove === false)
      throw new RuntimeException(__METHOD__.': $remove could not be flipped');
    /** @var $remove array */
    foreach ($initial as $el => $_){
      if (isset($remove[$el]))
        unset($initial[$el]);
    }

    return array_keys($initial);
  }

  public static function array_random(array $arr) {
    if (empty($arr))
      return null;

    if (count($arr) === 1)
      return $arr[0];

    return $arr[array_rand($arr, 1)];
  }

  /**
   * Returns the file's modification timestamp or the current timestamp if it doesn't exist
   *
   * @param string $path
   *
   * @return int
   */
  public static function filemtime(string $path):int {
    if (!file_exists($path))
      return time();

    $mt = filemtime($path);

    return $mt === false ? time() : $mt;
  }

  /**
   * Deletes a file if it exists, stays silent otherwise
   *
   * @param string $name
   *
   * @return bool
   */
  public static function deleteFile(string $name):bool {
    if (!file_exists($name))
      return true;

    return unlink($name);
  }

  public static function conditionalUncompress(string &$data) {
    if (0 === mb_strpos($data, "\x1f\x8b\x08", 0, 'US-ASCII')) {
      $decoded_data = @gzdecode($data);
      if (is_string($decoded_data))
        $data = $decoded_data;
    }
  }

  public static function stringSize(string $data):int {
    return mb_strlen($data, '8bit');
  }

  public static function logError(string $message, int $severity = Logger::ERROR) {
    global $logger;

    /** @var $logger Logger */
    $logger->log($severity, $message);
  }

  public static function responseSmiley(string $face):string {
    return "<div class='align-center'><span class='sideways-smiley-face'>$face</span></div>";
  }

  public static function isURLSafe(string $url, &$matches = null):bool {
    return mb_strlen($url) <= 256 && Regexes::$rewrite->match(strtok($url, '?'), $matches);
  }

  public static function getSidebarLoggedIn(bool $wrap = WRAP):string {
    $data = Auth::to_array();
    $data['wrap'] = $wrap;

    return Twig::$env->render('layout/_sidebar_logged_in.html.twig', $data);
  }

  public static function callScript(string $name, array $args = [], &$output = null) {
    $arguments = array_map('escapeshellarg', [PROJPATH."scripts/$name.php", ...$args]);

    return exec(sprintf('nohup /usr/bin/php -f %s >> "%s" 2>&1 &', implode(' ', $arguments), FULL_LOG_PATH), $output);
  }

  public static function parseMarkdown(string $text):string {
    return Parsedown::instance()->setUrlsLinked(false)->setBreaksEnabled(true)->setMarkupEscaped(true)->text($text);
  }

  /**
   * @param Notice[] $notices
   * @param bool     $wrap
   *
   * @return string
   */
  public static function getNoticeListHTML(array $notices, bool $wrap = WRAP):string {
    return Twig::$env->render('admin/_notice_list.html.twig', [
      'notices' => $notices,
      'wrap' => $wrap,
    ]);
  }

  public static function startsWith(string $haystack, string $needle):bool {
    return 0 === mb_strpos($haystack, $needle);
  }

  public static function endsWith(string $haystack, string $needle):bool {
    $length = mb_strlen($needle);

    return $length === 0 || (mb_substr($haystack, -$length) === $needle);
  }

  public static function contains(string $haystack, string $needle, bool $case_sensitive = true):bool {
    $pos = $case_sensitive ? mb_strpos($haystack, $needle) : mb_stripos($haystack, $needle);

    return $pos !== false;
  }

  /**
   * Get the difference between an AR DateTime object and the current time in seconds
   *
   * @param DateTime|null $ts
   * @param int|null      $now
   *
   * @return int|null
   */
  public static function tsDiff(?DateTime $ts, ?int $now = null):?int {
    if ($ts === null)
      return null;

    return ($now ?? time()) - $ts->getTimestamp();
  }

  /**
   * @param Cacheable[] $cacheables
   *
   * @return bool
   */
  public static function cacheExpired($cacheables) {
    foreach ($cacheables as $cacheable){
      if (!($cacheable instanceof Cacheable))
        throw new RuntimeException('The following value does not implement '.Cacheable::class.":\n".var_export($cacheable, true));

      if ($cacheable->cacheExpired())
        return true;
    }

    return false;
  }

  public static function env(string $variable) {
    $value = $_ENV[$variable] ?? null;
    switch ($value){
      case 'true':
        return true;
      case 'false':
        return false;
      default:
        return $value;
    }
  }

  /**
   * Generate pagination data for API requests
   *
   * @param Pagination $pagination
   *
   * @return array
   */
  public static function paginationForApi(Pagination $pagination):array {
    return [
      'currentPage' => $pagination->getPage(),
      'totalPages' => $pagination->getMaxPages(),
      'totalItems' => $pagination->getEntryCount(),
      'itemsPerPage' => $pagination->getItemsPerPage(),
    ];
  }

  public static function generateFileHash(string $file_path):string {
    $hash = md5_file($file_path);
    if ($hash === false)
      throw new RuntimeException("Failed to get MD5 hash for file {$file_path}");

    return $hash;
  }

  public static function bin2dataUri(string $binary_str, string $content_type):string {
    return "data:$content_type;base64,".base64_encode($binary_str);
  }

  /**
   * @param bool $only_if_missing Generates the file only if it doesn't exist
   *
   * @return void
   */
  public static function generateApiSchema($only_if_missing = false):void {
    $output_path = APPATH.API_SCHEMA_PATH;
    if ($only_if_missing && file_exists($output_path))
      return;
    $openapi = scan(PROJPATH.'app/Controllers/API');
    if (!$openapi->validate())
      throw new RuntimeException("Invalid OpenAPI schema, could not generate $output_path");
    self::createFoldersFor($output_path);
    $openapi->saveAs($output_path, 'json');
  }

  public static function generateCacheKey(int $version, ...$args) {
    $args[] = "v$version";

    return implode('_', array_map(function ($arg) {
      switch (gettype($arg)){
        case 'boolean':
          return $arg ? 't' : 'f';
        case 'double':
        case 'float':
        case 'integer':
          return (string)$arg;
        case 'NULL':
          return 'null';
        case 'string':
          return str_replace(' ', '_', $arg);
      }
    }, $args));
  }

  public const CSP_HEADER_NAMES = [
    'Content-Security-Policy',
    'X-Content-Security-Policy',
    'X-WebKit-CSP',
  ];

  public static function outputCSPHeaders():void {
    if (!self::env('CSP_ENABLED'))
      return;

    $csp_header = implode(';', [
      'default-src '.self::env('CSP_DEFAULT_SRC'),
      'script-src '.self::env('CSP_SCRIPT_SRC').' '.self::env('WS_SERVER_HOST')." 'nonce-".CSP_NONCE."'",
      'object-src '.self::env('CSP_OBJECT_SRC'),
      'style-src '.self::env('CSP_STYLE_SRC'),
      'img-src '.self::env('CSP_IMG_SRC'),
      'manifest-src '.self::env('CSP_MANIFEST_SRC'),
      'media-src '.self::env('CSP_MEDIA_SRC'),
      'frame-src '.self::env('CSP_FRAME_SRC'),
      'font-src '.self::env('CSP_FONT_SRC'),
      'connect-src '.self::env('CSP_CONNECT_SRC').' '.self::env('WS_SERVER_HOST').' wss://'.self::env('WS_SERVER_HOST'),
      'frame-ancestors '.self::env('CSP_FRAME_ANCESTORS'),
      'form-action '.self::env('CSP_FRAME_ANCESTORS'),
      'base-uri '.self::env('CSP_BASE_URI'),
    ]);

    $report_uri = self::env('CSP_REPORT_URI');
    if (!empty($report_uri)) {
      $csp_header .= ";report-uri $report_uri";
    }

    foreach (self::CSP_HEADER_NAMES as $header_name){
      header("$header_name: $csp_header");
    }
  }

  public static function removeCSPHeaders():void {
    if (!self::env('CSP_ENABLED'))
      return;

    foreach (self::CSP_HEADER_NAMES as $header_name){
      header_remove($header_name);
    }
  }

  public static bool $useNutshellNames;

  public static function checkNutshell() {
    self::$useNutshellNames = UserPrefs::get('cg_nutshell') === '1';
    $names_path = CONFPATH.'nutshell_names.php';
    if (self::$useNutshellNames) {
      if (!file_exists($names_path))
        throw new RuntimeException('Nutshell name mapping file missing');

      /** @noinspection PhpIncludeInspection */
      require_once $names_path;
    }
  }

  /**
   * Swap adjacent letters in a string a specific number of times
   *
   * @param string $str
   * @param int    $times
   *
   * @return string
   */
  public static function swapLetters(string $str, int $times):string {
    if (mb_strlen($str) < 2)
      return $str;

    $indices = [];
    $str_array = mb_str_split($str);
    foreach ($str_array as $i => $letter){
      if (preg_match('~^[a-z]$~i', $letter))
        $indices[] = $i;
    }

    $indices_count = count($indices);
    for ($i = 0; $i < $times; $i++){
      $key1 = array_rand($indices);
      if ($key1 === $indices_count - 1)
        [$key1, $key2] = [$key1-1, $key1];
      else $key2 = $key1 + 1;
      [$index1, $index2] = [$indices[$key1], $indices[$key2]];
      [$str_array[$index2], $str_array[$index1]] = [$str_array[$index1], $str_array[$index2]];
    }

    return implode('', $str_array);
  }
}
