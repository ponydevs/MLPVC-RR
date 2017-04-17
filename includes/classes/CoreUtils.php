<?php

namespace App;

use App\Controllers\Controller;
use App\Models\User;
use App\View;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use ElephantIO\Engine\SocketIO\Version1X as SocketIOEngineVersion1X;
use App\Exceptions\CURLRequestException;

class CoreUtils {
	const
		FIXPATH_EMPTY = '#',
		FIXPATH_PERM = 301,
		FIXPATH_TEMP = 302;
	/**
	 * Forces an URL rewrite to the specified path
	 *
	 * @param string $fix_uri  URL to forcibly redirect to
	 * @param int    $http HTPP status code for the redirect
	 */
	static function fixPath(string $fix_uri, int $http = self::FIXPATH_TEMP){
		$_split = explode('?', $_SERVER['REQUEST_URI'], 2);
		$path = $_split[0];
		$query = empty($_split[1]) ? '' : "?{$_split[1]}";

		$_split = explode('?', $fix_uri, 2);
		$fix_path = $_split[0];
		$fix_query = empty($_split[1]) ? '' : "?{$_split[1]}";

		if (empty($fix_query))
			$fix_query = $query;
		else {
			$query_assoc = self::queryStringAssoc($query);
			$fix_query_assoc = self::queryStringAssoc($fix_query);
			$merged = $query_assoc;
			foreach ($fix_query_assoc as $key => $item)
				$merged[$key] = $item;
			$fix_query_arr = array();
			foreach ($merged as $key => $item){
				if (!isset($item) || $item !== self::FIXPATH_EMPTY)
					$fix_query_arr[] = $key.(!empty($item)?'='.urlencode($item):'');
			}
			$fix_query = empty($fix_query_arr) ? '' : '?'.implode('&', $fix_query_arr);
		}
		if ($path !== $fix_path || $query !== $fix_query)
			HTTP::redirect("$fix_path$fix_query", $http);
	}

