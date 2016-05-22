<?php

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
				throw new Exception("Could not load class $class: file ($path) not found");
			require $path;
			if (!class_exists($class))
				throw new Exception("Could not load class $class: class not found in $path");
		}

		/**
		 * Redirection
		 *
		 * @param string $url  Redirection target URL
		 * @param bool   $die  Stop script execution after redirect
		 * @param int    $http HTTP status code
		 */
		static function Redirect($url = '/', $die = true, $http = 301){
			header("Location: $url",$die,$http);
			if ($die !== STAY_ALIVE) die("<script>location.replace('".$url."')</script>");
		}

		/**
		 * Forces an URL rewrite to the specified path
		 *
		 * @param string $fix_uri  URL to forcibly redirect to
		 * @param int    $http HTPP status code for the redirect
		 */
		static function FixPath($fix_uri, $http = 301){
			list($path, $query) = explode('?', "{$_SERVER['REQUEST_URI']}?");
			$query = empty($query) ? '' : "?$query";
			list($fix_path, $fix_query) = explode('?', "$fix_uri?");
			$fix_query = empty($fix_query) ? '' : "?$fix_query";
			if (empty($fix_query))
				$fix_query = $query;
			else {
				$query_assoc = self::QueryStringAssoc($query);
				$fix_query_assoc = self::QueryStringAssoc($fix_query);
				$merged = $query_assoc;
				foreach ($fix_query_assoc as $key => $item)
					$merged[$key] = $item;
				$fix_query_arr = array();
				foreach ($merged as $key => $item)
					$fix_query_arr[] = "$key".(!empty($item)?"=$item":'');
				$fix_query = empty($fix_query_arr) ? '' : implode('&', $fix_query_arr);
			}
			if ($path !== $fix_path || $query !== $fix_query)
				self::Redirect("$fix_path$fix_query", STAY_ALIVE, $http);
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
				parse_str($query, $assoc);
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
			return str_replace("'", '&apos;', $str);
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
				$HTML .= '<label>'.htmlspecialchars($title).'</label>';

			$textRows = preg_split("/(\r\n|\n|\r){2}/", $text);
			foreach ($textRows as $row)
				$HTML .= '<p>'.self::Trim($row).'</p>';

			if ($center)
				$type .= ' align-center';
			return "<div class='notice $type'>$HTML</div>";
		}

		/**
		 * Sends replies to AJAX requests in a universal form
		 * $s respresents the request status, a truthy value
		 *  means the request was successful, a falsey value
		 *  means the request failed
		 * $x can be used to
		 *
		 * @param string|array $message Message
		 * @param bool|int     $status  Status (truthy/falsy value)
		 * @param array        $extra   Append additional data to the response
		 */
		static function Respond($message = 'Insufficent permissions.', $status = false, $extra = null){
			header('Content-Type: application/json');
			if ($message === true)
				$response = array('status' => true);
			else if (is_array($message) && $status == false && empty($extra)){
				$message['status'] = true;
				$response = $message;
			}
			else {
				if (strpos($message, ERR_DB_FAIL) !== false){
					global $Database;
					$message = rtrim("$message: ".$Database->getLastError(), ': ');
				}
				$response = array(
					"message" => $message,
					"status" => (bool) $status,
				);
			}
			if (!empty($extra))
				$response = array_merge($response, $extra);
			echo JSON::Encode($response);
			exit;
		}

		// HTTP Status Codes
		static $HTTP_STATUS_CODES = array(
				300 => 'Multiple Choices',
				301 => 'Moved Permanently',
				302 => 'Moved Temporarily',
				303 => 'See Other',
				304 => 'Not Modified',
				305 => 'Use Proxy',
				400 => 'Bad Request',
				401 => 'Unauthorized',
				402 => 'Payment Required',
				403 => 'Forbidden',
				404 => 'Not Found',
				405 => 'Method Not Allowed',
				406 => 'Not Acceptable',
				407 => 'Proxy Authentication Required',
				408 => 'Request Time-out',
				409 => 'Conflict',
				410 => 'Gone',
				411 => 'Length Required',
				412 => 'Precondition Failed',
				413 => 'Request Entity Too Large',
				414 => 'Request-URI Too Large',
				415 => 'Unsupported Media Type',
				500 => 'Internal Server Error',
				501 => 'Not Implemented',
				502 => 'Bad Gateway',
				503 => 'Service Unavailable',
				504 => 'Gateway Time-out',
				505 => 'HTTP Version not supported',
			);
		/**
		 * Sends an HTTP status code header with the response
		 *
		 * @param int  $code HTTP status code
		 * @param bool $die  Halt script execution afterwards
		 *
		 * @throws Exception
		 */
		static function StatusCode($code, $die = false){
			if (!isset(self::$HTTP_STATUS_CODES[$code]))
				throw new Exception("Unknown status code: $code");

			header($_SERVER['SERVER_PROTOCOL']." $code ".self::$HTTP_STATUS_CODES[$code]);
			if ($die === AND_DIE)
				die();
		}

		/**
		 * Display a 404 page
		 */
		static function NotFound(){
			if (POST_REQUEST || isset($_GET['via-js'])){
				$RQURI = rtrim(str_replace('via-js=true','',$_SERVER['REQUEST_URI']),'?&');
				CoreUtils::Respond("HTTP 404: ".(POST_REQUEST?'Endpoint':'Page')." ($RQURI) does not exist", 0);
			}

			global $do;
			$do = '404';
			self::LoadPage(array(
				'title' => '404',
				'view' => '404',
				'status-code' => 404,
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
		 *     'status-code' => int,  - Send a specific HTTP status code along
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

			// SE crawlign disable
			if (in_array('no-robots', $options))
				$norobots = true;

			# CSS
			$DEFAULT_CSS = array('theme');
			$customCSS = array();
			// Only add defaults when needed
			if (array_search('no-default-css', $options) === false)
				$customCSS = array_merge($customCSS, $DEFAULT_CSS);

			# JavaScript
			$DEFAULT_JS = array('global','moment','dyntime','dialog');
			$customJS = array();
			// Only add defaults when needed
			if (array_search('no-default-js', $options) === false)
				$customJS = array_merge($customJS, $DEFAULT_JS);

			# Check assests
			self::_checkAssets($options, $customCSS, 'css');
			self::_checkAssets($options, $customJS, 'js');

			# Add status code
			if (isset($options['status-code']))
				CoreUtils::StatusCode($options['status-code']);

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
				CoreUtils::Respond(array(
					'css' => $customCSS,
					'js' => $customJS,
					'title' => (isset($GLOBALS['title'])?$GLOBALS['title'].' - ':'').SITE_TITLE,
					'content' => self::_clearIndentation($content),
					'sidebar' => self::_clearIndentation($sidebar),
					'footer' => CoreUtils::GetFooter(),
					'avatar' => $GLOBALS['signedIn'] ? $GLOBALS['currentUser']['avatar_url'] : GUEST_AVATAR,
					'responseURL' => $_SERVER['REQUEST_URI'],
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
		 * @param string   $type       The literal strings 'css' or 'js'
		 *
		 * @throws Exception
		 */
		private static function _checkAssets($options, &$customType, $type){
			if (isset($options[$type])){
				$$type = $options[$type];
				if (!is_array($$type))
					$customType[] = $$type;
				else $customType = array_merge($customType, $$type);
				if (array_search("do-$type", $options) !== false){
					global $do;
					$customType[] = $do;
				}
			}
			else if (array_search("do-$type", $options) !== false){
				global $do;
				$customType[] = $do;
			}

			$pathStart = APPATH."$type/";
			foreach ($customType as $i => $item){
				if (file_exists("$pathStart$item.min.$type")){
					$customType[$i] = self::_formatFilePath("$item.min.$type");
					continue;
				}
				$item .= ".$type";
				if (!file_exists($pathStart.$item)){
					array_splice($customType,$i,1);
					throw new Exception("File /$type/$item does not exist");
				}
				else $customType[$i] = self::_formatFilePath($item);
			}
		}

		/**
		 * Turns asset filenames into URLs & adds modification timestamp parameters
		 *
		 * @param string $item
		 *
		 * @return string
		 */
		private static function _formatFilePath($item){
			$type = regex_replace(new RegExp('^.*\.(\w+)$'),'$1', $item);
			$pathStart = APPATH."$type/";
			return "/$type/$item?".filemtime($pathStart.$item);
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
						if (regex_match(new RegExp('^/(.*)/([a-z]*)$'), $value, $regex_parts))
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
			$config->set('HTML.AllowedElements', $whitelist);
			$purifier = new HTMLPurifier($config);
			return $purifier->purify($dirty_html);
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
					if (!in_array($f, $invalid))
						$invalid[] = $f;

				$s = count($invalid)!==1?'s':'';
				$the_following = count($invalid)!==1?' the following':'an';
				$Error = "$Thing (".htmlspecialchars($string).") contains $the_following invalid character$s: ".CoreUtils::ArrayToNaturalString($invalid);
				if ($returnError)
					return $Error;
				CoreUtils::Respond($Error);
			}
		}

		/**
		 * Returns text HTML of the website's footer
		 *
		 * @return string
		 */
		static function GetFooter(){
			global $Database, $CGDb;
			$commit_id = rtrim(shell_exec('git rev-parse --short=4 HEAD'));
			$commit_time = Time::Tag(date('c',strtotime(shell_exec('git log -1 --date=short --pretty=format:%ci'))));
			return "Running <strong><a href='".GITHUB_URL."' title='Visit the GitHub repository'>MLPVC-RR</a>@<a href='".GITHUB_URL."/commit/$commit_id' title='See exactly what was changed and why'>$commit_id</a></strong> created $commit_time | <a class='issues' href='".GITHUB_URL."/issues' target='_blank'>Known issues</a> | ".(isset($Database)?"<a href='#feedback' class='send-feedback'>Send feedback</a>":"<a href='".GITHUB_URL."/issues/new' target='_blank'>Send feedback</a>").(Permission::Sufficient('developer')?' | Render: '.number_format(microtime(true)-EXEC_START_MICRO, 4).'s | SQL Queries: '.($Database->query_count+($CGDb->query_count??0)):'');
		}

		/**
		 * Returns the HTML code of the navigation in the header
		 *
		 * @return string
		 */
		static function GetNavigation(){
			if (!empty($GLOBALS['NavHTML']))
				return $GLOBALS['NavHTML'];

			global $do;

			// Navigation items
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
				else $NavItems['eps']['subitem'] = $GLOBALS['title'];
			}
			global $color, $Color, $EQG;
			$NavItems['colorguide'] = array("/cg", (!empty($EQG)?'EQG ':'')."$Color Guide");
			if ($do === 'colorguide'){
				global $Tags, $Changes, $Ponies, $Pagination, $Appearance;
				if (!empty($Appearance))
					$NavItems['colorguide']['subitem'] = $Appearance['label'];
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

				$href = "href='{$l['url']}'";
				if ($l['url'][0] === '#')
					$href .= " class='action--".substr($l['url'],1)."'";

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
		static function GetSidebarUsefulLinksList($wrap = true){
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
					$cansee .= 's and above';
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
		 * Retrieve the full size URL for a Sta.sh submission
		 *
		 * @param string $stash_id
		 *
		 * @return null|string
		 */
		static function GetStashFullsizeURL($stash_id){
			$stash_url = "http://sta.sh/$stash_id";
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

			$STASH_DL_LINK_REGEX = '(https?://sta.sh/download/\d+/[a-z\d_]+-d[a-z\d]{6,}\.(?:png|jpe?g|bmp)\?[^"]+)';
			$urlmatch = regex_match(new RegExp('<a\s+class="[^"]*?dev-page-download[^"]*?"\s+href="'.
				$STASH_DL_LINK_REGEX.'"'), $stashpage['response'], $_match);

			if (!$urlmatch)
				return 4;

			$fullsize_url = HTTP::FindRedirectTarget(htmlspecialchars_decode($_match[1]), $stash_url);

			if (empty($fullsize_url))
				return 5;

			global $Database;
			if ($Database->where('id', $stash_id)->where('provider', 'sta.sh')->has('deviation_cache'))
				$Database->where('id', $stash_id)->where('provider', 'sta.sh')->update('deviation_cache', array(
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
				$link = User::GetProfileLink(User::Get($row['reserved_by']), FORMAT_FULL);
				$count = "<strong style='color:rgb(".min(round($row['cnt']/10*255),255).",0,0)'>{$row['cnt']}</strong>";

				$HTML .= "<tr><td>$link</td><td>$count</td></tr>";
			}
			return "$HTML</table>";
		}
	}
