<?php

	use Exceptions\cURLRequestException;

	class CoreUtils {
		/**
		 * Loads a single class or a set of classes
		 *
		 * @param string $class
		 *
		 * @throws Exception
		 */
		static function CanIHas($class){
			if (class_exists($class))
				return;
			$path = APPATH.'includes/classes/'.str_replace('\\','/',$class).'.php';
			if (!file_exists($path))
				throw new Exception("Could not load class/interface $class: file ($path) not found");
			require $path;
			if (!class_exists($class) && !interface_exists($class))
				throw new Exception("Could not load class/interface $class: definition not found in $path");
		}

		const FIXPATH_EMPTY = '#';
		/**
		 * Forces an URL rewrite to the specified path
		 *
		 * @param string $fix_uri  URL to forcibly redirect to
		 * @param int    $http HTPP status code for the redirect
		 */
		static function FixPath($fix_uri, $http = 301){
			$_split = explode('?', $_SERVER['REQUEST_URI'], 2);
			$path = $_split[0];
			$query = empty($_split[1]) ? '' : "?{$_split[1]}";

			$_split = explode('?', $fix_uri, 2);
			$fix_path = $_split[0];
			$fix_query = empty($_split[1]) ? '' : "?{$_split[1]}";

			if (empty($fix_query))
				$fix_query = $query;
			else {
				$query_assoc = self::QueryStringAssoc($query);
				$fix_query_assoc = self::QueryStringAssoc($fix_query);
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
				HTTP::Redirect("$fix_path$fix_query", STAY_ALIVE, $http);
		}

		/**
		 * Turn query string into an associative array
		 *
		 * @param string $query
		 *
		 * @return array
		 */
		static function QueryStringAssoc($query){
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
		static function AposEncode($str){
			return self::EscapeHTML($str, ENT_QUOTES);
		}

		static function EscapeHTML($html, $mask = null){
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
		static function Notice($type, $title, $text = null, $center = false){
			if (!in_array($type, self::$NOTICE_TYPES))
				throw new Exception("Invalid notice type $type");

			if (!is_string($text)){
				if (is_bool($text))
					$center = $text;
				$text = $title;
				unset($title);
			}

			$HTML = '';
			if (!empty($title))
				$HTML .= '<label>'.self::EscapeHTML($title).'</label>';

			$textRows = preg_split("/(\r\n|\n|\r){2}/", $text);
			foreach ($textRows as $row)
				$HTML .= '<p>'.self::Trim($row).'</p>';

			if ($center)
				$type .= ' align-center';
			return "<div class='notice $type'>$HTML</div>";
		}

		/**
		 * Display a 404 page
		 */
		static function NotFound(){
			if (POST_REQUEST || isset($_GET['via-js'])){
				$RQURI = rtrim(str_replace('via-js=true','',$_SERVER['REQUEST_URI']),'?&');
				Response::Fail("HTTP 404: ".(POST_REQUEST?'Endpoint':'Page')." ($RQURI) does not exist");
			}

			HTTP::StatusCode(404);
			global $do;
			$do = '404';
			self::LoadPage(array(
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
		 * );
		 *
		 * @param array $options
		 */
		static function LoadPage($options){
			// Page <title>
			if (isset($options['title']))
				$GLOBALS['title'] = $options['title'];

			// Page heading
			if (isset($options['heading']))
				$GLOBALS['heading'] = $options['heading'];

			// SE crawling disable
			if (in_array('no-robots', $options))
				$norobots = true;

			# CSS
			$DEFAULT_CSS = array('theme');
			$customCSS = array();
			// Only add defaults when needed
			if (array_search('no-default-css', $options) === false)
				$customCSS = array_merge($customCSS, $DEFAULT_CSS);

			# JavaScript
			$DEFAULT_JS = array('moment','global','dialog');
			if ($GLOBALS['signedIn'])
				array_splice($DEFAULT_JS,0,0,array('socket.io-1.4.5'));
			$customJS = array();
			// Only add defaults when needed
			if (array_search('no-default-js', $options) === false)
				$customJS = array_merge($customJS, $DEFAULT_JS);

			# Check assests
			self::_checkAssets($options, $customCSS, 'scss/min', 'css');
			self::_checkAssets($options, $customJS, 'js/min', 'js');

			# Import global variables
			foreach ($GLOBALS as $nev => $ertek)
				if (!isset($$nev))
					$$nev = $ertek;

			# Putting it together
			$view = empty($options['view']) ? $GLOBALS['do'] : $options['view'];
			$viewPath = "views/{$view}.php";

			header('Content-Type: text/html; charset=utf-8;');

			if (empty($_GET['via-js'])){
				ob_start();
				require 'views/header.php';
				require $viewPath;
				require 'views/footer.php';
				$content = ob_get_clean();
				echo self::_clearIndentation($content);
				die();
			}
			else {
				$_SERVER['REQUEST_URI'] = rtrim(str_replace('via-js=true','',CSRFProtection::RemoveParamFromURL($_SERVER['REQUEST_URI'])), '?&');
				ob_start();
				require 'views/sidebar.php';
				$sidebar = ob_get_clean();
				ob_start();
				require $viewPath;
				$content = ob_get_clean();
				Response::Done(array(
					'css' => $customCSS,
					'js' => $customJS,
					'title' => (isset($GLOBALS['title'])?$GLOBALS['title'].' - ':'').SITE_TITLE,
					'content' => self::_clearIndentation($content),
					'sidebar' => self::_clearIndentation($sidebar),
					'footer' => CoreUtils::GetFooter(WITH_GIT_INFO),
					'avatar' => $GLOBALS['signedIn'] ? $GLOBALS['currentUser']['avatar_url'] : GUEST_AVATAR,
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
			return regex_replace(new RegExp('(\n|\r|\r\n)[\t ]*'), '$1', $HTML);
		}

		/**
		 * Checks assets from LoadPage()
		 *
		 * @param array    $options    Options array
		 * @param string[] $customType Array of partial file names
		 * @param string   $relpath    File path relative to /
		 * @param string   $ext        The literal strings 'css' or 'js'
		 *
		 * @throws Exception
		 */
		private static function _checkAssets($options, &$customType, $relpath, $ext){
			if (isset($options[$ext])){
				$$ext = $options[$ext];
				if (!is_array($$ext))
					$customType[] = $$ext;
				else $customType = array_merge($customType, $$ext);
				if (array_search("do-$ext", $options) !== false){
					global $do;
					$customType[] = $do;
				}
			}
			else if (array_search("do-$ext", $options) !== false){
				global $do;
				$customType[] = $do;
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
				throw new Exception("File /$relpath/$item does not exist");
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
		static function Pad($input, $pad_length = 2, $pad_string = '0', $pad_type = STR_PAD_LEFT){
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
		static function Capitalize($str, $all = false){
			if ($all) return preg_replace_callback(new RegExp('((?:^|\s)[a-z])'), function($match){
				return strtoupper($match[1]);
			}, $str);
			else return strlen($str) === 1 ? strtoupper($str) : strtoupper($str[0]).substr($str,1);
		}

		// Turns a file size ini setting value into bytes
		private static function _shortSizeInBytes($size){
			$unit = substr($size, -1);
			$value = intval(substr($size, 0, -1), 10);
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
		 * @return string
		 */
		static function GetMaxUploadSize(){
			$sizes = array(ini_get('post_max_size'), ini_get('upload_max_filesize'));

			$workWith = $sizes[0];
			if ($sizes[1] !== $sizes[0]){
				$sizesBytes = array_map('self::_shortSizeInBytes', $sizes);
				if ($sizesBytes[1] < $sizesBytes[0])
					$workWith = $sizes[1];
			}

			return regex_replace(new RegExp('^(\d+)([GMk])$','i'), '$1 $2B', strtoupper($workWith));
		}

		/**
		 * Export PHP variables to JS through a script tag
		 *
		 * @param array $export Associative aray where keys are the desired JS variable names
		 * @throws Exception
		 */
		static function ExportVars($export){
			$HTML =  '<script>var ';
			foreach ($export as $name => $value){
				$type = gettype($value);
				switch ($type){
					case "boolean":
						$value = $value ? 'true' : 'false';
					break;
					case "array":
						$value = JSON::Encode($value);
					break;
					case "string":
						// regex test
						if (regex_match(new RegExp('^/(.*)/([a-z]*)$','u'), $value, $regex_parts))
							$value = (new RegExp($regex_parts[1],$regex_parts[2]))->jsExport();
						else $value = JSON::Encode($value);
					break;
					case "integer":
					case "float":
					case "null":
						$value = strval($value);
					break;
					default:
						if ($value instanceof RegExp){
							$value = $value->jsExport();
							break;
						}
						throw new Exception("Exporting unsupported variable $name of type $type");
				}
				$HTML .= "$name=$value,";
			}
			echo rtrim($HTML,',').'</script>';
		}

		/**
		 * Sanitizes HTML that comes from user input
		 *
		 * @param string   $dirty_html HTML coming from the user
		 * @param string[] $allowed    Additional allowed tags
		 *
		 * @return string Sanitized HTML code
		 */
		static function SanitizeHtml($dirty_html, $allowed = null){
			require_once APPATH."includes/HTMLPurifier/HTMLPurifier.standalone.php";
			$config = HTMLPurifier_Config::createDefault();
			$whitelist = array('strong','b','em','i');
			if (!empty($allowed))
				$whitelist = array_merge($whitelist, $allowed);
			/** @noinspection PhpUndefinedMethodInspection */
			$config->set('HTML.AllowedElements', $whitelist);
			/** @noinspection PhpUndefinedMethodInspection */
			$config->set('Core.EscapeInvalidTags', true);
			$purifier = new HTMLPurifier($config);
			/** @noinspection PhpUndefinedMethodInspection */
			return self::TrimMultiline($purifier->purify($dirty_html));
		}

		/**
		 * Analyzes a file path and creates the filder structure necessary to sucessfully store it
		 *
		 * @param string $path Path to analyze
		 *
		 * @return bool Whether the folder was sucessfully created
		 */
		static function CreateUploadFolder($path){
			$DS = RegExp::escapeBackslashes('\/');
			$folder = regex_replace(new RegExp("^(.*[$DS])[^$DS]+$"),'$1',regex_replace(new RegExp('$DS'),'\\',$path));
			return !is_dir($folder) ? mkdir($folder,0777,true) : true;
		}

		/**
		 * Formats a 1-dimensional array of stings naturally
		 *
		 * @param string[] $list
		 * @param string   $append
		 * @param string   $separator
		 *
		 * @return string
		 */
		static function ArrayToNaturalString($list, $append = 'and', $separator = ','){
			if (is_string($list)) $list = explode($separator, $list);

			if (count($list) > 1){
				$list_str = $list;
				array_splice($list_str,count($list_str)-1,0,$append);
				$i = 0;
				$maxDest = count($list_str)-3;
				while ($i < $maxDest){
					if ($i == count($list_str)-1) continue;
					$list_str[$i] = $list_str[$i].',';
					$i++;
				}
				$list_str = implode(' ',$list_str);
			}
			else $list_str = $list[0];
			return $list_str;
		}

		/**
		 * Checks validity of a string based on regex
		 *  and responds if invalid chars are found
		 *
		 * @param string $string      The value bein checked
		 * @param string $Thing       Human-readable name for $string
		 * @param string $pattern     An inverse pattern that matches INVALID characters
		 * @param bool   $returnError Whether to return the error or just spit it out
		 *
		 * @return null|string
		 */
		static function CheckStringValidity($string, $Thing, $pattern, $returnError = false){
			if (regex_match(new RegExp($pattern,'u'), $string, $fails)){
				$invalid = array();
				foreach ($fails as $f)
					if (!in_array($f, $invalid)){
						switch ($f){
							case "\n":
								$invalid[] = '\n';
							case "\r":
								$invalid[] = '\r';
							default:
								$invalid[] = $f;
						}
					}

				$s = count($invalid)!==1?'s':'';
				$the_following = count($invalid)!==1?' the following':'an';
				$Error = "$Thing (".self::EscapeHTML($string).") contains $the_following invalid character$s: ".CoreUtils::ArrayToNaturalString($invalid);
				if ($returnError)
					return $Error;
				Response::Fail($Error);
			}
		}

		/**
		 * Returns text HTML of the website's footer
         *
		 * @param bool $with_git_info
		 *
		 * @return string
		 */
		static function GetFooter($with_git_info = false){
			if (Permission::Insufficient('developer'))
				$append = '';
			else {
				global $Database, $CGDb;
				$append = ' | Render: '.number_format(microtime(true)-EXEC_START_MICRO, 4).'s | SQL Queries: '.($Database->query_count+($CGDb->query_count??0));
			}

			return ($with_git_info?self::GetFooterGitInfo():'')."<a class='issues' href='".GITHUB_URL."/issues' target='_blank'>Known issues</a> | <a class='send-feedback'>Send feedback</a>$append";
		}

		/**
		 * Returns the HTML of the GIT informaiiton in the website's footer
		 *
		 * @return string
		 */
		static function GetFooterGitInfo(){
			$commit_info = "Running <strong><a href='".GITHUB_URL."' title='Visit the GitHub repository'>MLPVC-RR</a>";
			$commit_id = rtrim(shell_exec('git rev-parse --short=4 HEAD'));
			if (!empty($commit_id)){
				$commit_time = Time::Tag(date('c',strtotime(shell_exec('git log -1 --date=short --pretty=format:%ci'))));
				$commit_info .= "@<a href='".GITHUB_URL."/commit/$commit_id' title='See exactly what was changed and why'>$commit_id</a></strong> created $commit_time";
			}
			else $commit_info .= "</strong> (version information unavailable)";
			$commit_info .= ' | ';
			return $commit_info;
		}

		/**
		 * Returns the HTML code of the navigation in the header
		 *
		 * @param bool $disabled
		 *
		 * @return string
		 */
		static function GetNavigationHTML($disabled = false){
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
					global $Episodes, $Pagination;
					if (isset($Episodes))
						$NavItems['eps'][1] .= " - Page {$Pagination->page}";
				}
				if ($do === 'episode' && !empty($GLOBALS['CurrentEpisode'])){
					if (!empty($GLOBALS['Latest']))
						$NavItems['latest'][0] = $_SERVER['REQUEST_URI'];
					else $NavItems['eps']['subitem'] = CoreUtils::Cutoff($GLOBALS['heading'],Episode::TITLE_CUTOFF);
				}
				global $Color, $EQG;
				$NavItems['colorguide'] = array("/cg", (!empty($EQG)?'EQG ':'')."$Color Guide");
				if ($do === 'colorguide'){
					global $Tags, $Changes, $Ponies, $Pagination, $Appearance, $Map;
					if (!empty($Appearance))
						$NavItems['colorguide']['subitem'] = (isset($Map)?"Sprite {$Color}s - ":'').$Appearance['label'];
					else if (isset($Ponies))
						$NavItems['colorguide'][1] .= " - Page {$Pagination->page}";
					else {
						if ($GLOBALS['data'] === 'full'){
							$NavItems['colorguide']['subitem'] = 'Full List';
						}
						else {
							if (isset($Tags)) $pagePrefix = 'Tags';
							else if (isset($Changes)) $pagePrefix = "Major $Color Changes";

							$NavItems['colorguide']['subitem'] = (isset($pagePrefix) ? "$pagePrefix - " : '')."Page {$Pagination->page}";
						}
					}

				}
				if ($GLOBALS['signedIn'])
					$NavItems['u'] = array("/@{$GLOBALS['currentUser']['name']}",'Account');
				if ($do === 'user' || Permission::Sufficient('staff')){
					global $User, $sameUser;

					$NavItems['users'] = array('/users', 'Users', Permission::Sufficient('staff'));
					if (!empty($User) && empty($sameUser))
						$NavItems['users']['subitem'] = $User['name'];
				}
				if (Permission::Sufficient('staff')){
					$NavItems['admin'] = array('/admin', 'Admin');
					global $task;
					if ($task === 'logs'){
						global $Pagination;
						$NavItems['admin']['subitem'] = "Logs - Page {$Pagination->page}";
					}
				}
				$NavItems[] = array('/about', 'About');
			}
			else $NavItems = array(array(true, 'HTTP 503', false, 'subitem' => 'Service Temporarily Unavailable'));

			$GLOBALS['NavHTML'] = '';
			$currentSet = false;
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
			$GLOBALS['NavHTML'] .= '<li><a href="http://mlp-vectorclub.deviantart.com/" target="_blank">MLP-VectorClub</a></li>';
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
			$current = (!$currentSet || $htmlOnly === HTML_ONLY) && ($path === true || regex_match(new RegExp("^$path($|/)"), $RQURI));
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
		static function RenderSidebarUsefulLinks(){
			global $Database, $signedIn, $currentUser;
			if (!$signedIn) return;
			$Links = $Database->orderBy('"order"','ASC')->get('usefullinks');

			$Render = array();
			foreach ($Links as $l){
				if (Permission::Insufficient($l['minrole']))
					continue;

				if (!empty($l['title'])){
					$title = str_replace("'",'&apos;',$l['title']);
					$title = "title='$title'";
				}
				else $title = '';

				$href = $l['url'][0] === '#' ? "class='action--".substr($l['url'],1)."'" : "href='{$l['url']}'";

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
		static function GetSidebarUsefulLinksListHTML($wrap = true){
			global $Database;
			$HTML = $wrap ? '<ol>' : '';
			$UsefulLinks = $Database->orderBy('"order"','ASC')->get('usefullinks');
			foreach ($UsefulLinks as $l){
				$href = "href='".CoreUtils::AposEncode($l['url'])."'";
				if ($l['url'][0] === '#')
					$href .= " class='action--".substr($l['url'],1)."'";
				$title = CoreUtils::AposEncode($l['title']);
				$label = htmlspecialchars_decode($l['label']);
				$cansee = Permission::$ROLES_ASSOC[$l['minrole']];
				if ($l['minrole'] !== 'developer')
					$cansee = self::MakePlural($cansee, 0).' and above';
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
		 *
		 * @return string
		 */
		static function Posess($w){
			return "$w'".(substr($w, -1) !== 's'?'s':'');
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
		static function MakePlural($w, $in, $prep = false){
			return ($prep?"$in ":'').$w.($in != 1 && !in_array(strtolower($w),self::$_uncountableWords) ?'s':'');
		}

		private static $_uncountableWords = array('staff');

		/**
		 * Detect user's web browser based on user agent
		 *
		 * @param string|null $user_agent User-Agent string to check
		 *
		 * @return array
		 */
		static function DetectBrowser($user_agent = null){
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
		static function BrowserNameToClass($BrowserName){
			return regex_replace(new RegExp('[^a-z]'),'',strtolower($BrowserName));
		}

		/**
		 * Escapes values for use in LIKE checks
		 *
		 * @param string $str
		 *
		 * @return string
		 */
		static function EscapeLikeValue($str){
			return preg_replace('~(^|[^\\\\])([%_\[\]])~','$1\\\\$2', $str);
		}

		/**
		 * Trims a string while truncating consecutive spaces
		 *
		 * @param string $str
		 * @param string $chars
		 *
		 * @return string
		 */
		static function Trim($str, $chars = "\t\n\r\0\x0B"){
			return regex_replace(new RegExp(' +'),' ',trim($str, $chars));
		}

		/**
		 * Trims a string while truncating consecutive spaces and normalizing newlines
		 *
		 * @param string $str
		 * @param string $chars
		 *
		 * @return string
		 */
		static function TrimMultiline($str, $chars = "\t\n\r\0\x0B"){
			return regex_replace(new RegExp('(\r\n|\r)'),"\n",self::Trim($str,$chars));
		}

		/**
		 * Averages the numbers inside an array
		 *
		 * @param int[] $numbers
		 *
		 * @return float
		 */
		static function Average(...$numbers){
			return array_sum($numbers)/count($numbers);
		}

		/**
		 * Checks if a deviation is in the group
		 *
		 * @param int|string $DeviationID
		 *
		 * @return bool|int
		 */
		static function IsDeviationInClub($DeviationID){
			if (!is_int($DeviationID))
				$DeviationID = intval(substr($DeviationID, 1), 36);

			try {
				$DiFiRequest = HTTP::LegitimateRequest("http://deviantart.com/global/difi/?c[]=\"DeviationView\",\"getAllGroups\",[\"$DeviationID\"]&t=json");
			}
			catch (cURLRequestException $e){
				return $e->getCode();
			}
			if (empty($DiFiRequest['response']))
				return 1;

			$DiFiRequest = @JSON::Decode($DiFiRequest['response'], JSON::$AsObject);
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
		static function CheckDeviationInClub($favme, $throw = false){
			$Status = self::IsDeviationInClub($favme);
			if ($Status !== true){
				$errmsg = (
					$Status === false
					? "The deviation has not been submitted to/accepted by the group yet"
					: "There was an issue while checking the acceptance status (Error code: $Status)"
				);
				if ($throw)
					throw new Exception($errmsg);
				Response::Fail($errmsg);
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
		static function Hex2Rgb($hex){
			return sscanf($hex, "#%02x%02x%02x");
		}

		/**
		 * Normalize a misaligned Stash submission ID
		 *
		 * @param string $id Stash submission ID
		 *
		 * @return string
		 */
		static function NomralizeStashID($id){
			$normalized = ltrim($id,'0');
			return strlen($normalized) < 12 ? '0'.$normalized : $normalized;
		}

		/**
		 * Retrieve the full size URL for a submission
		 *
		 * @param string $id
		 * @param string $prov
		 *
		 * @return null|string
		 */
		static function GetFullsizeURL($id, $prov){
			$stash_url = $prov === 'sta.sh' ? "http://sta.sh/$id" : "http://fav.me/$id";
			try {
				$stashpage = HTTP::LegitimateRequest($stash_url,null,null);
			}
			catch (cURLRequestException $e){
				if ($e->getCode() === 404)
					return 404;
				return 1;
			}
			catch (Exception $e){
				return 2;
			}
			if (empty($stashpage))
				return 3;

			$STASH_DL_LINK_REGEX = '(https?://(sta\.sh|www\.deviantart\.com)/download/\d+/[a-z\d_]+-d[a-z\d]{6,}\.(?:png|jpe?g|bmp)\?[^"]+)';
			$urlmatch = regex_match(new RegExp('<a\s+class="[^"]*?dev-page-download[^"]*?"\s+href="'.
				$STASH_DL_LINK_REGEX.'"'), $stashpage['response'], $_match);

			if (!$urlmatch)
				return 4;

			$fullsize_url = HTTP::FindRedirectTarget(htmlspecialchars_decode($_match[1]), $stash_url);

			if (empty($fullsize_url))
				return 5;

			global $Database;
			if ($Database->where('id', $id)->where('provider', $prov)->has('deviation_cache'))
				$Database->where('id', $id)->where('provider', $prov)->update('deviation_cache', array(
					'fullsize' => $fullsize_url
				));

			return URL::MakeHttps($fullsize_url);
		}

		static function GetOverdueSubmissionList(){
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
				$link = User::GetProfileLink(User::Get($row['reserved_by']), Time::FORMAT_FULL);
				$count = "<strong style='color:rgb(".min(round($row['cnt']/10*255),255).",0,0)'>{$row['cnt']}</strong>";

				$HTML .= "<tr><td>$link</td><td>$count</td></tr>";
			}
			return "$HTML</table>";
		}

		static function DownloadFile($contents, $name){
			header('Content-Type: application/octet-stream');
			header('Content-Transfer-Encoding: Binary');
			header("Content-disposition: attachment; filename=\"$name\"");
			die($contents);
		}

		static function Substring(...$args){
			return function_exists('mb_substr') ? mb_substr(...$args) : substr(...$args);
		}

		static function Cutoff($str, $len){
			$strlen = strlen($str);
			return $strlen > $len ? self::Substring($str, 0, $len-2).'…' : $str;
		}

		static function SocketEvent($event, array $data){
			$options = array(
				'context' => array(
					'http' => array(
						'header' => 'Cookie: access='.urlencode(WS_SERVER_KEY)
					)
				)
			);
			if (regex_match(new RegExp('\.lc$'), WS_SERVER_DOMAIN))
				$options['context']['ssl'] = array(
			        "verify_peer" => false,
			        "verify_peer_name" => false,
				);

			$elephant = new ElephantIO\Client(new ElephantIO\Engine\SocketIO\Version1X('https://ws.'.WS_SERVER_DOMAIN.':8667', $options));

			$elephant->initialize();
			$elephant->emit($event, $data);
			$elephant->close();
		}

		static $VECTOR_APPS = array(
			'' => "(don't show)",
			'illustrator' => 'Adobe Illustrator',
			'inkscape' => 'Inkscape',
			'ponyscape' => 'Ponyscape',
		);
	}