	/**
	 * Turn query string into an associative array
	 *
	 * @param string $query
	 *
	 * @return array
	 */
	static function queryStringAssoc($query){
		$assoc = array();
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
	static function aposEncode(?string $str):string {
		return self::escapeHTML($str, ENT_QUOTES);
	}

	static function escapeHTML(?string $html, $mask = null){
		$mask = isset($mask) ? $mask | ENT_HTML5 : ENT_HTML5;
		return htmlspecialchars($html, $mask, 'UTF-8');
	}

	// Possible notice types
	static $NOTICE_TYPES = array('info','success','fail','warn','caution');
	/**
	 * Renders the markup of an HTML notice
	 *
	 * @param string      $type   Notice type
	 * @param string      $title  If $text is specified: Notice title
	 *                            If $text is null: Notice body
	 * @param string|null $text   Notice body
	 *                            If there's no title, leave empty and use $title for body
	 * @param bool        $center Whether to center the contents of the notice
	 *
	 * @return string
	 */
	static function notice($type, $title, $text = null, $center = false){
		if (!in_array($type, self::$NOTICE_TYPES))
			throw new \Exception("Invalid notice type $type");

		if (!is_string($text)){
			if (is_bool($text))
				$center = $text;
			$text = $title;
			unset($title);
		}

		$HTML = '';
		if (!empty($title))
			$HTML .= '<label>'.self::escapeHTML($title).'</label>';

		$textRows = preg_split("/(\r\n|\n|\r){2}/", $text);
		foreach ($textRows as $row)
			$HTML .= '<p>'.self::trim($row).'</p>';

		if ($center)
			$type .= ' align-center';
		return "<div class='notice $type'>$HTML</div>";
	}

	/**
	 * Display a 404 page
	 */
	static function notFound(){
		if (POST_REQUEST || isset($_GET['via-js'])){
			$RQURI = rtrim(str_replace('via-js=true','',$_SERVER['REQUEST_URI']),'?&');
			Response::fail("HTTP 404: ".(POST_REQUEST?'Endpoint':'Page')." ($RQURI) does not exist");
		}

		Users::authenticate();

		HTTP::statusCode(404);
		global $do;
		$do = '404';
		self::loadPage(array(
			'title' => '404',
			'view' => '404',
		));
	}

	/**
	 * Page loading function
	 * ---------------------
	 * $options = array(
	 *     'title' => string,     - Page title
	 *     'no-robots',           - Disable crawlers (that respect meta tags)
	 *     'no-default-css',      - Disable loading of default CSS files
	 *     'no-default-js'        - Disable loading of default JS files
	 *     'css' => string|array, - Specify a single/multiple CSS files to load
	 *     'js' => string|array,  - Specify a single/multiple JS files to load
	 *     'view' => string,      - Which view file to open (defaults to $do)
	 *     'do-css',              - Load the CSS file whose name matches $do
	 *     'do-js',               - Load the JS file whose name matches $do
	 *     'url' => string,       - A URL which will replace the one sent to the browser
	 * );
	 *
	 * @param array                  $options
	 * @param Controllers\Controller $controller
	 */
	static function loadPage($options, Controllers\Controller $controller = null){
		// Page <title>
		if (isset($options['title']))
			$GLOBALS['title'] = $options['title'];

		// Page heading
		if (isset($options['heading']))
			$GLOBALS['heading'] = $options['heading'];

		// SE crawling disable
		if (in_array('no-robots', $options))
			$norobots = true;

		// Set new URL option
		if (!empty($options['url']))
			$redirectto = $options['url'];

		# CSS
		$DEFAULT_CSS = array('theme');
		$customCSS = array();
		// Only add defaults when needed
		if (array_search('no-default-css', $options) === false)
			$customCSS = array_merge($customCSS, $DEFAULT_CSS);

		# JavaScript
		$DEFAULT_JS = array('moment','jquery.ba-throttle-debounce','global','dialog','dragscroll');
		$customJS = array();
		// Only add defaults when needed
		if (array_search('no-default-js', $options) === false)
			$customJS = array_merge($customJS, $DEFAULT_JS);

		# Check assests
		self::_checkAssets($options, $customCSS, 'scss/min', 'css', $controller);
		self::_checkAssets($options, $customJS, 'js/min', 'js', $controller);

		# Import variables
		if (isset($options['import']) && is_array($options['import'])){
			$scope = $options['import'];
			foreach ($scope as $k => $v)
				if (!isset($$k))
					$$k = $v;
		}
		else $scope = [];
		foreach ($GLOBALS as $k => $v)
			if (!isset($$k))
				$$k = $v;

		# Putting it together
		if (empty($options['view']) && !isset($controller->do))
			throw new \Exception('View cannot be resolved. Specify the <code>view</code> option or provide the controller as a parameter');
		$view = new View(empty($options['view']) ? $controller->do : $options['view']);

		header('Content-Type: text/html; charset=utf-8;');

		if (empty($_GET['via-js'])){
			ob_start();
			require INCPATH.'views/_layout.php';
			$content = ob_get_clean();
			echo self::_clearIndentation($content);
			die();
		}
		else {
			$_SERVER['REQUEST_URI'] = rtrim(str_replace('via-js=true','',CSRFProtection::removeParamFromURL($_SERVER['REQUEST_URI'])), '?&');
			ob_start();
			require INCPATH.'views/sidebar.php';
			$sidebar = ob_get_clean();
			ob_start();
			require $view;
			$content = ob_get_clean();
			Response::done(array(
				'css' => $customCSS,
				'js' => $customJS,
				'title' => (isset($GLOBALS['title'])?$GLOBALS['title'].' - ':'').SITE_TITLE,
				'content' => self::_clearIndentation($content),
				'sidebar' => self::_clearIndentation($sidebar),
				'footer' => CoreUtils::getFooter(WITH_GIT_INFO),
				'avatar' => $GLOBALS['signedIn'] ? $GLOBALS['currentUser']->avatar_url : GUEST_AVATAR,
				'responseURL' => $_SERVER['REQUEST_URI'],
				'signedIn' => $GLOBALS['signedIn'],
			));
		}
	}

	/**
	 * Removes excess tabs from HTML code
	 *
	 * @param string $HTML
	 *
	 * @return string
	 */
	private static function _clearIndentation($HTML){
		return preg_replace(new RegExp('(\n|\r|\r\n)[\t ]*'), '$1', $HTML);
	}

	/**
	 * Checks assets from loadPage()
	 *
	 * @param array                  $options    Options array
	 * @param string[]               $customType Array of partial file names
	 * @param string                 $relpath    File path relative to /
	 * @param string                 $ext        The literal strings 'css' or 'js'
	 * @param Controllers\Controller $controller
	 *
	 * @throws \Exception
	 */
	private static function _checkAssets($options, &$customType, $relpath, $ext, $controller = null){
		if (isset($options[$ext])){
			$$ext = $options[$ext];
			if (!is_array($$ext))
				$customType[] = $$ext;
			else $customType = array_merge($customType, $$ext);
		}
		if (array_search("do-$ext", $options) !== false){
			if (!isset($controller))
				throw new \Exception("do-$ext used without explicitly passing the controller to ".__METHOD__);
			else if (!isset($controller->do))
				throw new \Exception("Controller passed to ".__METHOD__." lacks \$do property");
			$customType[] = $controller->do;
		}

		foreach ($customType as $i => &$item)
			self::_formatFilePath($item, $relpath, $ext);
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
	private static function _formatFilePath(&$item, $relpath, $type){
		$pathStart = APPATH.$relpath;
		$item .= ".$type";
		if (!file_exists("$pathStart/$item"))
			throw new \Exception("File /$relpath/$item does not exist");
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
	static function pad($input, $pad_length = 2, $pad_string = '0', $pad_type = STR_PAD_LEFT){
		return str_pad((string) $input, $pad_length, $pad_string, $pad_type);
	}

	/**
	 * Capitalizes the first leter of a string
	 *
	 * @param string $str
	 * @param bool   $all
	 *
	 * @return string
	 */
	static function capitalize($str, $all = false){
		if ($all) return preg_replace_callback(new RegExp('((?:^|\s)[a-z])(\w+\b)?','i'), function($match){
			return strtoupper($match[1]).strtolower($match[2]);
		}, $str);
		else return self::length($str) === 1 ? strtoupper($str) : strtoupper($str[0]).CoreUtils::substring($str,1);
	}

	// Turns a file size ini setting value into bytes
	private static function _shortSizeInBytes($size){
		$unit = CoreUtils::substring($size, -1);
		$value = intval(CoreUtils::substring($size, 0, -1), 10);
		switch(strtoupper($unit)){
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
	static function getMaxUploadSize($sizes = null){
		if (!isset($sizes))
			$sizes = array(ini_get('post_max_size'), ini_get('upload_max_filesize'));

		$workWith = $sizes[0];
		if ($sizes[1] !== $sizes[0]){
			$sizesBytes = array_map('self::_shortSizeInBytes', $sizes);
			if ($sizesBytes[1] < $sizesBytes[0])
				$workWith = $sizes[1];
		}

		return preg_replace(new RegExp('^(\d+)([GMk])$','i'), '$1 $2B', strtoupper($workWith));
	}

	/**
	 * Export PHP variables to JS through a script tag
	 *
	 * @param array $export Associative aray where keys are the desired JS variable names
	 * @throws \Exception
	 *
	 * @return string
	 */
	static function exportVars(array $export):string {
		if (empty($export))
			return '';
		/** @noinspection ES6ConvertVarToLetConst */
		$HTML =  '<script>var ';
		foreach ($export as $name => $value){
			$type = gettype($value);
			switch (strtolower($type)){
				case "boolean":
					$value = $value ? 'true' : 'false';
				break;
				case "array":
					$value = JSON::encode($value);
				break;
				case "string":
					// regex test
					if (preg_match(new RegExp('^/(.*)/([a-z]*)$','u'), $value, $regex_parts))
						$value = (new RegExp($regex_parts[1],$regex_parts[2]))->jsExport();
					else $value = JSON::encode($value);
				break;
				case "integer":
				case "float":
					$value = strval($value);
				break;
				case "null":
					$value = 'null';
				break;
				default:
					if ($value instanceof RegExp){
						$value = $value->jsExport();
						break;
					}
					throw new \Exception("Exporting unsupported variable $name of type $type");
			}
			$HTML .= "$name=$value,";
		}
		return rtrim($HTML,',').'</script>';
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
	static function sanitizeHtml(string $dirty_html, ?array $allowedTags = null, ?array $allowedAttributes = null){
		$config = \HTMLPurifier_Config::createDefault();
		$whitelist = array('strong','b','em','i');
		if (!empty($allowedTags))
			$whitelist = array_merge($whitelist, $allowedTags);
		/** @noinspection PhpUndefinedMethodInspection */
		$config->set('HTML.AllowedElements', $whitelist);
		/** @noinspection PhpUndefinedMethodInspection */
		$config->set('Core.EscapeInvalidTags', true);
		$purifier = new \HTMLPurifier($config);
		/** @noinspection PhpUndefinedMethodInspection */
		return self::trim($purifier->purify($dirty_html), true);
	}

	/**
	 * Analyzes a file path and creates the filder structure necessary to sucessfully store it
	 *
	 * @param string $path Path to analyze
	 *
	 * @return bool Whether the folder was sucessfully created
	 */
	static function createUploadFolder(string $path):bool {
		$DS = RegExp::escapeBackslashes('\/');
		$folder = preg_replace(new RegExp("^(.*[$DS])[^$DS]+$"),'$1',preg_replace(new RegExp('$DS'),'\\',$path));
		return !is_dir($folder) ? mkdir($folder,0777,true) : true;
	}

	/**
	 * Formats a 1-dimensional array of stings naturally
	 *
	 * @param string[] $list
	 * @param string   $append
	 * @param string   $separator
	 * @param bool     $noescape  Set to true to prevent character escaping
	 *
	 * @return string
	 */
	static function arrayToNaturalString(array $list, string $append = 'and', string $separator = ',', $noescape = false):string {
		if (is_string($list)) $list = explode($separator, $list);

		if (count($list) > 1){
			$list_str = $list;
			array_splice($list_str,count($list_str)-1,0,$append);
			$i = 0;
			$maxDest = count($list_str)-3;
			while ($i < $maxDest){
				if ($i == count($list_str)-1)
					continue;
				$list_str[$i] = $list_str[$i].',';
				$i++;
			}
			$list_str = implode(' ',$list_str);
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
	static function checkStringValidity($string, $Thing, $pattern, $returnError = false){
		if (preg_match_all(new RegExp($pattern,'u'), $string, $fails)){
			$invalid = array();
			foreach ($fails[0] as $f)
				if (!in_array($f, $invalid)){
					switch ($f){
						case "\n":
							$invalid[] = '\n';
						case "\r":
							$invalid[] = '\r';
						case "\t":
							$invalid[] = '\t';
						default:
							$invalid[] = $f;
					}
				}

			$count = count($invalid);
			$s = $count!==1?'s':'';
			$the_following = $count!==1?'the following':'an';
			$Error = "$Thing (".self::escapeHTML($string).") contains $the_following invalid character$s: ".CoreUtils::arrayToNaturalString($invalid);
			if ($returnError)
				return $Error;
			Response::fail($Error);
		}
	}

	/**
	 * Returns text HTML of the website's footer
	 *
	 * @param bool $with_git_info
	 *
	 * @return string
	 */
	static function getFooter($with_git_info = false){
		$out = [];
		if ($with_git_info)
			$out[] = self::getFooterGitInfo(false);
		$out[] = "<a class='issues' href='".GITHUB_URL."/issues' target='_blank' rel='noopener'>Known issues</a>";
		$out[] = '<a class="send-feedback">Send feedback</a>';
		global $Database, $Database;
		$out[] = '<abbr title="Time spent rendering the page (ms)">R</abbr>'.round((microtime(true)-EXEC_START_MICRO)*1000).' <abbr title="Number of SQL queries used to fetch this page">S</abbr>'.(($Database->queryCount??0)+($Database->queryCount??0));
		return implode(' | ',$out);
	}

	/**
	 * Returns the HTML of the GIT informaiiton in the website's footer
	 *
	 * @param bool $appendSeparator
	 *
	 * @return string
	 */
	static function getFooterGitInfo(bool $appendSeparator = true):string {
		$commit_info = "Running <strong><a href='".GITHUB_URL."' title='Visit the GitHub repository'>MLPVC-RR</a>";
		$commit_id = rtrim(shell_exec('git rev-parse --short=4 HEAD'));
		if (!empty($commit_id)){
			$commit_time = Time::tag(date('c',strtotime(shell_exec('git log -1 --date=short --pretty=format:%ci'))));
			$commit_info .= "@<a href='".GITHUB_URL."/commit/$commit_id' title='See exactly what was changed and why'>$commit_id</a></strong> created $commit_time";
		}
		else $commit_info .= "</strong> (version information unavailable)";
		if ($appendSeparator)
			$commit_info .= ' | ';
		return $commit_info;
	}

	/**
	 * Returns the HTML code of the navigation in the header
	 *
	 * @param bool  $disabled
	 * @param array $scope    Contains the variables passed to the current page
	 *
	 * @return string
	 */
	static function getNavigationHTML($disabled = false, $scope = []){
		if (!empty($GLOBALS['NavHTML']))
			return $GLOBALS['NavHTML'];

		global $do;

		// Navigation items
		if (!$disabled){
			$NavItems = array(
				'latest' => array('/','Latest episode'),
				'eps' => array('/episodes','Episodes'),
			);
			if ($do === 'episodes'){
				if (isset($scope['Episodes']))
					$NavItems['eps'][1] .= " - Page {$scope['Pagination']->page}";
			}
			if (($do === 'episode' || $do === 's' || $do === 'movie') && !empty($scope['CurrentEpisode'])){
				if ($scope['CurrentEpisode']->isMovie)
					$NavItems['eps'][1] = 'Movies';
				if ($scope['CurrentEpisode']->isLatest())
					$NavItems['latest'][0] = $_SERVER['REQUEST_URI'];
				else $NavItems['eps']['subitem'] = CoreUtils::cutoff($GLOBALS['heading'],Episodes::TITLE_CUTOFF);
			}
			global $Color;
			$NavItems['colorguide'] = array("/cg".(!empty($scope['EQG'])?'/eqg':''), (!empty($scope['EQG'])?'EQG ':'')."$Color Guide");
			if ($do === 'cg'){
				if (!empty($scope['Appearance']))
					$NavItems['colorguide']['subitem'] = (isset($scope['Map'])?"Sprite {$Color}s - ":'').Appearances::processLabel(CoreUtils::escapeHTML($scope['Appearance']['label']));
				else if (isset($scope['Ponies']))
					$NavItems['colorguide'][1] .= " - Page {$scope['Pagination']->page}";
				else {
					if (preg_match(new RegExp('full$'),$GLOBALS['data'])){
						$NavItems['colorguide']['subitem'] = 'Full '.($scope['EQG']?'Character':'Pony').' List';
					}
					else {
						if (isset($scope['Tags'])) $pagePrefix = 'Tags';
						else if (isset($scope['Changes'])) $pagePrefix = "Major $Color Changes";

						$NavItems['colorguide']['subitem'] = (isset($pagePrefix) ? "$pagePrefix - " : '')."Page {$scope['Pagination']->page}";
					}
				}

			}
			$NavItems['events'] = array('/events','Events');
			if ($do === 'events'){
				if (isset($scope['Events']))
					$NavItems['events'][1] .= " - Page {$scope['Pagination']->page}";
			}
			if ($do === 'event' && isset($scope['Event']))
				$NavItems['events']['subitem'] = CoreUtils::cutoff($scope['Event']->name, 20);
			if ($GLOBALS['signedIn']){
				$NavItems['u'] = array("/@{$GLOBALS['currentUser']->name}",'Account');
				if (isset($scope['Owner']) && $scope['Owner']->id === $GLOBALS['currentUser']->id)
					$NavItems['u']['subitem'] = 'Personal Color Guide';
			}
			if ($do === 'user' || Permission::sufficient('staff')){
				$NavItems['users'] = array('/users', 'Users', Permission::sufficient('staff'));
				if (!empty($scope['User']) && empty($scope['sameUser']))
					$NavItems['users']['subitem'] = $scope['User']->name;
			}
			if (Permission::sufficient('staff')){
				$NavItems['admin'] = array('/admin', 'Admin');
				global $LogItems;
				if (isset($LogItems)){
					global $Pagination;
					$NavItems['admin']['subitem'] = "Logs - Page {$Pagination->page}";
				}
				if (isset($scope['nav_dsc']))
					$NavItems['admin']['subitem'] = $GLOBALS['heading'];
			}
			$NavItems[] = array('/about', 'About');
		}
		else $NavItems = array(array(true, 'HTTP 503', false, 'subitem' => 'Service Temporarily Unavailable'));

		$GLOBALS['NavHTML'] = '';
		foreach ($NavItems as $item){
			$sublink = '';
			if (isset($item['subitem'])){
				list($class, $sublink) = self::_processHeaderLink(array(true, $item['subitem']));
				$sublink = " &rsaquo; $sublink";
				$link = self::_processHeaderLink($item, HTML_ONLY);
			}
			else if (isset($item[2]) && !$item[2])
				continue;
			else list($class, $link) = self::_processHeaderLink($item);
			$GLOBALS['NavHTML'] .= "<li$class>$link$sublink</li>";
		}
		$GLOBALS['NavHTML'] .= '<li><a href="http://mlp-vectorclub.deviantart.com/" target="_blank" rel="noopener">MLP-VectorClub</a></li>';
		return $GLOBALS['NavHTML'];
	}

	/**
	 * Header link HTML generator
	 *
	 * @param string[] $item     A header navigation item
	 * @param bool     $htmlOnly Return just the HTML
	 *
	 * @return array|string
	 */
	private static function _processHeaderLink($item, $htmlOnly = false){
		global $currentSet;

		list($path, $label) = $item;
		$RQURI = strtok($_SERVER['REQUEST_URI'], '?');
		$current = (!$currentSet || $htmlOnly === HTML_ONLY) && ($path === true || preg_match(new RegExp("^$path($|/)"), $RQURI));
		$class = '';
		if ($current){
			$currentSet = true;
			$class = " class='active'";
		}

		$perm = isset($item[2]) ? $item[2] : true;

		if ($perm){
			$href = $current && $htmlOnly !== HTML_ONLY ? '' : " href='$path'";
			$html = "<a$href>$label</a>";
		}
		else $html = "<span>$label</span>";

		return $htmlOnly === HTML_ONLY ? $html : array($class, $html);
	}

	/**
	 * Renders the "Useful links" section of the sidebar
	 */
	static function renderSidebarUsefulLinks(){
		global $Database, $signedIn;
		if (!$signedIn) return;
		$Links = $Database->orderBy('"order"','ASC')->get('usefullinks');

		$Render = array();
		foreach ($Links as $l){
			if (Permission::insufficient($l['minrole']))
				continue;

			if (!empty($l['title'])){
				$title = str_replace("'",'&apos;',$l['title']);
				$title = "title='$title'";
			}
			else $title = '';

			$href = $l['url'][0] === '#' ? "class='action--".CoreUtils::substring($l['url'],1)."'" : "href='".self::aposEncode($l['url'])."'";

			$Render[] =  "<li id='s-ufl-{$l['id']}'><a $href $title>{$l['label']}</a></li>";
		}
		if (!empty($Render))
			echo '<ul class="links">'.implode('',$Render).'</ul>';
	}

	/**
	 * Renders the "Useful links" section of the sidebar
	 *
	 * @param bool $wrap
	 *
	 * @return string
	 */
	static function getSidebarUsefulLinksListHTML($wrap = true){
		global $Database;
		$HTML = $wrap ? '<ol>' : '';
		$UsefulLinks = $Database->orderBy('"order"','ASC')->get('usefullinks');
		foreach ($UsefulLinks as $l){
			$href = "href='".CoreUtils::aposEncode($l['url'])."'";
			if ($l['url'][0] === '#')
				$href .= " class='action--".CoreUtils::substring($l['url'],1)."'";
			$title = CoreUtils::aposEncode($l['title']);
			$label = htmlspecialchars_decode($l['label']);
			$cansee = Permission::ROLES_ASSOC[$l['minrole']];
			if ($l['minrole'] !== 'developer')
				$cansee = self::makePlural($cansee, 0).' and above';
			$HTML .= "<li id='ufl-{$l['id']}'><div><a $href title='$title'>{$label}</a></div>".
			             "<div><span class='typcn typcn-eye'></span> $cansee</div>".
			             "<div class='buttons'><button class='blue typcn typcn-pencil edit-link'>Edit</button><button class='red typcn typcn-trash delete-link'>Delete</button></div></li>";
		}
		return $HTML.($wrap?'</ol>':'');
	}

	/**
	 * Adds possessive ’s at the end of a word
	 *
	 * @param string $w
	 * @param bool   $sOnly
	 *
	 * @return string
	 */
	static function posess($w, bool $sOnly = false){
		$s = "’".(CoreUtils::substring($w, -1) !== 's'?'s':'');
		if ($sOnly)
			return $s;
		return $w.$s;
	}

	/**
	 * Appends 's' to the end of string if input is not 1
	 *
	 * @param string $w    Text to pluralize
	 * @param int    $in   Number to base pluralization off of
	 * @param bool   $prep Prepend number to text
	 *
	 * @return string
	 */
	static function makePlural($w, int $in = 0, $prep = false){
		$ret = ($prep?"$in ":'');
		if ($w[-1] === 'y' && $in != 1)
			return $ret.self::substring($w,0,-1).'ies';
		return $ret.$w.($in != 1 && !in_array(strtolower($w),self::$_uncountableWords) ?'s':'');
	}

	private static $_uncountableWords = array('staff');

	/**
	 * Detect user's web browser based on user agent
	 *
	 * @param string|null $user_agent User-Agent string to check
	 *
	 * @return array
	 */
	static function detectBrowser($user_agent = null){
		$Return = array('user_agent' => !empty($user_agent) ? $user_agent : $_SERVER['HTTP_USER_AGENT']);
		$browser = new Browser($Return['user_agent']);
		$name = $browser->getBrowser();
		if ($name !== Browser::BROWSER_UNKNOWN){
			$Return['browser_name'] = $name;

			$ver = $browser->getVersion();
			if ($ver !== Browser::VERSION_UNKNOWN)
				$Return['browser_ver'] = $ver;
		}
		$Return['platform'] = $browser->getPlatform();
		return $Return;
	}

	// Converts a browser name to it's equivalent class name
	static function browserNameToClass($BrowserName){
		return preg_replace(new RegExp('[^a-z]'),'',strtolower($BrowserName));
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
	static function trim(string $str, bool $multiline = false, string $chars = " \t\n\r\0\x0B"){
		$out = preg_replace(new RegExp(' +'),' ',trim($str, $chars));
		if ($multiline)
			$out = preg_replace(new RegExp('(\r\n|\r)'),"\n",$out);

		return $out;
	}

	/**
	 * Averages the numbers inside an array
	 *
	 * @param int[] $numbers
	 *
	 * @return float
	 */
	static function average(...$numbers){
		return array_sum($numbers)/count($numbers);
	}

	/**
	 * Checks if a deviation is in the club
	 *
	 * @param int|string $DeviationID
	 *
	 * @return bool|int
	 */
	static function isDeviationInClub($DeviationID){
		if (!is_int($DeviationID))
			$DeviationID = intval(CoreUtils::substring($DeviationID, 1), 36);

		try {
			$DiFiRequest = HTTP::legitimateRequest("http://deviantart.com/global/difi/?c[]=\"DeviationView\",\"getAllGroups\",[\"$DeviationID\"]&t=json");
		}
		catch (CURLRequestException $e){
			return $e->getCode();
		}
		if (empty($DiFiRequest['response']))
			return 1;

		$DiFiRequest = @JSON::decode($DiFiRequest['response'], JSON::AS_OBJECT);
		if (empty($DiFiRequest->DiFi->status))
			return 2;
		if ($DiFiRequest->DiFi->status !== 'SUCCESS')
			return 3;
		if (empty($DiFiRequest->DiFi->response->calls))
			return 4;
		if (empty($DiFiRequest->DiFi->response->calls[0]))
			return 5;
		if (empty($DiFiRequest->DiFi->response->calls[0]->response))
			return 6;
		if (empty($DiFiRequest->DiFi->response->calls[0]->response->status))
			return 7;
		if ($DiFiRequest->DiFi->response->calls[0]->response->status !== 'SUCCESS')
			return 8;
		if (empty($DiFiRequest->DiFi->response->calls[0]->response->content->html))
			return 9;

		$html = $DiFiRequest->DiFi->response->calls[0]->response->content->html;
		return strpos($html, 'gmi-groupname="MLP-VectorClub">') !== false;
	}

	/**
	 * Checks if a deviation is in the club and stops execution if it isn't
	 *
	 * @param string $favme
	 * @param bool   $throw If true an Exception will be thrown instead of responding
	 */
	static function checkDeviationInClub($favme, $throw = false){
		$Status = self::isDeviationInClub($favme);
		if ($Status !== true){
			$errmsg = (
				$Status === false
				? "The deviation has not been submitted to/accepted by the group yet"
				: "There was an issue while checking the acceptance status (Error code: $Status)"
			);
			if ($throw)
				throw new \Exception($errmsg);
			Response::fail($errmsg);
		}
	}

	/**
	 * Converts a HEX color string to an array of R, G and B values
	 * Related: http://stackoverflow.com/a/15202130/1344955
	 *
	 * @param string $hex
	 *
	 * @return int[]
	 */
	static function hex2Rgb($hex){
		return sscanf($hex, "#%02x%02x%02x");
	}

	/**
	 * Normalize a misaligned Stash submission ID
	 *
	 * @param string $id Stash submission ID
	 *
	 * @return string
	 */
	static function nomralizeStashID($id){
		$normalized = ltrim($id,'0');
		return self::length($normalized) < 12 ? '0'.$normalized : $normalized;
	}

	/**
	 * Retrieve the full size URL for a submission
	 *
	 * @param string $id
	 * @param string $prov
	 *
	 * @return null|string
	 */
	static function getFullsizeURL($id, $prov){
		$stash_url = $prov === 'sta.sh' ? "http://sta.sh/$id" : "http://fav.me/$id";
		try {
			$stashpage = HTTP::legitimateRequest($stash_url,null,null);
		}
		catch (CURLRequestException $e){
			if ($e->getCode() === 404)
				return 404;
			return 1;
		}
		catch (\Exception $e){
			return 2;
		}
		if (empty($stashpage))
			return 3;

		$STASH_DL_LINK_REGEX = '(https?://(sta\.sh|www\.deviantart\.com)/download/\d+/[a-z\d_]+-d[a-z\d]{6,}\.(?:png|jpe?g|bmp)\?[^"]+)';
		$urlmatch = preg_match(new RegExp('<a\s+class="[^"]*?dev-page-download[^"]*?"\s+href="'.
			$STASH_DL_LINK_REGEX.'"'), $stashpage['response'], $_match);

		if (!$urlmatch)
			return 4;

		$fullsize_url = HTTP::findRedirectTarget(htmlspecialchars_decode($_match[1]), $stash_url);

		if (empty($fullsize_url))
			return 5;

		global $Database;
		if ($Database->where('id', $id)->where('provider', $prov)->has('cached-deviations'))
			$Database->where('id', $id)->where('provider', $prov)->update('cached-deviations', array(
				'fullsize' => $fullsize_url
			));

		return URL::makeHttps($fullsize_url);
	}

	static function getOverdueSubmissionList(){
		global $Database;

		$Query = $Database->rawQuery(
			"SELECT reserved_by, COUNT(*) as cnt FROM (
				SELECT reserved_by FROM reservations
				WHERE deviation_id IS NOT NULL AND lock = false
				UNION ALL
				SELECT reserved_by FROM requests
				WHERE deviation_id IS NOT NULL AND lock = false
			) t
			GROUP BY reserved_by
			HAVING COUNT(*) >= 5
			ORDER BY cnt DESC;");

		if (empty($Query))
			return;

		$HTML = "<table>";
		foreach ($Query as $row){
			$link = Users::get($row['reserved_by'])->getProfileLink(User::LINKFORMAT_FULL);
			$r = min(round($row['cnt']/10*255),255);
			$count = "<strong style='color:rgb($r,0,0)'>{$row['cnt']}</strong>";

			$HTML .= "<tr><td>$link</td><td>$count</td></tr>";
		}
		return "$HTML</table>";
	}

	static function downloadFile($contents, $name){
		header('Content-Type: application/octet-stream');
		header('Content-Transfer-Encoding: Binary');
		header("Content-disposition: attachment; filename=\"$name\"");
		die($contents);
	}

	static function substring(...$args){
		return mb_substr(...$args);
	}

	static function length(...$args){
		return mb_strlen(...$args);
	}

	/**
	 * Cut a string to the specified length
	 *
	 * @param string $str
	 * @param int    $len
	 *
	 * @return string
	 */
	static function cutoff(string $str, $len){
		$strlen = self::length($str);
		return $strlen > $len ? self::trim(self::substring($str, 0, $len-1)).'…' : $str;
	}

	static function socketEvent(string $event, array $data){
		$elephant = new \ElephantIO\Client(new SocketIOEngineVersion1X('https://ws.'.WS_SERVER_DOMAIN.':8667', array(
			'context' => array(
				'http' => array(
					'header' => 'Cookie: access='.urlencode(WS_SERVER_KEY)
				)
			)
		)));

		$elephant->initialize();
		$elephant->emit($event, $data);
		$elephant->close();
	}

	static $VECTOR_APPS = array(
		'' => "(don’t show)",
		'illustrator' => 'Adobe Illustrator',
		'inkscape' => 'Inkscape',
		'ponyscape' => 'Ponyscape',
	);

	static function yiq($hex){
		$rgb = self::hex2Rgb($hex);
	    return (($rgb[0]*299)+($rgb[1]*587)+($rgb[2]*114))/1000;
	}

	/**
	 * Universal method for setting keys/properties of arrays/objects
	 * @param array|object $on
	 * @param string       $key
	 * @param mixed        $value
	 */
	static function set(&$on, $key, $value){
		if (is_object($on))
			$on->$key = $value;
		else if (is_array($on))
			$on[$key] = $value;
		else throw new \Exception('$on is of invalid type ('.gettype($on).')');
	}

	/**
	 * Checks if an image exists on the web
	 * Specify raw HTTP codes as integers to $onlyFail to only report failure on those codes
	 *
	 * @param string $url
	 * @param array  $onlyFails
	 *
	 * @return bool
	 */
	static function isURLAvailable(string $url, array $onlyFails = []):bool{
		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL => $url,
			CURLOPT_NOBODY => 1,
			CURLOPT_FAILONERROR => 1,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_FOLLOWLOCATION => true,
		));
		$available = curl_exec($ch) !== false;
		$responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($available === false && !empty($onlyFails))
			$available = !in_array($responseCode, $onlyFails);
		curl_close($ch);

		return $available;
	}

	static function msleep(int $ms){
		usleep($ms*1000);
	}

	static function sha256(string $data):string {
		return hash('sha256', $data);
	}

	static function makeUrlSafe(string $string):string{
		return CoreUtils::trim(preg_replace(new RegExp('-+'),'-',preg_replace(new RegExp('[^A-Za-z\d\-]'),'-', $string)),false,'-');
	}

	/** @var Client */
	private static $_elastiClient;

	static function elasticClient():Client {
		if (!isset(self::$_elastiClient))
			self::$_elastiClient = ClientBuilder::create()->setHosts(['127.0.0.1:9200'])->build();

		return self::$_elastiClient;
	}
}
