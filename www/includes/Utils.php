<?php

	// Constants
	define('PRINTABLE_ASCII_REGEX','^[ -~]+$');
	define('INVERSE_PRINTABLE_ASCII_REGEX','[^ -~]');
	define('NEWEST_FIRST', 'DESC');
	define('OLDEST_FIRST', 'ASC');
	$HEX_COLOR_PATTERN = new RegExp('^#?([\dA-Fa-f]{6})$','u');

	// Constants to enable/disable returning of wrapper element with markup generators
	define('NOWRAP', false);
	define('WRAP', !NOWRAP);

	/**
	 * Sends replies to AJAX requests in a universal form
	 * $s respresents the request status, a truthy value
	 *  means the request was successful, a falsey value
	 *  means the request failed
	 * $x can be used to attach additional data to the response
	 *
	 * @param string $m
	 * @param bool|int $s
	 * @param array $x
	 */
	define('ERR_DB_FAIL','There was an error while saving to the database');
	function respond($m = 'Insufficent permissions.', $s = false, $x = null){
		header('Content-Type: application/json');
		if ($m === true) $m = array();
		if (is_array($m) && $s == false && empty($x)){
			$m['status'] = true;
			$r = $m;
		}
		else {
			if (strpos($m, ERR_DB_FAIL) !== false){
				global $Database;
				$m = rtrim("$m: ".$Database->getLastError(), ': ');
			}
			$r = array(
				"message" => $m,
				"status" => $s,
			);
		}
		if (!empty($x))
			$r = array_merge($r, $x);
		echo JSON::Encode($r);
		exit;
	}

	# Logging
	$LOG_DESCRIPTION = array(
		'junk' => 'Junk entry',
		'episodes' => 'Episode management',
		'episode_modify' => 'Episode modified',
		'rolechange' => 'User group change',
		'userfetch' => 'Fetch user details',
		'banish' => 'User banished',
		'un-banish' => 'User un-banished',
		'post_lock' => 'Post approved',
		'color_modify' => 'Major appearance update',
		'req_delete' => 'Request deleted',
		'img_update' => 'Post image updated',
	);
	function LogAction($type,$data = null){
		global $Database, $signedIn, $currentUser;
		$central = array('ip' => $_SERVER['REMOTE_ADDR']);

		if (isset($data)){
			foreach ($data as $k => $v)
				if (is_bool($v))
					$data[$k] = $v ? 1 : 0;

			$refid = $Database->insert("log__$type",$data,'entryid');
			if (!$refid){
				trigger_error('Logging failed: '.$Database->getLastError());
				return;
			}
		}

		$central['reftype'] = $type;
		if (!empty($refid))
			$central['refid'] = $refid;
		else if (!empty($data)) return false;

		if ($signedIn)
			$central['initiator'] = $currentUser['id'];
		return !!$Database->insert("log",$central);
	}

	# Make any absolute URL HTTPS
	function makeHttps($url){
		return regex_replace(new RegExp('^(https?:)?//'),'https://',$url);
	}

	# Format log details
	function format_log_details($logtype, $data){
		global $Database, $ROLES_ASSOC;
		$details = array();

		switch ($logtype){
			case "rolechange":
				$target =  $Database->where('id',$data['target'])->getOne('users');

				$details = array(
					array('Target user', profile_link($target)),
					array('Old group',$ROLES_ASSOC[$data['oldrole']]),
					array('New group',$ROLES_ASSOC[$data['newrole']])
				);
			break;
			case "episodes":
				$actions = array('add' => 'create', 'del' => 'delete');
				$details[] = array('Action', $actions[$data['action']]);
				$details[] = array('Name', format_episode_title($data));
				if (!empty($data['airs']))
					$details[] = array('Airs', timetag($data['airs'], EXTENDED, NO_DYNTIME));
			break;
			case "episode_modify":
				$details[] = array('Target episode', $data['target']);

				$newOld = array();
				unset($data['entryid'], $data['target']);
				foreach ($data as $k => $v){
					if (is_null($v)) continue;

					$thing = substr($k, 3);
					$type = substr($k, 0, 3);
					if (!isset($newOld[$thing]))
						$newOld[$thing] = array();
					$newOld[$thing][$type] = $thing === 'twoparter' ? !!$v : $v;
				}

				if (!empty($newOld['airs'])){
					$newOld['airs']['old'] =  timetag($newOld['airs']['old'], EXTENDED, NO_DYNTIME);
					$newOld['airs']['new'] =  timetag($newOld['airs']['new'], EXTENDED, NO_DYNTIME);
				}

				foreach ($newOld as $thing => $ver){
					$details[] = array("Old $thing",$ver['old']);
					$details[] = array("New $thing",$ver['new']);
				}
			break;
			case "userfetch":
				$details[] = array('User', profile_link(get_user($data['userid'])));
			break;
			case "banish":
			case "un-banish":
				$details[] = array('User', profile_link(get_user($data['target'])));
				$details[] = array('Reason', htmlspecialchars($data['reason']));
			break;
			case "post_lock":
				$Post = $Database->where('id', $data['id'])->getOne("{$data['type']}s");
				if (empty($Post))
					$details[] = array('Notice', 'The post has been deleted, some details are unavailable');
				$details[] = array('ID',$data['id']);
				$details[] = array('Type',$data['type']);
				if (!empty($Post)){
					$Fragment = "#{$data['type']}-{$data['id']}";
					if ($Post['season'] == 0){
						$IDstr = "EQG{$Post['episode']}";
						$Link = "/eqg/{$Post['episode']}";
					}
					else{
						$IDstr = "S{$Post['season']}E{$Post['episode']}";
						$Link = "/episode/$IDstr";
					}
					$details[] = array('Link',"<a href='$Link$Fragment'>$IDstr$Fragment</a>");
				}
			break;
			case "color_modify":
				$details[] = array('Appearance',"<a href='/colorguide/appearance/{$data['ponyid']}'>#{$data['ponyid']}</a>");
				$details[] = array('Reason',htmlspecialchars($data['reason']));
			break;
			case "req_delete":
				$details[] = array('Request ID', $data['id']);
				$typeNames = array(
					'chr' => 'Character',
					'obj' => 'Object',
					'bg' => 'Background',
				);
				$details[] = array('Description',$data['label']);
				$details[] = array('Type',$typeNames[$data['type']]);
				$IDstr = "S{$data['season']}E{$data['episode']}";
				$details[] = array('Episode', "<a href='/episode/$IDstr'>$IDstr</a>");
				$details[] = array('Posted', timetag($data['posted'], EXTENDED, NO_DYNTIME));
				if (!empty($data['requested_by']))
					$details[] = array('Requested by', profile_link(get_user($data['requested_by'])));
				if (!empty($data['reserved_by']))
					$details[] = array('Reserved by', profile_link(get_user($data['reserved_by'])));
				$details[] = array('Finished', !empty($data['deviation_id']));
				if (!empty($data['deviation_id'])){
					$DeviationURL = "http://fav.me/{$data['deviation_id']}";
					$details[] = array('Deviation', "<a href='$DeviationURL'>$DeviationURL</a>");

					$details[] = array('Approved', $data['lock']);
				}
			break;
			case "img_update":
				$Post = $Database->where('id', $data['id'])->getOne("{$data['thing']}s");
				if (empty($Post))
					$details[] = array('Notice', 'The post has been deleted, some details are unavailable');
				$details[] = array('Post ID',$data['id']);
				$details[] = array('Type',$data['thing']);
				if (!empty($Post)){
					$IDstr = "S{$Post['season']}E{$Post['episode']}#{$data['thing']}-{$data['id']}";
					$details[] = array('Link',"<a href='/episode/$IDstr'>$IDstr</a>");
				}
				$details[] = array('Old',"<a href='{$data['oldfullsize']}' target='_blank'>Full size</a><div><img src='{$data['oldpreview']}'></div>");
				$details[] = array('New',"<a href='{$data['newfullsize']}' target='_blank'>Full size</a><div><img src='{$data['newpreview']}'></div>");
			break;
			default:
				$details[] = array('Could not process details','No data processor defined for this entry type');
				$details[] = array('Raw details', '<pre>'.var_export($data, true).'</pre>');
			break;
		}

		return array('details' => $details);
	}

	# Render log page <tbody> content
	function log_tbody_render($LogItems){
		global $Database, $LOG_DESCRIPTION;

		$HTML = '';
		if (count($LogItems) > 0) foreach ($LogItems as $item){
			if (!empty($item['initiator'])){
				$inituser = $Database->where('id',$item['initiator'])->getOne('users');
				if (empty($inituser))
					$inituser = 'Deleted user';
				else $inituser = profile_link($inituser);

				$ip = in_array($item['ip'], array('::1', '127.0.0.1')) ? "localhost" : $item['ip'];

				if ($item['ip'] === $_SERVER['REMOTE_ADDR'])
					$ip .= ' <span class="self">(from your IP)</span>';
			}
			else {
				$inituser = null;
				$ip = 'Web server';
			}

			$event = isset($LOG_DESCRIPTION[$item['reftype']]) ? $LOG_DESCRIPTION[$item['reftype']] : $item['reftype'];
			if (isset($item['refid']))
				$event = '<span class="expand-section typcn typcn-plus">'.$event.'</span>';
			$ts = timetag($item['timestamp'], EXTENDED);

			if (!empty($inituser)) $ip = "$inituser<br>$ip";

			$HTML .= <<<HTML
		<tr>
			<td class='entryid'>{$item['entryid']}</td>
			<td class='timestamp'>$ts</td>
			<td class='ip'>$ip</td>
			<td class='reftype'>$event</td>
		</tr>
HTML;
		}
		else $HTML = "<tr><td colspan='4'>There are no log items</td></tr>";

		return $HTML;
	}

	/**
	 * Formats a 1-dimensional array of stings and integers
	 *  to be human-readable
	 *
	 * @param array $list
	 * @param string $append
	 * @param string $separator
	 *
	 * @return string
	 */
	function array_readable($list, $append = 'and', $separator = ','){
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
	
	// Apostrophe HTML encoding for attribute values \\
	function apos_encode($str){
		return str_replace("'", '&apos;', $str);
	}

	// Data for the time functions below
	$TIME_DATA = array(
		'year' =>   31557600,
		'month' =>  2592000,
		'week' =>   604800,
		'day' =>    86400,
		'hour' =>   3600,
		'minute' => 60,
		'second' => 1,
	);

	// Gets the difference between 2 timestamps \\
	function timeDifference($n,$e){
		global $TIME_DATA;
		$substract = $n - $e;
		$d = array(
			'past' => $substract > 0,
			'time' => abs($substract),
			'target' => $e
		);
		$time = $d['time'];
		
		$d['day'] = floor($time/$TIME_DATA['day']);
		$time -= $d['day'] * $TIME_DATA['day'];
		
		$d['hour'] = floor($time/$TIME_DATA['hour']);
		$time -= $d['hour'] * $TIME_DATA['hour'];
		
		$d['minute'] = floor($time/$TIME_DATA['minute']);
		$time -= $d['minute'] * $TIME_DATA['minute'];
		
		$d['second'] = floor($time);
		
		if (!empty($d['day']) && $d['day'] >= 7){
			$d['week'] = floor($d['day']/7);
			$d['day'] -= $d['week']*7;
		}
		if (!empty($d['week']) && $d['week'] >= 4){
			$d['month'] = floor($d['week']/4);
			$d['week'] -= $d['month']*4;
		}
		if (!empty($d['month']) && $d['month'] >= 12){
			$d['year'] = floor($d['month']/12);
			$d['month'] -= $d['year']*12;
		}
		
		return $d;
	}
	
	/**
	 * Converts $timestamp to an "X somthing ago" format
	 * Always uses the greatest unit available
	 *
	 * @param int $timestamp
	 *
	 * @return string
	 */
	function time_ago($timestamp){
		global $TIME_DATA;

		$delta = time() - $timestamp;
		$past = $delta > 0;
		if (!$past) $delta *= -1;

		foreach ($TIME_DATA as $unit => $value){
			if ($delta >= $value){
				$left = floor($delta / $value);
				$delta -= ($left * $value);
				if (!$past && $unit === 'minute')
					$left++;
				$str = $left!=1?"$left {$unit}s":($unit=='hour'?'an':'a')." $unit";
				break;
			}
		}

		if (!isset($str)) return 'just now';

		if ($str == '1 day') return $past ? 'yesterday' : 'tomorrow';
		else return $past ? "$str ago" : "in $str";
	}

	/**
	 * Create an ISO timestamp from the input string
	 *
	 * @param int $time
	 * @param string $format
	 *
	 * @return string
	 */
	define('FORMAT_READABLE',true);
	define('FORMAT_FULL','jS M Y, g:i:s a T');
	function format_timestamp($time, $format = 'c'){
		if ($format === FORMAT_READABLE)
			return time_ago($time);

		$ts = gmdate($format, $time);
		if ($format !== 'c' && strpos($format, 'T') === false)
			$ts .= ' ('.date('T').')';
		return $ts;
	}

	/**
	 * Create <time datetime></time> tag
	 *
	 * @param string|int $timestamp
	 * @param bool $extended
	 * @return string
	 */
	define('EXTENDED', true);
	define('NO_DYNTIME', false);
	function timetag($timestamp, $extended = false, $allowDyntime = true){
		if (is_string($timestamp))
			$timestamp = strtotime($timestamp);
		if ($timestamp === false) return null;

		$datetime = format_timestamp($timestamp);
		$full = format_timestamp($timestamp,FORMAT_FULL);
		$text = format_timestamp($timestamp,FORMAT_READABLE);

		if ($allowDyntime === NO_DYNTIME)
			$datetime .= "' class='nodt";

		return
			!$extended
			? "<time datetime='$datetime' title='$full'>$text</time>"
			:"<time datetime='$datetime'>$full</time>".(
				$allowDyntime !== NO_DYNTIME
				?"<span class='dynt-el'>$text</span>"
				:''
			);
	}

	// Removes excess tabs from HTML
	function remove_indentation($HTML){
		return regex_replace(new RegExp('(\n|\r|\r\n)[\t ]*'), '$1', $HTML);
	}

	// Page loading function
	function loadPage($settings){
		// Page <title>
		if (isset($settings['title']))
			$GLOBALS['title'] = $settings['title'];

		// SE crawlign disable
		if (in_array('no-robots',$settings))
			$norobots = true;

		# CSS
		$DEFAULT_CSS = array('theme');
		$customCSS = array();
		// Only add defaults when needed
		if (array_search('no-default-css',$settings) === false)
			$customCSS = array_merge($customCSS, $DEFAULT_CSS);

		# JavaScript
		$DEFAULT_JS = array('global','moment','dyntime','dialog');
		$customJS = array();
		// Only add defaults when needed
		if (array_search('no-default-js',$settings) === false)
			$customJS = array_merge($customJS, $DEFAULT_JS);

		# Check assests
		assetCheck($settings, $customCSS, 'css');
		assetCheck($settings, $customJS, 'js');

		# Add status code
		if (isset($settings['status-code']))
			statusCodeHeader($settings['status-code']);

		# Import global variables
		foreach ($GLOBALS as $nev => $ertek)
			if (!isset($$nev))
				$$nev = $ertek;

		# Putting it together
		/* Together, we'll always shine! */
		$view = empty($settings['view']) ? $GLOBALS['do'] : $settings['view'];
		$viewPath = "views/{$view}.php";

		header('Content-Type: text/html; charset=utf-8;');

		$pageHeader = array_search('no-page-header',$settings) === false;

		if (empty($_GET['via-js'])){
			ob_start();
			require 'views/header.php';
			require $viewPath;
			require 'views/footer.php';
			$content = ob_get_clean();
			echo remove_indentation($content);
			die();
		}
		else {
			$_SERVER['REQUEST_URI'] = rtrim(str_replace('via-js=true','',remove_csrf_query_parameter($_SERVER['REQUEST_URI'])), '?&');
			ob_start();
			require 'views/sidebar.php';
			$sidebar = ob_get_clean();
			ob_start();
			require $viewPath;
			$content = ob_get_clean();
			respond(array(
				'css' => $customCSS,
				'js' => $customJS,
				'title' => (isset($GLOBALS['title'])?$GLOBALS['title'].' - ':'').SITE_TITLE,
				'content' => remove_indentation($content),
				'sidebar' => remove_indentation($sidebar),
				'footer' => get_footer(),
				'avatar' => $GLOBALS['signedIn'] ? $GLOBALS['currentUser']['avatar_url'] : GUEST_AVATAR,
				'responseURL' => $_SERVER['REQUEST_URI'],
			));
		}
	}
	function assetCheck($settings, &$customType, $type){
		// Any more files?
		if (isset($settings[$type])){
			$$type = $settings[$type];
			if (!is_array($$type))
				$customType[] = $$type;
			else $customType = array_merge($customType, $$type);
			if (array_search("do-$type",$settings) !== false){
				global $do;
				$customType[] = $do;
			}
		}
		else if (array_search("do-$type",$settings) !== false){
			global $do;
			$customType[] = $do;
		}

		$pathStart = APPATH."$type/";
		foreach ($customType as $i => $item){
			if (file_exists("$pathStart$item.min.$type")){
				$customType[$i] = format_filepath("$item.min.$type");
				continue;
			}
			$item .= ".$type";
			if (!file_exists($pathStart.$item)){
				array_splice($customType,$i,1);
				trigger_error("File /$type/$item does not exist");
			}
			else $customType[$i] = format_filepath($item);
		}
	}
	function format_filepath($item){
		$type = regex_replace(new RegExp('^.*\.(\w+)$'),'$1', $item);
		$pathStart = APPATH."$type/";
		return "/$type/$item?".filemtime($pathStart.$item);
	}

	// Remove CSRF query parameter from request URL
	function remove_csrf_query_parameter($url, $viajsToo = false){
		return rtrim(regex_replace(new RegExp('CSRF_TOKEN=[^&]+(&|$)'),'',$url),'?&');
	}

	// Display a 404 page
	function do404($debug = null){
		if (RQMTHD == 'POST' || isset($_GET['via-js'])){
			$RQURI = rtrim(str_replace('via-js=true','',$_SERVER['REQUEST_URI']),'?&');
			respond("HTTP 404: ".(RQMTHD=='POST'?'Endpoint':'Page')." ($RQURI) does not exist",0, is_string($debug) ? array('debug' => $debug) : null);
		}
		statusCodeHeader(404);
		loadPage(array(
			'title' => '404',
			'view' => '404',
		));
	}

	// Time Constants \\
	define('EIGHTY_YEARS',2524556160);
	define('ONE_YEAR',31536000);
	define('THIRTY_DAYS',2592000);
	define('ONE_HOUR',3600);

	// Random Array Element \\
	function array_random($array){ return $array[array_rand($array, 1)]; }

	// Color padder \\
	function clrpad($c){
		if (strlen($c) === 3) $c = $c[0].$c[0].$c[1].$c[1].$c[2].$c[2];
		return $c;
	}

	// Convert HEX to RGB
	function hex2rgb($hex){
		// http://stackoverflow.com/a/15202130/1344955
		return sscanf($hex, "#%02x%02x%02x");
	}

	// Redirection \\
	define('STAY_ALIVE', false);
	function redirect($url = '/', $die = true, $http = 301){
		header("Location: $url",$die,$http);
		if ($die !== STAY_ALIVE) die();
	}

	// Redirect to fix path \\
	function fix_path($fix_uri, $http = 301){
		list($path, $query) = explode('?', "{$_SERVER['REQUEST_URI']}?");
		$query = empty($query) ? '' : "?$query";

		list($fix_path, $fix_query) = explode('?', "$fix_uri?");
		$fix_query = empty($fix_query) ? '' : "?$fix_query";

		if (empty($fix_query))
			$fix_query = $query;
		else {
			$query_assoc = query_string_assoc($query);
			$fix_query_assoc = query_string_assoc($fix_query);
			$merged = $query_assoc;
			foreach ($fix_query_assoc as $key => $item)
				$merged[$key] = $item;

			$fix_query_arr = array();
			foreach ($merged as $key => $item)
				$fix_query_arr[] = "$key".(!empty($item)?"=$item":'');
			$fix_query = implode('&', $fix_query_arr);
			$fix_query = empty($fix_query) ? '' : "?$fix_query";
		}

		if ($path !== $fix_path || $query !== $fix_query)
			redirect("$fix_path$fix_query", STAY_ALIVE, $http);
	}

	// Turn query string into an associative array
	function query_string_assoc($query){
		$assoc = array();
		if (!empty($query)){
			$query_arr = explode('&', substr($query, 1));
			foreach ($query_arr as $el){
				$el = explode('=', $el);
				$assoc[$el[0]] = empty($el[1]) ? '' : $el[1];
			}
		}
		return $assoc;
	}

	/**
	 * Number padder
	 * -------------
	 * Pad a number using $padchar from either side
	 *  to create an $l character long string
	 * If $leftSide is false, padding is done from the right
	 *
	 * @param string|int $str
	 * @param int $l
	 * @param string $padchar
	 * @param bool $leftSide
	 * @return string
	 */
	function pad($str,$l = 2,$padchar = '0', $leftSide = true){
		if (!is_string($str)) $str = strval($str);
		if (strlen($str) < $l){
			do {
				$str = $leftSide ? $padchar.$str : $str.$padchar;
			}
			while (strlen($str) < $l);
		}
		return $str;
	}

	// HTTP Status Codes \\
	$HTTP_STATUS_CODES = array(
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
	define('AND_DIE', true);
	function statusCodeHeader($code, $die = false){
		global $HTTP_STATUS_CODES;

		if (!isset($HTTP_STATUS_CODES[$code]))
			throw new Exception("Unknown status code: $code");

		header("{$_SERVER['SERVER_PROTOCOL']} $code {$HTTP_STATUS_CODES[$code]}");
		if ($die === AND_DIE)
			die();
	}

	// CSRF Check \\
	define('RETURN_AS_BOOL', true);
	function detectCSRF($return_as_bool = false){
		global $CSRF;
		$is_forged = isset($CSRF) && $CSRF;

		if ($return_as_bool === RETURN_AS_BOOL)
			return $is_forged;
		else if ($is_forged)
			die(statusCodeHeader(401));
	}

	// A function to compare a space-separated list of strings
	function compare_scope($scope1, $scope2){
		$scope1 = explode(' ',$scope1);
		$scope2 = explode(' ',$scope2);
		$diff1 = array_diff($scope1,$scope2);
		$diff2 = array_diff($scope2,$scope1);
		return !(count($diff1)+count($diff2));
	}

	// oAuth Error Response Messages \\
	$OAUTH_RESPONSE = array(
		'invalid_request' => 'The authorization recest was not properly formatted.',
		'unsupported_response_type' => 'The authorization server does not support obtaining an authorization code using this method.',
		'unauthorized_client' => 'The authorization process did not complete. Please try again.',
		'invalid_scope' => 'The requested scope is invalid, unknown, or malformed.',
		'server_error' => "There's an issue on DeviantArt's end. Try again later.",
		'temporarily_unavailable' => "There's an issue on DeviantArt's end. Try again later.",
		'user_banned' => 'You were banned on our website by a staff member.',
	);

	// Redirection URI shortcut \\
	function oauth_redirect_uri($state = true){
		global $do, $data;
		if ($do === 'index' && empty($data)) $returnURL = '/';
		else $returnURL = $_SERVER['REQUEST_URI'];
		return '&redirect_uri='.urlencode(ABSPATH."da-auth").($state?'&state='.urlencode($state===true?$returnURL:$state):'');
	}

	// oAuth Authorization page URL generator
	function get_oauth_authorization_url(){
		return "https://www.deviantart.com/oauth2/authorize?response_type=code&scope=user+browse&client_id=".DA_CLIENT.oauth_redirect_uri();
	}

	class cURLRequestException extends Exception {
		public function __construct($errMsg, $errCode){
			$this->message = $errMsg;
			$this->code = $errCode;
		}
	}

	/**
	 * Makes authenticated requests to the DeviantArt API
	 *
	 * @param string $endpoint
	 * @param null|array $postdata
	 * @param null|string $token
	 * @return array
	 */
	function da_request($endpoint, $token = null, $postdata = null){
		global $signedIn, $currentUser, $http_response_header;

		$requestHeaders = array("Accept-Encoding: gzip","User-Agent: MLPVC-RR @ ".GITHUB_URL);
		if (!isset($token) && $signedIn)
			$token = $currentUser['Session']['access'];
		if (!empty($token)) $requestHeaders[] = "Authorization: Bearer $token";
		else if ($token !== false) return null;

		$requestURI  = regex_match(new RegExp('^https?://'), $endpoint) ? $endpoint : "https://www.deviantart.com/api/v1/oauth2/$endpoint";

		$r = curl_init($requestURI);
		$curl_opt = array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_HTTPHEADER => $requestHeaders,
			CURLOPT_HEADER => 1,
			CURLOPT_BINARYTRANSFER => 1,
		);
		if (!empty($postdata)){
			$query = array();
			foreach($postdata as $k => $v) $query[] = urlencode($k).'='.urlencode($v);
			$curl_opt[CURLOPT_POST] = count($postdata);
			$curl_opt[CURLOPT_POSTFIELDS] = implode('&', $query);
		}
		curl_setopt_array($r, $curl_opt);

		$response = curl_exec($r);
		$responseCode = curl_getinfo($r, CURLINFO_HTTP_CODE);
		$headerSize = curl_getinfo($r, CURLINFO_HEADER_SIZE);

		$responseHeaders = rtrim(substr($response, 0, $headerSize));
		$response = substr($response, $headerSize);
		$http_response_header = array_map("rtrim",explode("\n",$responseHeaders));
		$curlError = curl_error($r);
		curl_close($r);

		if ($responseCode < 200 || $responseCode >= 300)
			throw new cURLRequestException(rtrim("cURL fail for URL \"$requestURI\" (HTTP $responseCode); $curlError",' ;'), $responseCode);

		if (regex_match(new RegExp('Content-Encoding:\s?gzip'), $responseHeaders))
			$response = gzdecode($response);
		return JSON::Decode($response, true);
	}

	/**
	 * Requests or refreshes an Access Token
	 * $type defaults to 'authorization_code'
	 *
	 * @param string $code
	 * @param null|string $type
	 */
	function da_get_token($code, $type = null){
		global $Database, $http_response_header;

		if (empty($type) || !in_array($type,array('authorization_code','refresh_token'))) $type = 'authorization_code';
		$URL_Start = 'https://www.deviantart.com/oauth2/token?client_id='.DA_CLIENT.'&client_secret='.DA_SECRET."&grant_type=$type";

		switch ($type){
			case "authorization_code":
				$json = da_request("$URL_Start&code=$code".oauth_redirect_uri(false),false);
			break;
			case "refresh_token":
				$json = da_request("$URL_Start&refresh_token=$code",false);
			break;
		}

		if (empty($json)){
			if (Cookie::exists('access')){
				$Database->where('access', Cookie::get('access'))->delete('sessions');
				Cookie::delete('access');
			}
			redirect("/da-auth?error=server_error&error_description={$http_response_header[0]}");
		}
		if (empty($json['status'])) redirect("/da-auth?error={$json['error']}&error_description={$json['error_description']}");

		$userdata = da_request('user/whoami', $json['access_token']);

		$User = $Database->where('id',$userdata['userid'])->getOne('users');
		if ($User['role'] === 'ban'){
			$_GET['error'] = 'user_banned';
			$BanReason = $Database
				->where('target', $User['id'])
				->orderBy('entryid', 'ASC')
				->getOne('log__banish');
			if (!empty($BanReason))
				$_GET['error_description'] = $BanReason['reason'];

			return;
		}

		$UserID = strtolower($userdata['userid']);
		$UserData = array(
			'name' => $userdata['username'],
			'avatar_url' => makeHttps($userdata['usericon']),
		);
		$AuthData = array(
			'access' => $json['access_token'],
			'refresh' => $json['refresh_token'],
			'expires' => date('c',time()+intval($json['expires_in'])),
			'scope' => $json['scope'],
		);

		$cookie = \Sodium\randombytes_buf(64);
		$AuthData['token'] = sha1($cookie);

		add_browser($AuthData);
		if (empty($User)){
			$MoreInfo = array(
				'id' => $UserID,
				'role' => 'user',
			);
			$makeDev = !$Database->has('users');
			if ($makeDev){
				$MoreInfo['id'] = strtoupper($MoreInfo['id']);

				if (!$Database->has('roles')){
					$STATIC_ROLES = array(
						array(0, 'ban', 'Banished User'),
						array(1, 'user', 'DeviantArt User'),
						array(2, 'member', 'Club Member'),
						array(3, 'inspector', 'Vector Inspector'),
						array(4, 'manager', 'Group Manager'),
						array(255, 'developer', 'Site Developer'),
					);
					foreach ($STATIC_ROLES as $role)
						$Database->insert('roles', array(
							'value' => $role[0],
							'name' => $role[1],
							'label' => $role[2],
						));
				}
			}
			$Insert = array_merge($UserData, $MoreInfo);
			$Database->insert('users', $Insert);
			if ($makeDev)
				update_role($Insert, 'developer');
		}
		else $Database->where('id',$UserID)->update('users', $UserData);

		if (empty($makeDev) && (!empty($User) && !PERM('member', $User['role']) || empty($User)) && is_user_in_vectorclub($UserData['name']))
			update_role(array(
				'id' => $UserID,
				'role' => isset($User['role']) ? $User['role'] : 'user',
			), 'member');

		if ($type === 'refresh_token')
			$Database->where('refresh', $code)->update('sessions',$AuthData);
		else {
			$Database->where('user', $User['id'])->where('scope', $AuthData['scope'], '!=')->delete('sessions');
			$Database->insert('sessions', array_merge($AuthData, array('user' => $UserID)));
		}

		$Database->rawQuery(
			"DELETE FROM sessions
			WHERE \"user\" = ? && (scope != ? OR lastvisit <= NOW() - INTERVAL '2 MONTH')", array($UserID,$AuthData['scope']));


		Cookie::set('access', $cookie, ONE_YEAR);
	}

	function da_handle_auth(){
		global $err, $errdesc, $REWRITE_REGEX;

		if (!isset($_GET['error']) && (empty($_GET['code']) || (empty($_GET['state']) || !regex_match($REWRITE_REGEX, strtok($_GET['state'],'?')))))
			$_GET['error'] = 'unauthorized_client';
		if (isset($_GET['error'])){
			$err = $_GET['error'];
			if (isset($_GET['error_description']))
				$errdesc = $_GET['error_description'];
			global $signedIn;
			if ($signedIn)
				redirect($_GET['state']);
			loadEpisodePage();
		}
		da_get_token($_GET['code']);

		if (isset($_GET['error'])){
			$err = $_GET['error'];
			if (isset($_GET['error_description']))
				$errdesc = $_GET['error_description'];

			if ($err === 'user_banned')
				$errdesc .= "\n\nIf you'd like to appeal your ban, please <a href='http://mlp-vectorclub.deviantart.com/notes/'>send the group a note</a>.";
			loadEpisodePage();
		}

		redirect($_GET['state']);
	}

	/**
	 * Checks if a deviation is in the group
	 *
	 * @param int|string $DeviationID
	 *
	 * @return bool
	 */
	function is_deviation_in_vectorclub($DeviationID){
		if (!is_int($DeviationID))
			$DeviationID = intval(substr($DeviationID, 1), 36);

		$DiFiRequest = @file_get_contents("http://deviantart.com/global/difi/?c[]=\"DeviationView\",\"getAllGroups\",[\"$DeviationID\"]&t=json");
		if (empty($DiFiRequest))
			return 1;

		$DiFiRequest = @JSON::Decode($DiFiRequest, JSON::$AsObject);
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
	 * Checks if a user is a club member
	 * (currently only works for recently added members, does not deal with old members or admins)
	 *
	 * @param int|string $Username
	 *
	 * @return bool
	 */
	function is_user_in_vectorclub($Username){
		$RecentlyJoined = @file_get_contents('http://mlp-vectorclub.deviantart.com/modals/memberlist/');

		return !empty($RecentlyJoined) && regex_match(new RegExp('<a class="[a-z ]*username" href="http://'.strtolower($Username).'.deviantart.com/">'.USERNAME_PATTERN.'</a>'), $RecentlyJoined);
	}

	function legitimate_request($url, $cookies = null, $referrer = null){
		$r = curl_init();
		$curl_opt = array(
			CURLOPT_HTTPHEADER => array(
				"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
				"Accept-Encoding: gzip, deflate, sdch",
				"Accept-Language: hu,en-GB;q=0.8,en;q=0.6",
				"Connection: keep-alive",
			),
			CURLOPT_HEADER => true,
			CURLOPT_URL => $url,
			CURLOPT_BINARYTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.75 Safari/537.36"
		);
		if (isset($referrer))
			$curl_opt[CURLOPT_REFERER] = $referrer;
		if (is_array($cookies))
			$curl_opt[CURLOPT_COOKIE] = implode('; ', $cookies);
		curl_setopt_array($r, $curl_opt);

		$response = curl_exec($r);
		$responseCode = curl_getinfo($r, CURLINFO_HTTP_CODE);
		$headerSize = curl_getinfo($r, CURLINFO_HEADER_SIZE);

		$responseHeaders = rtrim(substr($response, 0, $headerSize));
		$response = substr($response, $headerSize);
		$curlError = curl_error($r);
		curl_close($r);

		if ($responseCode < 200 || $responseCode >= 300)
			throw new cURLRequestException(rtrim("cURL fail for URL \"$url\" (HTTP $responseCode); $curlError",' ;'), $responseCode);

		global $http_response_header;
		$http_response_header = array_map("rtrim",explode("\n",$responseHeaders));

		if (regex_match(new RegExp('Content-Encoding:\s?gzip'), $responseHeaders))
			$response = gzdecode($response);
		return array(
			'responseHeaders' => $responseHeaders,
			'response' => $response,
		);
	}

	function redirects_where($url, $referrer = null){
		global $http_response_header;

		$cookies = array();
		if (!empty($http_response_header))
			foreach ($http_response_header as $header){
				if (!regex_match(new RegExp('^([^:]+): (.*)$'), $header, $parts) || $parts[1] !== 'Set-Cookie')
					continue;

				regex_match(new RegExp('\s*([^=]+=[^;]+)(?:;|$)'), $parts[2], $cookie);
				$cookies[] = $cookie[1];
			};

		$request = legitimate_request($url, $cookies, $referrer);
		return regex_match(new RegExp('Location:\s+([^\r\n]+)'), $request['responseHeaders'], $_match) ? trim($_match[1]) : null;
	}

	/**
	 * Adds browser info to $Authdata
	 *
	 * @param string|null $user_agent User-Agent string to check
	 *
	 * @return array
	 */
	function browser($user_agent = null){
		require_once "includes/Browser.php";
		$Return = array('user_agent' => !empty($user_agent) ? $user_agent : $_SERVER['HTTP_USER_AGENT']);
		$browser = new Browser($Return['user_agent']);
		$name = $browser->getBrowser();
		if ($name !== Browser::BROWSER_UNKNOWN){
			$Return['browser_name'] = $name;

			$ver = $browser->getVersion();
			if ($ver !== Browser::VERSION_UNKNOWN)
				$Return['browser_ver'] = $ver;
		}
		$platform = $browser->getPlatform();
		if ($platform !== Browser::PLATFORM_UNKNOWN)
			$Return['platform'] = $platform;
		return $Return;
	}
	function add_browser(&$AuthData){
		$browser = browser();
		foreach (array_keys($browser) as $v)
			if (isset($browser[$v]))
				$AuthData[$v] = $browser[$v];
	}

	/**
	 * Makes a call to the dA oEmbed API to get public info about an artwork
	 * $type defaults to 'fav.me'
	 *
	 * @param string $ID
	 * @param null|string $type
	 * @return string
	 */
	function da_oembed($ID, $type){
		if (empty($type) || !in_array($type,array('fav.me','sta.sh'))) $type = 'fav.me';

		if ($type === 'sta.sh' && strlen($ID) !== 11)
			$ID = str_pad($ID, 11, '0', STR_PAD_LEFT);
		try {
			$data = da_request('http://backend.deviantart.com/oembed?url='.urlencode("http://$type/$ID"),false);
		}
		catch (cURLRequestException $e){
			if ($e->getCode() == 404)
				throw new Exception("Image not found. The URL may be incorrect or the image has been deleted.");
			else throw new Exception("Image could not be retrieved (HTTP {$e->getCode()})");
		}

		return $data;
	}

	// Prevents running any more caching requests when set to true
	$CACHE_BAILOUT = false;
	$MASS_CACHE_LIMIT = 5;
	$MASS_CACHE_USED = 0;

	/**
	 * Caches information about a deviation in the 'deviation_cache' table
	 * Returns null on failure
	 *
	 * @param string $ID
	 * @param null|string $type
	 * @param bool $mass
	 * @return array|null
	 */
	function da_cache_deviation($ID, $type = 'fav.me', $mass = false){
		global $Database, $CACHE_BAILOUT, $MASS_CACHE_USED, $MASS_CACHE_LIMIT;

		if ($type === 'sta.sh')
			$ID = stash_id_normalize($ID);

		$Deviation = $Database->where('id',$ID)->getOne('deviation_cache');
		if (!$CACHE_BAILOUT && (empty($Deviation) && $MASS_CACHE_USED <= $MASS_CACHE_LIMIT) || (!empty($Deviation['updated_on']) && strtotime($Deviation['updated_on'])+(ONE_HOUR*5) < time())){
			try {
				$json = da_oembed($ID, $type);
			}
			catch (Exception $e){
				if (!empty($Deviation))
					$Database->where('id',$Deviation['id'])->update('deviation_cache', array('updated_on' => date('c',strtotime('+1 minute', time()))));

				$ErrorMSG = "Saving local data for $ID@$type failed: ".$e->getMessage();
				if (!PERM('developer')) trigger_error($ErrorMSG);

				if (RQMTHD === 'POST')
					respond($ErrorMSG);
				else echo "<div class='notice fail'><label>da_cache_deviation($ID, $type)</label><p>$ErrorMSG</p></div>";

				$CACHE_BAILOUT = true;
				return $Deviation;
			}

			require_once 'Image.php';

			$insert = array(
				'title' => $json['title'],
				'preview' => makeHttps($json['thumbnail_url']),
				'fullsize' => makeHttps(isset($json['fullsize_url']) ? $json['fullsize_url'] : $json['url']),
				'provider' => $type,
				'author' => $json['author_name'],
				'updated_on' => date('c'),
			);

			if ($type === 'sta.sh'){
				$fullsize_attempt = get_fullsize_stash_url($ID);
				if (!empty($fullsize_attempt))
					$Result['fullsize'] = $fullsize_attempt;
			}

			if (empty($Deviation)){
				$insert['id'] = $ID;
				$Database->insert('deviation_cache', $insert);
			}
			else {
				$Database->where('id',$Deviation['id'])->update('deviation_cache', $insert);
				$insert['id'] = $ID;
			}

			$MASS_CACHE_USED++;
			$Deviation = $insert;
		}
		else if (!empty($Deviation['updated_on'])){
			$Deviation['updated_on'] = date('c', strtotime($Deviation['updated_on']));
			if ($CACHE_BAILOUT)
				$Database->where('id',$Deviation['id'])->update('deviation_cache', array(
					'updated_on' => $Deviation['updated_on'],
				));
		}

		return $Deviation;
	}

	function get_fullsize_stash_url($stash_id){
		$stash_url = "http://sta.sh/$stash_id";
		try {
			$stashpage = legitimate_request($stash_url,null,null);
		}
		catch (Exception $e){ return null; }
		if (empty($stashpage))
			return null;

		$STASH_DL_LINK_REGEX = '(https?://sta.sh/download/\d+/[a-z\d_]+-d[a-z\d]{6,}\.(?:png|jpe?g|bmp)\?[^"]+)';
		$urlmatch = regex_match(new RegExp('<a\s+class="[^"]*?dev-page-download[^"]*?"\s+href="'.
			$STASH_DL_LINK_REGEX.'"'), $stashpage['response'], $_match);

		if (!$urlmatch)
			return null;

		$fullsize_url = redirects_where(htmlspecialchars_decode($_match[1]), $stash_url);

		return !empty($fullsize_url) ? makeHttps($fullsize_url) : null;
	}

	# Get Roles from DB
	$ROLES_ASSOC = array('guest' => 0);
	$ROLES = array('guest');
	if (!empty($Database)){
		foreach ($Database->orderBy('value', 'ASC')->get('roles') as $r){
			$ROLES_ASSOC[$r['name']] = $r['label'];
			$ROLES[] = $r['name'];
		}
	}

	/**
	 * Permission checking function
	 * ----------------------------
	 * Compares the currenlty logged in user's role to the one specified
	 * A "true" retun value means that the user meets the required role or surpasses it.
	 * If user isn't logged in, and $compareAgainst is missing, returns false
	 * If $compareAgainst isn't missing, compare it to $role
	 *
	 * @param string $role
	 * @param string|null $compareAgainst
	 *
	 * @return bool
	 */
	function PERM($role, $compareAgainst = null){
		if (!is_string($role)) return false;

		if (empty($compareAgainst)){
			global $signedIn, $currentUser;
			if (!$signedIn)
				return false;
			$checkRole = $currentUser['role'];
		}
		else $checkRole = $compareAgainst;

		global $ROLES;
		if (!in_array($role,$ROLES))
			trigger_error('Invalid role: '.$role, E_USER_ERROR);
		$targetRole = $role;

		return array_search($checkRole,$ROLES) >= array_search($targetRole,$ROLES);
	}

	// Episode title matching pattern \\
	$EP_TITLE_REGEX = new RegExp('^[A-Za-z \'\-!\d,&:?]{5,35}$','u');

	/**
	 * Turns an 'episode' database row into a readable title
	 *
	 * @param array $Ep
	 * @return string
	 */
	define('AS_ARRAY',true);
	function format_episode_title($Ep, $returnArray = false, $arrayKey = null){
		$EpNumber = intval($Ep['episode']);

		if ($returnArray === AS_ARRAY) {
			if ($Ep['twoparter'])
				$Ep['episode'] = $EpNumber.'-'.($EpNumber+1);
			$arr = array(
				'id' => "S{$Ep['season']}E{$Ep['episode']}",
				'season' => $Ep['season'],
				'episode' => $Ep['episode'],
				'title' => $Ep['title'],
			);

			if (!empty($arrayKey))
				return isset($arr[$arrayKey]) ? $arr[$arrayKey] : null;
			else return $arr;
		}

		if ($Ep['season'] == 0)
			return "Equestria Girls: {$Ep['title']}";

		if ($Ep['twoparter'])
			$Ep['episode'] = pad($EpNumber).'-'.pad($EpNumber+1);
		else $Ep['episode'] = pad($Ep['episode']);
		$Ep['season'] = pad($Ep['season']);
		return "S{$Ep['season']} E{$Ep['episode']}: {$Ep['title']}";
	}

	/**
	 * Extracts the season and episode from the episode id
	 * Examples:
	 *   "S1E1" => {season:1,episode:1}
	 *   "S01E01" => {season:1,episode:1}
	 *   "S1E1-2" => {season:1,episode:1,twoparter:true}
	 *   "S01E01-02" => {season:1,episode:1,twoparter:true}
	 *
	 * @param string $id
	 * @return null|array
	 */
	define('EPISODE_ID_PATTERN','S0*([0-8])E0*([1-9]|1\d|2[0-6])(?:-0*([1-9]|1\d|2[0-6]))?(?:\D|$)');
	$EPISODE_ID_REGEX = new RegExp('^'.EPISODE_ID_PATTERN,'i');
	function episode_id_parse($id){
		global $EPISODE_ID_REGEX;
		if (regex_match($EPISODE_ID_REGEX, $id, $match))
			return array(
				'season' => intval($match[1]),
				'episode' => intval($match[2]),
				'twoparter' => !empty($match[3]),
			);
		else return null;
	}

	/**
	 * User Information Fetching
	 * -------------------------
	 * Fetch user info from dA upon request to nonexistant user
	 *
	 * @param string $username
	 * @param string $dbcols
	 * @return array|null
	 */
	define('USERNAME_PATTERN', '([A-Za-z\-\d]{1,20})');
	$USERNAME_REGEX = new RegExp('^'.USERNAME_PATTERN.'$');
	function fetch_user($username, $dbcols = null){
		global $Database,$USERNAME_REGEX;

		if (!$USERNAME_REGEX->match($username))
			return null;

		try {
			$userdata = da_request('user/whois', null, array('usernames[0]' => $username));
		}
		catch (cURLRequestException $e){
			return false;
		}

		if (empty($userdata['results'][0]))
			return null;

		$userdata = $userdata['results'][0];
		$ID = strtolower($userdata['userid']);

		$userExists = $Database->where('id', $ID)->has('users');

		$insert = array(
			'name' => $userdata['username'],
			'avatar_url' => makeHttps($userdata['usericon']),
		);
		if (!$userExists)
			$insert['id'] = $ID;

		if (!($userExists ? $Database->where('id', $ID)->update('users', $insert) : $Database->insert('users',$insert)))
			throw new Exception('Saving user data failed'.(PERM('developer')?': '.$Database->getLastError():''));

		if (!$userExists)
			LogAction('userfetch',array('userid' => $insert['id']));

		return get_user($insert['name'], 'name', $dbcols);
	}

	// Global cache for storing user details
	$_USER_CACHE = array();

	/**
	 * User Information Retriever
	 * --------------------------
	 * Gets a single row from the 'users' database
	 *  where $coloumn is equal to $value
	 * Returns null if user is not found
	 *
	 * If $cols is set, only specified coloumns
	 *  will be fetched
	 *
	 * @param string $value
	 * @param string $coloumn
	 * @param string $dbcols
	 *
	 * @return array|null
	 */
	function get_user($value, $coloumn = 'id', $dbcols = null){
		global $Database, $_USER_CACHE;

		if ($coloumn === "token"){
			$Auth = $Database->where('token', $value)->getOne('sessions');

			if (empty($Auth)) return null;
			$coloumn = 'id';
			$value = $Auth['user'];
		}

		if ($coloumn === 'id' && isset($_USER_CACHE[$value]))
			return $_USER_CACHE[$value];

		if (empty($dbcols)){
			$User = $Database->rawQuerySingle(
				"SELECT
					users.*,
					roles.label as rolelabel
				FROM users
				LEFT JOIN roles ON roles.name = users.role
				WHERE users.$coloumn = ?",array($value));
		}
		else $User = $Database->where($coloumn, $value)->getOne('users',$dbcols);

		if (empty($User) && $coloumn === 'name')
			$User = fetch_user($value, $dbcols);

		if (empty($dbcols) && !empty($User) && isset($Auth))
			$User['Session'] = $Auth;

		if (isset($User['id']))
			$_USER_CACHE[$User['id']] = $User;

		return $User;
	}

	// Update user's role
	function update_role($targetUser, $newgroup){
		global $Database;
		$response = $Database->where('id', $targetUser['id'])->update('users',array('role' => $newgroup));

		if ($response) LogAction('rolechange',array(
			'target' => $targetUser['id'],
			'oldrole' => $targetUser['role'],
			'newrole' => $newgroup
		));

		return $response;
	}

	/**
	 * DeviantArt profile link generator
	 *
	 * @param array $User
	 * @param int $format
	 *
	 * @return string
	 */
	define('FULL', 0);
	define('TEXT_ONLY', 1);
	define('LINK_ONLY', 2);
	function da_link($User, $format = FULL){
		if (!is_array($User)){
			trigger_error('$User is not an array');
			if (PERM('developer'))
				var_dump($User);
		}

		$Username = $User['name'];
		$username = strtolower($Username);
		$avatar = $format == FULL ? "<img src='{$User['avatar_url']}' class='avatar' alt='avatar'> " : '';
		$link = "http://$username.deviantart.com/";

		if ($format === LINK_ONLY) return $link;
		return "<a href='$link' class='da-userlink'>$avatar<span class='name'>$Username</span></a>";
	}

	/**
	 * Local profile link generator
	 *
	 * @param array $User
	 * @param int $format
	 *
	 * @return string
	 */
	function profile_link($User, $format = TEXT_ONLY){
		if (!is_array($User)){
			trigger_error('$User is not an array');
			if (PERM('developer'))
				var_dump($User);
		}

		$Username = $User['name'];
		$avatar = $format == FULL ? "<img src='{$User['avatar_url']}' class='avatar' alt='avatar'> " : '';

		return "<a href='/@$Username' class='da-userlink".($format == FULL ? ' with-avatar':'')."'>$avatar<span class='name'>$Username</span></a>";
	}

	// Reserved by section creator \\
	function get_reserver_button($By = null, $R = false, $isRequest = false){
		global $signedIn, $currentUser;

		$sameUser = $signedIn && $By['id'] === $currentUser['id'];
		$CanEdit = (empty($R['lock']) && PERM('inspector')) || PERM('developer');
		$Buttons = array();

		if (is_array($R) && empty($R['reserved_by'])) $HTML = PERM('member') ? "<button class='reserve-request typcn typcn-user-add'>Reserve</button>" : '';
		else {
			if (empty($By) || $By === true){
				if (!$signedIn) trigger_error('Trying to get reserver button while not signed in');
				$By = $currentUser;
			}
			$dAlink = profile_link($By, FULL);

			$HTML =  "<div class='reserver'>$dAlink</div>";

			$finished = !empty($R['deviation_id']);
			$inspectorOrSameUser = ($sameUser && PERM('member')) || PERM('inspector');
			if (!$finished && $inspectorOrSameUser){
				$Buttons[] = array('user-delete red cancel', 'Cancel Reservation');
				$Buttons[] = array('attachment green finish', ($sameUser ? "I'm" : 'Mark as').' finished');
			}
			if ($finished && empty($R['lock'])){
				if (PERM('inspector'))
					$Buttons[] = array((empty($R['preview'])?'trash delete-only red':'media-eject orange').' unfinish',empty($R['preview'])?'Delete':'Un-finish');
				if ($inspectorOrSameUser)
					$Buttons[] = array('tick green check','Check');
			}
		}

		if (empty($R['lock']) && empty($Buttons) && (PERM('inspector') || (empty($R['reserved_by']) && $isRequest && $signedIn && $R['requested_by'] === $currentUser['id'])))
			$Buttons[] = array('trash red delete','Delete');
		if ($CanEdit)
			array_splice($Buttons,0,0,array(array('pencil darkblue edit','Edit')));

		$HTML .= "<div class='actions'>";
		if (!empty($Buttons)){
			$regularButton = count($Buttons) <3;
			foreach ($Buttons as $b){
				$WriteOut = "'".($regularButton ? ">{$b[1]}" : " title='".apos_encode($b[1])."'>");
				$HTML .= "<button class='typcn typcn-{$b[0]}$WriteOut</button> ";
			}
		}
		$HTML .= '</div>';

		return $HTML;
	}

	// List ltem generator function for request & reservation renderers \\
	define('IS_REQUEST', true);
	function get_r_li($R, $isRequest = false){
		$finished = !empty($R['deviation_id']);
		$ID = ($isRequest ? 'request' : 'reservation').'-'.$R['id'];
		$alt = !empty($R['label']) ? apos_encode($R['label']) : '';
		$Image = "<div class='image screencap'><a href='{$R['fullsize']}'><img src='{$R['preview']}' alt='$alt'></a></div>";
		$post_label = !empty($R['label']) ? '<span class="label">'.htmlspecialchars($R['label']).'</span>' : '';
		$permalink = "<a href='#$ID'>".timetag($R['posted']).'</a>';

		$posted_at = '<em class="post-date">';
		if ($isRequest){
			global $signedIn, $currentUser;
			$sameUser = $signedIn && $R['requested_by'] === $currentUser['id'];

			$posted_at .= "Requested $permalink";
			if (PERM('inspector') || $sameUser)
				$posted_at .= ' by '.($sameUser ? 'You' : profile_link(get_user($R['requested_by'])));
		}
		else $posted_at .= "Reserved $permalink";
		$posted_at .= "</em>";

		$R['reserver'] = false;
		if (!empty($R['reserved_by'])){
			$R['reserver'] = get_user($R['reserved_by']);
			$reserved_at = $isRequest && !empty($R['reserved_at']) ? "<em class='reserve-date'>Reserved <a href='#$ID'>".timetag($R['reserved_at'])."</a></em>" : '';
			if ($finished){
				$Deviation = da_cache_deviation($R['deviation_id'],'fav.me',true);
				if (empty($Deviation))
					$Image = "<div class='image deviation error'><a href='http://fav.me/{$R['deviation_id']}'>Preview unavailable<br><small>Click to view</small></a></div>";
				else {
					$alt = apos_encode($Deviation['title']);
					$Image = "<div class='image deviation'><a href='http://fav.me/{$Deviation['id']}'><img src='{$Deviation['preview']}' alt='$alt'>";
					if (!empty($R['lock']))
						$Image .= "<span class='typcn typcn-tick' title='This submission has been accepted into the group gallery'></span>";
					$Image .= "</a></div>";
				}
				if (PERM('inspector')){
					$Image .= $post_label.$posted_at.$reserved_at;
					if (!empty($R['fullsize']))
						$Image .= "<a href='{$R['fullsize']}' class='original' target='_blank'>Direct link to original image</a>";
				}
			}
			else $Image .= $post_label.$posted_at.$reserved_at;
		}
		else $Image .= $post_label.$posted_at;

		return "<li id='$ID'>".$Image.get_reserver_button($R['reserver'], $R, $isRequest).'</li>';
	}

	// Get Request / Reservation Submission Form HTML \\
	function get_post_form($type){
		global $currentUser;

		$Type = strtoupper($type[0]).substr($type,1);
		$optional = $type === 'reservation' ? 'optional, ' : '';
		$optreq = $type === 'reservation' ? '' : 'required';

		$HTML = <<<HTML

		<form class="hidden post-form" data-type="$type">
			<h2>Make a $type</h2>
			<div>
				<label>
					<span>$Type description ({$optional}3-255 chars)</span>
					<input type="text" name="label" pattern="^.{3,255}$" maxlength="255" $optreq>
				</label>
				<label>
					<span>Image URL</span>
					<input type="text" name="image_url" pattern="^.{2,255}$" required>&nbsp;
					<button type="button" class="check-img red typcn typcn-arrow-repeat">Check image</button><br>
				</label>
				<div class="img-preview">
					<div class="notice info">
						<p>Please click the <strong>Check image</strong> button after providing an URL to get a preview & verify if the link is correct.</p>
						<hr>
						<p class="keep">You can use a link from any of the following providers: <a href="http://sta.sh/" target="_blank">Sta.sh</a>, <a href="http://deviantart.com/" target="_blank">DeviantArt</a>, <a href="http://imgur.com/" target="_blank">Imgur</a>, <a href="http://derpibooru.org/" target="_blank">Derpibooru</a>, <a href="http://puush.me/" target="_blank">Puush</a>, <a href="http://app.prntscr.com/" target="_blank">LightShot</a></p>
					</div>
				</div>

HTML;
			if ($type === 'request')
				$HTML .= <<<HTML
				<label>
					<span>$Type type</span>
					<select name="type" required>
						<option value="" style="display:none" selected>Choose one</option>
						<optgroup label="$Type types">
							<option value="chr">Character</option>
							<option value="bg">Background</option>
							<option value="obj">Object</option>
						</optgroup>
					</select>
				</label>

HTML;
			if (PERM('developer')){
				$UNP = USERNAME_PATTERN;
				$HTML .= <<<HTML
				<label>
					<span>$Type as user</span>
					<input type="text" name="post_as" pattern="^$UNP$" maxlength="20" placeholder="Username" spellcheck="false">
				</label>

HTML;
			}

			$HTML .= <<<HTML
			</div>
			<button class="green">Submit $type</button> <button type="reset">Cancel</button>
		</form>
HTML;
			return $HTML;
	}

	// Render Reservation HTML\\
	define('RETURN_ARRANGED', true);
	function reservations_render($Reservations, $returnArranged = false){
		$Arranged = array();
		$Arranged['unfinished'] =
		$Arranged['finished'] = !$returnArranged ? '' : array();

		if (!empty($Reservations) && is_array($Reservations)){
			foreach ($Reservations as $R){
				$k = (empty($R['finished'])?'un':'').'finished';
				if (!$returnArranged)
					$Arranged[$k] .= get_r_li($R);
				else $Arranged[$k][] = $R;
			}
		}

		if ($returnArranged) return $Arranged;

		if (PERM('member')){
			$makeRes = '<button id="reservation-btn" class="green">Make a reservation</button>';
			$resForm = get_post_form('reservation');

		}
		else $resForm = $makeRes = '';
		$addRes = PERM('inspector') ? '<button id="add-reservation-btn" class="darkblue">Add a reservation</button>' :'';

		return <<<HTML
	<section id="reservations">
		<div class="unfinished">
			<h2>List of Reservations$makeRes</h2>
			<ul>{$Arranged['unfinished']}</ul>
		</div>
		<div class="finished">
			<h2>Finished Reservations$addRes</h2>
			<ul>{$Arranged['finished']}</ul>
		</div>$resForm
	</section>

HTML;
	}

	// Render Requests HTML \\
	$REQUEST_TYPES = array(
		'chr' => 'Characters',
		'obj' => 'Objects',
		'bg' => 'Backgrounds',
	);
	function requests_render($Requests, $returnArranged = false){
		global $REQUEST_TYPES;

		$Arranged = array('finished' => !$returnArranged ? '' : array());
		if (!$returnArranged){
			$Arranged['unfinished'] = array();
			$Arranged['unfinished']['bg'] =
			$Arranged['unfinished']['obj'] =
			$Arranged['unfinished']['chr'] = $Arranged['finished'];
		}
		else $Arranged['unfinished'] = $Arranged['finished'];
		if (!empty($Requests) && is_array($Requests)){
			foreach ($Requests as $R){
				$HTML = !$returnArranged ? get_r_li($R, IS_REQUEST) : $R;

				if (!$returnArranged){
					if (!empty($R['finished']))
						$Arranged['finished'] .= $HTML;
					else $Arranged['unfinished'][$R['type']] .= $HTML;
				}
				else {
					$k = (empty($R['finished'])?'un':'').'finished';
					$Arranged[$k][] = $HTML;
				}
			}
		}
		if ($returnArranged) return $Arranged;

		$Groups = '';
		foreach ($Arranged['unfinished'] as $g => $c)
			$Groups .= "<div class='group' id='group-$g'><h3>{$REQUEST_TYPES[$g]}:</h3><ul>{$c}</ul></div>";

		if (PERM('user')){
			$makeRq = '<button id="request-btn" class="green">Make a request</button>';
			$reqForm = get_post_form('request');
		}
		else $reqForm = $makeRq = '';
		
		return <<<HTML
	<section id="requests">
		<div class="unfinished">
			<h2>List of Requests$makeRq</h2>
			$Groups
		</div>
		<div class="finished">
			<h2>Finished Requests</h2>
			<ul>{$Arranged['finished']}</ul>
		</div>$reqForm
	</section>

HTML;
	}

	/**
	 * Retrieves requests & reservations for the episode specified
	 *
	 * @param int $season
	 * @param int $episode
	 * @return array
	 */
	define('ONLY_REQUESTS', 1);
	define('ONLY_RESERVATIONS', 2);
	function get_posts($season, $episode, $only = false){
		global $Database;

		$Query =
			'SELECT
				*,
				(CASE
					WHEN (r.deviation_id IS NOT NULL && r.reserved_by IS NOT NULL)
					THEN 1
					ELSE 0
				END) as finished
			FROM "coloumn" r
			WHERE season = ? && episode = ?
			ORDER BY finished, posted';

		$return = array();
		if ($only !== ONLY_RESERVATIONS) $return[] = $Database->rawQuery(str_ireplace('coloumn','requests',$Query),array($season, $episode));
		if ($only !== ONLY_REQUESTS) $return[] = $Database->rawQuery(str_ireplace('coloumn','reservations',$Query),array($season, $episode));

		if (!$only) return $return;
		else return $return[0];
	}

	// Renders the entire sidebar "Useful links" section \\
	function sidebar_links_render(){
		global $Database, $signedIn, $currentUser;
		if (!PERM('user')) return;
		$Links = $Database->orderBy('"order"','ASC')->get('usefullinks');

		$Render = array();
		foreach ($Links as $l){
			if (!PERM($l['minrole'])) continue;

			if (!empty($l['title'])){
				$title = str_replace("'",'&apos;',$l['title']);
				$title = "title='$title'";
			}
			else $title = '';
			$Render[] =  "<li id='s-ufl-{$l['id']}'><a href='{$l['url']}' $title>{$l['label']}</a></li>";
		}
		if (!empty($Render))
			echo '<ul class="links">'.implode('',$Render).'</ul>';
	}
	
	// Renders the user card \\
	define('GUEST_AVATAR','/img/guest.svg');
	function usercard_render($sidebar = false){
		global $signedIn, $currentUser;
		if ($signedIn){
			$avatar = $currentUser['avatar_url'];
			$username = profile_link($currentUser);
			$rolelabel = $currentUser['rolelabel'];
			$Avatar = $sidebar ? '' : get_avatar_wrap($currentUser);
		}
		else {
			$avatar = GUEST_AVATAR;
			$username = 'Curious Pony';
			$rolelabel = 'Guest';
			$Avatar = $sidebar ? '' : get_avatar_wrap(array(
				'avatar_url' => $avatar,
				'name' => $username,
				'rolelabel' => $rolelabel,
				'guest' => true,
			));
		}

		echo <<<HTML
		<div class='usercard'>
			$Avatar
			<span class="un">$username</span>
			<span class="role">$rolelabel</span>
		</div>
HTML;
	}

	/**
	 * Converts role label to badge initials
	 * -------------------------------------
	 * Related: http://stackoverflow.com/a/30740511/1344955
	 *
	 * @param string $label
	 *
	 * @return string
	 */
	function label_to_initials($label){
		return regex_replace(new RegExp('(?:^|\s)([A-Z])|.'),'$1',$label);
	}

	// Renders avatar wrapper for a specific user \\
	function get_avatar_wrap($User){
		$badge = '';
		if (empty($User['guest']))
			$badge = "<span class='badge'>".label_to_initials($User['rolelabel'])."</span>";
		return "<div class='avatar-wrap'><img src='{$User['avatar_url']}' class='avatar' alt='avatar'>$badge</div>";
	}

	/**
	 * Adds airing-.related information to an episodes table row
	 *
	 * @param array $Episode
	 *
	 * @return array
	 */
	function add_episode_airing_data($Episode){
		if (empty($Episode))
			return null;

		$airtime = strtotime($Episode['airs']);
		$Episode['displayed'] = strtotime('-24 hours', $airtime) < time();
		$Episode['aired'] = strtotime('+'.($Episode['season']===0?'2 hours':((!$Episode['twoparter']?30:60).' minutes')), $airtime) < time();
		return $Episode;
	}

	/**
	 * Returns all episodes from the database, properly sorted
	 *
	 * @param int $count
	 * @param string|null $where
	 *
	 * @return array
	 */
	function get_episodes($count = null, $where = null){
		global $Database;

		if (!empty($where))
			$Database->where($where);

		$Database->orderBy('season')->orderBy('episode')->where('season != 0');
		if ($count !== 1){
			$eps =  $Database->get('episodes',$count);
			foreach ($eps as $i => $ep)
				$eps[$i] = add_episode_airing_data($ep);
			return $eps;
		}
		else return add_episode_airing_data($Database->getOne('episodes'));
	}

	/**
	 * Returns the last episode aired from the db
	 *
	 * @return array
	 */
	function get_latest_episode(){
		return get_episodes(1,"airs < NOW() + INTERVAL '24 HOUR'");
	}

	/**
	 * Checks if provided episode is the latest episode
	 *
	 * @param array $Ep
	 *
	 * @return bool
	 */
	function is_episode_latest($Ep){
		$latest = get_latest_episode();
		return $Ep['season'] === $latest['season']
			&& $Ep['episode'] === $latest['episode'];
	}

	/**
	 * Get the <tbody> contents for the episode list table
	 *
	 * @param array|null $Episodes
	 *
	 * @return string
	 */
	function get_eptable_tbody($Episodes = null){
		if (!isset($Episodes)) $Episodes = get_episodes();

		if (empty($Episodes)) return "<tr class='empty align-center'><td colspan='3'><em>There are no episodes to display</em></td></tr>";

		$Body = '';
		$PathStart = '/episode/';
		$displayed = false;
		$LatestEp = get_latest_episode();
		foreach ($Episodes as $i => $ep) {
			$adminControls = $SeasonEpisode = $DataID = '';
			$isMovie = $ep['season'] === 0;
			if (!$isMovie){
				$Title = format_episode_title($ep, AS_ARRAY);
				$href = $PathStart.$Title['id'];
				if (PERM('inspector'))
					$adminControls = "<span class='admincontrols'>" .
						"<button class='edit-episode typcn typcn-pencil blue' title='Edit episode'></button> " .
						"<button class='delete-episode typcn typcn-times red' title='Delete episode'></button>" .
					"</span>";

				$SeasonEpisode = <<<HTML
			<td class='season' rowspan='2'>{$Title['season']}</td>
			<td class='episode' rowspan='2'>{$Title['episode']}</td>
HTML;

				$DataID = " data-epid='{$Title['id']}'";
			}
			else {
				$Title = array('title' => "Equestria Girls: {$ep['title']}");
				$href = "/eqg/{$ep['episode']}";
				$ep = add_episode_airing_data($ep);
			}

			$star = '';
			if ($ep['season'] === $LatestEp['season'] && $ep['episode'] === $LatestEp['episode']){
				$displayed = true;
				$star = '<span class="typcn typcn-eye" title="Curently visible on the homepage"></span> ';
			}
			$star .= '<span class="typcn typcn-media-play'.(!$ep['aired']?'-outline':'').'" title="'.($isMovie?'Movie':'Episode').' had'.($ep['aired']?' aired, voting enabled':'n\'t aired yet, voting disabled').'"></span> ';

			$airs = timetag($ep['airs'], EXTENDED, NO_DYNTIME);

			$Body .= <<<HTML
		<tr$DataID>
			$SeasonEpisode
			<td class='title'>$star<a href="$href">{$Title['title']}</a>$adminControls</td>
		</tr>
		<tr><td class='airs'>$airs</td></tr>
HTML;
		}
		return $Body;
	}

	/**
	 * If an episode is a two-parter's second part, then returns the first part
	 * Otherwise returns the episode itself
	 *
	 * @param int $episode
	 * @param int $season
	 * @param bool $dontIgnoreSeason0 If set to true, season 0 is valid
	 *
	 * @return array|null
	 */
	function get_real_episode($season, $episode, $dontIgnoreSeason0 = false){
		global $Database;

		if (!$dontIgnoreSeason0 && $season == 0){
			trigger_error('Season 0 ignored', E_USER_ERROR);
			return null;
		}

		$Ep1 = $Database->whereEp($season,$episode)->getOne('episodes');
		if (empty($Ep1)){
			$Part1 = $Database->whereEp($season,$episode-1)->getOne('episodes');
			return !empty($Part1) && !empty($Part1['twoparter']) ? add_episode_airing_data($Part1) : null;
		}
		else return add_episode_airing_data($Ep1);
	}
	define('ALLOW_SEASON_ZERO', true);

	/**
	 * Adds 's/S' to the end of a word
	 *
	 * @param string $w
	 *
	 * @return string
	 */
	 function s($w){
		return "$w'".(substr($w, -1) !== 's'?'s':'');
	 }

	/**
	 * Appends 's' to the end of string if input is not 1
	 *
	 * @param string $w Text to pluralize
	 * @param int $in Number to base pluralization off of
	 * @param bool $prep Prepend number to text
	 *
	 * @return string
	 */
	 function plur($w, $in, $prep = false){
		return ($prep?"$in ":'').$w.($in!==1?'s':'');
	 }
	 define('PREPEND_NUMBER', true);

	/**
	 * Parse session array for user page
	 */
	define('CURRENT',true);
	function render_session_li($Session, $current = false){
		$browserClass = browser_name_to_class_name($Session['browser_name']);
		$browserTitle = "{$Session['browser_name']} {$Session['browser_ver']}";
		$platform = !empty($Session['platform']) ? "<span class='platform'>on <strong>{$Session['platform']}</strong></span>" : '';

		$signoutText = !$current ? 'Delete' : 'Sign out';
		$buttons = "<button class='typcn remove ".(!$current?'typcn-trash red':'typcn-arrow-back')."' data-sid='{$Session['id']}'>$signoutText</button>";
		if (PERM('developer') && !empty($Session['user_agent'])){
			$buttons .= "<br><button class='darkblue typcn typcn-eye useragent' data-agent='".apos_encode($Session['user_agent'])."'>UA</button>".
				"<a class='btn orange typcn typcn-chevron-right' href='/browser/{$Session['id']}'>Debug</a>";
		}

		$firstuse = timetag($Session['created']);
		$lastuse = !$current ? 'Last used: '.timetag($Session['lastvisit']) : '<em>Current session</em>';
		echo <<<HTML
<li class="browser-$browserClass" id="session-{$Session['id']}">
	<span class="browser">$browserTitle</span>
	$platform$buttons
	<span class="created">Created: $firstuse</span>
	<span class="used">$lastuse</span>
</li>
HTML;
	}

	// Converts a browser name to it's equivalent class name
	function browser_name_to_class_name($BrowserName){
		return regex_replace(new RegExp('[^a-z]'),'',strtolower($BrowserName));
	}

	// Checks the image which allows a request to be finished
	$POST_TYPES = array('request','reservation');
	function check_request_finish_image($ReserverID = null){
		global $POST_TYPES, $Database;
		if (!isset($_POST['deviation']))
			respond('Please specify a deviation URL');
		$deviation = $_POST['deviation'];
		try {
			require_once 'includes/Image.php';
			$Image = new Image($deviation, array('fav.me','dA'));

			foreach ($POST_TYPES as $what){
				if ($Database->where('deviation_id', $Image->id)->has("{$what}s"))
					respond("This exact deviation has already been marked as the finished version of a different $what");
			}

			$return = array('deviation_id' => $Image->id);
			$Deviation = da_cache_deviation($Image->id);
			if (!empty($Deviation['author'])){
				$Author = get_user($Deviation['author'], 'name');

				if (!empty($Author)){
					if (!isset($_POST['allow_overwrite_reserver']) && !empty($ReserverID) && $Author['id'] !== $ReserverID){
						global $currentUser;
						$sameUser = $currentUser['id'] === $ReserverID;
						$person = $sameUser ? 'you' : 'the user who reserved this post';
						respond("You've linked to an image which was not submitted by $person. If this was intentional, press Continue to proceed with marking the post finished <b>but</b> note that it will make {$Author['name']} the new reserver.".($sameUser
								? "<br><br>This means that you'll no longer be able to interact with this post until {$Author['name']} or an administrator cancels the reservation on it."
								: ''), 0, array('retry' => true));
					}

					$return['reserved_by'] = $Author['id'];
				}
			}

			return $return;
		}
		catch (MismatchedProviderException $e){
			respond('The finished vector must be uploaded to DeviantArt, '.$e->getActualProvider().' links are not allowed');
		}
		catch (Exception $e){ respond($e->getMessage()); }
	}

	// Header link HTML generator
	define('HTML_ONLY', true);
	function get_header_link($item, $htmlOnly = false){
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

		if ($htmlOnly === HTML_ONLY) return $html;
		return array($class, $html);
	}

	/**
	 * Get user's vote for an episode
	 *
	 * Accepts a single array containing values
	 *  for the keys 'season' and 'episode'
	 * Return's the user's vote entry from the DB
	 *
	 * @param array $Ep
	 * @return array
	 */
	function get_episode_user_vote($Ep){
		global $Database, $signedIn, $currentUser;
		if (!$signedIn) return null;
		return $Database
			->whereEp($Ep['season'], $Ep['episode'])
			->where('user', $currentUser['id'])
			->getOne('episodes__votes');
	}

	// Render episode voting HTML
	/**
	 * @param $Episode
	 *
	 * @return string
	 */
	function get_episode_voting($Episode){
		$thing = $Episode['season'] == 0 ? 'movie' : 'episode';
		if (!$Episode['aired'])
			return "<p>Voting will start ".timetag($Episode['willair']).", after the $thing had aired.</p>";
		global $Database, $signedIn, $currentUser;
		$HTML = '';

		$Score = $Database->whereEp($Episode)->get('episodes__votes',null,'AVG(vote) as score, COUNT(vote) as votes');
		$Votes = !empty($Score[0]['votes']) ? $Score[0]['votes'] : 0;
		if (!empty($Score[0]['score']))
			$Score = $Score[0]['score'];
		else $Score = 0;

		$Score = round($Score*10)/10;
		$ScorePercent = round(($Score/5)*1000)/10;

		$HTML .= '<p>'.(!empty($Score) ? "This $thing is rated $Score/5 (<a class='detail' href='#detail'>$Votes votes</a>)" : 'Nopony voted yet.').'</p>';
		if ($Score > 0)
			$HTML .= "<img src='/muffin-rating?w=$ScorePercent' id='muffins' alt='muffin rating svg'>";

		$UserVote = get_episode_user_vote($Episode);
		if (empty($UserVote)){
			$HTML .= "<br><p>What did <em>you</em> think about the $thing?</p>";
			if ($signedIn)
				$HTML .= "<button class='blue rate typcn typcn-star'>Cast your vote</button>";
			else $HTML .= "<p><em>Sign in above to cast your vote!</em></p>";
		}
		else $HTML .= "<p>Your rating: ".plur('muffin', $UserVote['vote'], PREPEND_NUMBER).'</p>';

		return $HTML;
	}

	// Render upcoming episode HTML \\
	function get_upcoming_eps($Upcoming = null, $wrap = true){
		global $TIME_DATA;
		if (empty($Upcoming)){
			global $Database;
			$Upcoming = $Database->where('airs > NOW()')->orderBy('airs', 'ASC')->get('episodes');
		}
		if (empty($Upcoming)) return;
		
		$HTML = $wrap ? '<section id="upcoming"><h2>Upcoming episodes</h2><ul>' : '';
		foreach ($Upcoming as $i => $ep){
			$airtime = strtotime($ep['airs']);
			$airs = date('c', $airtime);
			$month = date('M', $airtime);
			$day = date('j', $airtime);
			$diff = timeDifference(time(), $airtime);

			$time = 'in ';
			if ($diff['time'] < $TIME_DATA['month']){
				$tz = "(".date('T', $airtime).")";
				if (!empty($diff['week']))
					$diff['day'] += $diff['week'] * 7;
				if (!empty($diff['day']))
					$time .=  "{$diff['day']} day".($diff['day']!==1?'s':'').' & ';
				if (!empty($diff['hour']))
					$time .= "{$diff['hour']}:";
				foreach (array('minute','second') as $k)
					$diff[$k] = pad($diff[$k]);
				$time = "<time datetime='$airs'>$time{$diff['minute']}:{$diff['second']} $tz</time>";
			}
			else $time = timetag($ep['airs']);

			$title = ($ep['season'] === 0 ? 'EQG: ' : '').$ep['title'];

			$HTML .= "<li><div class='calendar'><span class='top'>$month</span><span class='bottom'>$day</span></div>".
				"<div class='meta'><span class='title'>$title</span>$time</div></li>";
		}
		return $HTML.($wrap?'</ul></section>':'');
	}

	/**
	 * Rate limit check for reservations
	 * ---------------------------------
	 * SQL Query to check status of every user (for debugging)
SELECT
@id := u.id,
u.name,
(
	(SELECT
	 COUNT(*) as "count"
	 FROM reservations res
	 WHERE res.reserved_by = @id && res.deviation_id IS NULL)
	+(SELECT
	  COUNT(*) as "count"
	  FROM requests req
	  WHERE req.reserved_by = @id && req.deviation_id IS NULL)
) as "count"
FROM "users" u
HAVING "count" > 0
ORDER BY "count" DESC
	 */
	function res_limit_check(){
		global $Database, $currentUser;

		$reservations = $Database->rawQuerySingle(
			'SELECT
			(
				(SELECT
				 COUNT(*) as "count"
				 FROM reservations res
				 WHERE res.reserved_by = u.id && res.deviation_id IS NULL)
				+(SELECT
				  COUNT(*) as "count"
				  FROM requests req
				  WHERE req.reserved_by = u.id && req.deviation_id IS NULL)
			) as "count"
			FROM users u WHERE u.id = ?',
			array($currentUser['id'])
		);

		if (isset($reservations['count']) && $reservations['count'] >= 4)
			respond("You've already reserved {$reservations['count']} images, and you can't have more than 4 pending reservations at a time. You can review your reservations on your <a href='/user'>profile page</a>, finish at least one of them before trying to reserve another image.");
	}

	// Render episode video player \\
	$VIDEO_PROVIDER_NAMES = array(
		'yt' => 'YouTube',
		'dm' => 'Dailymotion',
	);
	$PROVIDER_BTN_CLASSES = array(
		'yt' => 'red typcn-social-youtube',
		'dm' => 'darkblue typcn-video',
	);
	function render_ep_video($CurrentEpisode){
		global $Database, $VIDEO_PROVIDER_NAMES, $PROVIDER_BTN_CLASSES;

		$isMovie = $CurrentEpisode['season'] === 0;
		$HTML = '';
		$Videos = $Database
			->whereEp($CurrentEpisode)
			->orderBy('provider', 'ASC')
			->orderBy('part', 'ASC')
			->get('episodes__videos');

		if (!empty($Videos)){
			$fullep = $CurrentEpisode['twoparter'] ? 'Full episode' : '';
			if (count($Videos) === 1 && $Videos[0]['provider'] === 'yt'){
				$airtime = strtotime($CurrentEpisode['airs']);
				$modified = $Videos[0]['modified'];
				if (!empty($modified) && $airtime > strtotime($modified)){
					$fullep = 'Livestream';
					$CurrentEpisode = add_episode_airing_data($CurrentEpisode);
					if ($CurrentEpisode['aired'])
						$fullep .= ' recording';
					if (!$CurrentEpisode['twoparter'])
						$fullep = " ($fullep)";
				}
			}

			$HTML = "<section class='episode'><h2>Watch the ".($isMovie?'Movie':'Episode')."</h2><p class='align-center actions'>";
			require_once "includes/Video.php";
			foreach ($Videos as $v){
				$url = Video::get_embed($v['id'], $v['provider'], Video::URL_ONLY);
				$partText = $CurrentEpisode['twoparter'] ? (
					!$v['fullep']
					? " (Part {$v['part']})"
					: " ($fullep)"
				) : $fullep;
				$HTML .= "<a class='btn typcn {$PROVIDER_BTN_CLASSES[$v['provider']]}' href='$url' target='_blank'>{$VIDEO_PROVIDER_NAMES[$v['provider']]}$partText</a>";
			}
			$HTML .= "<button class='green typcn typcn-eye showplayers'>Show on-site player</button></p></section>";
		}

		return $HTML;
	}

	function get_ep_video_embeds($CurrentEpisode){
		global $Database, $VIDEO_PROVIDER_NAMES;

		$EpVideos = $Database
			->whereEp($CurrentEpisode)
			->orderBy('provider', 'ASC')
			->orderBy('part', 'ASC')
			->get('episodes__videos');
		$Parts = 0;
		$embed = '';
		if (!empty($EpVideos)){
			$Videos = array();
			foreach ($EpVideos as $v)
				$Videos[$v['provider']][(int)$v['part']] = $v;
			// YouTube embed preferred
			$Videos = !empty($Videos['yt']) ? $Videos['yt'] : $Videos['dm'];

			$Parts = count($Videos);
			require_once "includes/Video.php";
			foreach ($Videos as $v)
				$embed .= "<div class='responsive-embed".($CurrentEpisode['twoparter']&&$v['part']!==1?' hidden':'')."'>".Video::get_embed($v['id'], $v['provider'])."</div>";
		}
		return array($Parts, $embed);
	}

	// Turns an ini setting into bytes
	function size_in_bytes($size){
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

	// Returns the maximum uploadable file size in a readable format
	function get_max_upload_size(){
		$sizes = array(ini_get('post_max_size'), ini_get('upload_max_filesize'));

		$workWith = $sizes[0];
		if ($sizes[1] !== $sizes[0]){
			$sizesBytes = array_map('size_in_bytes', $sizes);
			if ($sizesBytes[1] < $sizesBytes[0])
				$workWith = $sizes[1];
		}

		return regex_replace(new RegExp('^(\d+)([GMk])$'), '$1 $2B', $workWith);
	}

	// Pagination creator
	function get_pagination_html($basePath, $wrap = true){
		global $Page, $MaxPages;

		require_once APPATH."includes/Pagination.php";
		return (string) new Pagination($Page, $MaxPages, $basePath, $wrap);
	}

	// Pagiation calculate page
	function calc_page($EntryCount){
		global $data, $ItemsPerPage;

		$MaxPages = max(1, ceil($EntryCount/$ItemsPerPage));
		$Page = min(max(intval(regex_replace(new RegExp('^.*\/(\d+)$'),'$1',$data), 10), 1), $MaxPages);

		return array($Page, $MaxPages);
	}

	// Gets navigation HTML
	function get_nav_html(){
		if (!empty($GLOBALS['NavHTML']))
			return $GLOBALS['NavHTML'];

		global $do;

		// Navigation items
		$NavItems = array(
			'latest' => array('/','Latest episode'),
			'eps' => array('/episodes','Episodes'),
		);
		if ($do === 'episodes'){
			global $Episodes, $Page;
			if (isset($Episodes))
				$NavItems['eps'][1] .= " - Page $Page";
		}
		if ($do === 'episode' && !empty($GLOBALS['CurrentEpisode'])){
			if (!empty($GLOBALS['Latest']))
				$NavItems['latest'][0] = $_SERVER['REQUEST_URI'];
			else $NavItems['eps']['subitem'] = $GLOBALS['title'];
		}
		global $color, $Color, $EQG;
		$NavItems['colorguide'] = array("/{$color}guide", (!empty($EQG)?'EQG ':'')."$Color Guide");
		if ($do === 'colorguide'){
			global $Tags, $Changes, $Ponies, $Page, $Appearance;
			if (!empty($Appearance))
				$NavItems['colorguide']['subitem'] = $Appearance['label'];
			else if (isset($Ponies))
				$NavItems['colorguide'][1] .= " - Page $Page";
			else {
				if ($GLOBALS['data'] === 'full'){
					$NavItems['colorguide']['subitem'] = 'Full List';
				}
				else {
					if (isset($Tags)) $pagePrefix = 'Tags';
					else if (isset($Changes)) $pagePrefix = "Major $Color Changes";

					$NavItems['colorguide']['subitem'] = (isset($pagePrefix) ? "$pagePrefix - " : '')."Page $Page";
				}
			}

		}
		if ($GLOBALS['signedIn'])
			$NavItems['u'] = array("/@{$GLOBALS['currentUser']['name']}",'Account');
		if ($do === 'user' || PERM('inspector')){
			global $User, $sameUser;
			
			$NavItems['users'] = array('/users', 'Users', PERM('inspector'));
			if (!empty($User) && empty($sameUser))
				$NavItems['users']['subitem'] = $User['name'];
		}
		if (PERM('inspector')){
			$NavItems['admin'] = array('/admin', 'Admin');
			global $task;
			if ($task === 'logs'){
				global $Page;
				$NavItems['admin']['subitem'] = "Logs - Page $Page";
			}
		}
		$NavItems[] = array('/about', 'About');

		$GLOBALS['NavHTML'] = '';
		$currentSet = false;
		foreach ($NavItems as $item){
			$sublink = '';
			if (isset($item['subitem'])){
				list($class, $sublink) = get_header_link(array(true, $item['subitem']));
				$sublink = " &rsaquo; $sublink";
				$link = get_header_link($item, HTML_ONLY);
			}
			else if (isset($item[2]) && !$item[2])
				continue;
			else list($class, $link) = get_header_link($item);
			$GLOBALS['NavHTML'] .= "<li$class>$link$sublink</li>";
		}
		$GLOBALS['NavHTML'] .= '<li><a href="http://mlp-vectorclub.deviantart.com/" target="_blank">MLP-VectorClub</a></li>';
		return $GLOBALS['NavHTML'];
	}

	// Returns text of website footer
	function get_footer(){
		return "Running <strong><a href='".GITHUB_URL."' title='Visit the GitHub repository'>MLPVC-RR</a>@<a href='".GITHUB_URL."/commit/".LATEST_COMMIT_ID."' title='See exactly what was changed and why'>".LATEST_COMMIT_ID."</a></strong> created ".timetag(LATEST_COMMIT_TIME)." | <a class='issues' href='".GITHUB_URL."/issues' target='_blank'>Known issues</a> | ".(PERM('user')?"<a href='#feedback' class='send-feedback'>Send feedback</a>":"<a href='".GITHUB_URL."/issues/new' target='_blank'>Send feedback</a>");
	}

	// Loads the home page
	function loadEpisodePage($force = null, $EQG = false){
		global $data, $CurrentEpisode, $Requests, $Reservations, $Latest, $Database;

		if (is_int($force))
			$CurrentEpisode = get_real_episode(0, $force, ALLOW_SEASON_ZERO);
		else if (is_array($force))
			$CurrentEpisode = $force;
		else {
			$EpData = episode_id_parse($data);
			if (empty($EpData))
				$CurrentEpisode = get_latest_episode();
			else $CurrentEpisode = get_real_episode($EpData['season'],$EpData['episode']);
		}
		if (!empty($CurrentEpisode)){
			$Latest = empty($EpData) ? true : is_episode_latest($CurrentEpisode);
			list($Requests, $Reservations) = get_posts($CurrentEpisode['season'], $CurrentEpisode['episode']);
		}

		$EpID = format_episode_title($CurrentEpisode,AS_ARRAY,'id');
		if (!$EQG)
			fix_path("/episode/$EpID");
		else fix_path("/eqg/{$GLOBALS['url']}");

		loadPage(array(
			'title' => format_episode_title($CurrentEpisode),
			'view' => 'episode',
			'css' => 'episode',
			'js' => array('imagesloaded.pkgd','jquery.ba-throttle-debounce','jquery.fluidbox.min','Chart','episode'),
		));
	}

	// Creates the markup for an HTML notice
	$NOTICE_TYPES = array('info','success','fail','warn','caution');
	function Notice($type, $title, $text = null, $center = false){
		global $NOTICE_TYPES;
		if (!in_array($type, $NOTICE_TYPES))
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
			$HTML .= '<p>'.trim($row).'</p>';

		if ($center)
			$type .= ' align-center';
		return "<div class='notice $type'>$HTML</div>";
	}

	/**
	 * Checks validity of a string based on regex
	 *  and responds if invalid chars are found
	 *
	 * @param string $string
	 * @param string $Thing
	 * @param string $pattern
	 * @param bool $returnError
	 *
	 * @return null
	 */
	function check_string_valid($string, $Thing, $pattern, $returnError = false){
		if (regex_match(new RegExp($pattern,'u'), $string, $fails)){
			$invalid = array();
			foreach ($fails as $f)
				if (!in_array($f, $invalid))
					$invalid[] = $f;

			$s = count($invalid)!==1?'s':'';
			$the_following = count($invalid)!==1?' the following':'an';
			$Error = "$Thing ($string) contains $the_following invalid character$s: ".array_readable($invalid);
			if (!$returnError) respond($Error);
			return $Error;
		}
	}

	/**
	 * POST data validator function used when creating/editing posts
	 *
	 * @param string $thing "request"/"reservation"
	 * @param array $array Array to output the checked data into
	 * @param array|null $Post Optional, exsting post to compare new data against
	 */
	function check_post_post($thing, &$array, $Post = null){
		$editing = !empty($Post);

		if (!empty($_POST['label'])){
			$label = trim($_POST['label']);

			if (!$editing || $label !== $Post['label']){
				$labellen = strlen($label);
				if ($labellen < 3 || $labellen > 255)
					respond("The description must be between 3 and 255 characters in length");
				check_string_valid($label,'The description',INVERSE_PRINTABLE_ASCII_REGEX);
				$array['label'] = $label;
			}
		}
		else if (!$editing && $thing !== 'reservation')
			respond('Description cannot be empty');

		if ($thing === 'request'){
			if (!empty($_POST['type'])){
				if (!in_array($_POST['type'],array('chr','obj','bg')))
					respond("Invalid request type");
			}
			else if (!$editing)
				respnd("Missing request type");

			if (!$editing || (!empty($_POST['type']) && $_POST['type'] !== $Post['type']))
				$array['type'] = $_POST['type'];

			if (!empty($_POST['reserved_at'])){
				if (!PERM('developer'))
					respond();

				$array['reserved_at'] = date('c', strtotime($_POST['reserved_at']));
			}
		}

		if (!empty($_POST['posted'])){
			if (!PERM('developer'))
				respond();

			$array['posted'] = date('c', strtotime($_POST['posted']));
		}
	}

	/**
	 * Check image URL in POST request
	 *
	 * @param array|null $Post Existing post for comparison
	 *
	 * @return Image
	 */
	function check_post_image($Post = null){
		if (empty($_POST['image_url']))
			respond('Please enter an image URL');

		require_once 'includes/Image.php';
		try {
			$Image = new Image($_POST['image_url']);
		}
		catch (Exception $e){ respond($e->getMessage()); }

		global $POST_TYPES, $Database;
		foreach ($POST_TYPES as $type){
			if (!empty($Post['id']))
				$Database->where('r.id',$Post['id'],'!=');
			$Database
				->join('episodes ep','r.season = ep.season && r.episode = ep.episode')
				->where('r.preview','','!=')
				->where('r.preview',$Image->preview)
				->getOne("{$type}s r",'ep.*, r.id');
			if (!empty($Used)){
				$EpID = format_episode_title($Used,AS_ARRAY,'id');
				respond("This exact image has already been used for a $type under <a href='/episode/$EpID#$type-{$Used['id']}' target='_blank'>$EpID</a>");
			}
		}

		return $Image;
	}

	/**
	 * Normalize a misaligned Stash submission ID
	 *
	 * @param string $id Stash submission ID
	 *
	 * @return string
	 */
	function stash_id_normalize($id){
		$normalized = ltrim($id,'0');
		return strlen($normalized) < 12 ? '0'.$normalized : $normalized;
	}

	// Get link to specific posts
	function post_link_html($Post){
		$thing = empty($Post['rq']) ? 'reservation' : 'request';
		$id = "$thing-{$Post['id']}";
		if ($Post['season'] !== 0){
			$page = "S{$Post['season']}E{$Post['episode']}";
			$link = "/episode/$page#$id";
		}
		else {
			$page = "EQG {$Post['episode']}";
			$link = "/eqg/{$Post['episode']}";
		}
		return array($link,$page);
	}

	// Respond to paginated result requests
	function pagination_response($output, $update){
		global $Pagination, $Page;
		$RQURI = rtrim(regex_replace(new RegExp('js=true(?:&|$)'),'',$_SERVER['REQUEST_URI']),'?');
		respond(array(
			'output' => $output,
			'update' => $update,
			'pagination' => $Pagination,
			'page' => $Page,
			'request_uri' => $RQURI,
		));
	}

	/**
	 * Process label data for stats
	 */
	function process_stat_labels(){
		global $Labels, $Data;

		if (empty($Labels))
			$Labels = array();
		else {
			foreach ($Labels as $k => $v)
				$Labels[$k] = $v['key'];
		}

		$Data['labels'] = $Labels;
	}

	/**
	 * Process data for usage stats
	 *
	 * @param array $Rows    Database rows obtained with rawQuery
	 * @param array $Dataset Array to process data into
	 */
	function process_usage_stats($Rows, &$Dataset){
		$Dataset['labels'] =
		$Dataset['data'] = array();

		foreach ($Rows as $row){
			$Dataset['labels'][] = $row['key'];
			$Dataset['data'][] = $row['cnt'];
		}

		global $Labels;
		foreach ($Labels as $ix => $label){
			if (empty($Dataset['labels'][$ix]) || $Dataset['labels'][$ix] !== $label){
				array_splice($Dataset['labels'], $ix, 0, array($label));
				array_splice($Dataset['data'], $ix, 0, array(0));
			}
		}

		unset($Dataset['labels']);
	}

	// Create upload destination folder
	function upload_folder_create($path){
		$DS = RegExp::escapeBackslashes('\/');
		$folder = regex_replace(new RegExp("^(.*[$DS])[^$DS]+$"),'$1',regex_replace(new RegExp('$DS'),'\\',$path));
		return !is_dir($folder) ? mkdir($folder,0777,true) : true;
	}

	function get_ep_tag_ids($Ep){
		global $CGDb;

		$EpTagIDs = array();
		$EpTagPt1 = $CGDb->where('name',"s{$Ep['season']}e{$Ep['episode']}")->getOne('tags','tid');
		if (!empty($EpTagPt1))
			$EpTagIDs[] = $EpTagPt1['tid'];
		if ($Ep['twoparter']){
			$EpTagPt2 = $CGDb->where('name',"s{$Ep['season']}e".($Ep['episode']+1))->getOne('tags','tid');
			if (!empty($EpTagPt2))
				$EpTagIDs[] = $EpTagPt2['tid'];
			$EpTagBothParts = $CGDb->where('name',"s{$Ep['season']}e{$Ep['episode']}-".($Ep['episode']+1))->getOne('tags','tid');
			if (!empty($EpTagBothParts))
				$EpTagIDs[] = $EpTagBothParts['tid'];
		}
		return $EpTagIDs;
	}

	// Export PHP variables into JS through a script tag
	function ExportVars($export){
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
					trigger_error("Exporting unsupported variable $name of type $type", E_USER_ERROR);
			}
			$HTML .= "$name=$value,";
		}
		echo rtrim($HTML,',').'</script>';
	}

	function render_useful_links_list($wrap = true){
		global $Database, $ROLES_ASSOC;

		$HTML = $wrap ? '<ol>' : '';

		$UsefulLinks = $Database->get('usefullinks');
		foreach ($UsefulLinks as $l){
			$href = apos_encode($l['url']);
			$title = apos_encode($l['title']);
			$label = htmlspecialchars_decode($l['label']);
			$cansee = $ROLES_ASSOC[$l['minrole']];
			if ($l['minrole'] !== 'developer')
				$cansee .= 's and above';
			$HTML .= "<li id='ufl-{$l['id']}'><div><a href='$href' title='$title'>{$label}</a></div>".
			             "<div><span class='typcn typcn-eye'></span> $cansee</div>".
			             "<div class='buttons'><button class='blue typcn typcn-pencil edit-link'>Edit</button><button class='red typcn typcn-trash delete-link'>Delete</button></div></li>";
		}

		return $HTML.($wrap?'</ol>':'');
	}

	function render_most_recent_posts($wrap = true){
		global $Database, $ROLES_ASSOC;

		$cols = 'id,season,episode,label,posted,preview,lock,deviation_id,reserved_by';
		$RecentPosts = $Database->rawQuery(
			"SELECT * FROM
			(
				SELECT $cols, requested_by, true AS rq, reserved_at FROM requests
				WHERE posted > NOW() - INTERVAL '20 DAYS'
				UNION ALL
				SELECT $cols, null AS requested_by, false AS rq, null AS reserved_at FROM reservations
				WHERE posted > NOW() - INTERVAL '20 DAYS'
			) t
			ORDER BY posted DESC
			LIMIT 10");

		$HTML = $wrap ? '<ul>' : '';

		foreach ($RecentPosts as $p){
			list($link,$page) = post_link_html($p);
			$label = !empty($p['label']) ? "<span class='label'>{$p['label']}</span>" : '';
			$is_request = $p['rq'];
			$reservation_time_known = !empty($p['reserved_at']);
			$posted = timetag($p['posted']);
			$what = !empty($p['deviation_id']) ? 'deviation' : 'screencap';
			$alt = '';
			$preview = $p['preview'];
			if (!empty($p['deviation_id'])){
				$Deviation = da_cache_deviation($p['deviation_id']);
				$preview = $Deviation['preview'];
				$alt = $Deviation['title'];
			}
			else if (!empty($p['label']))
				$alt = $p['label'];
			$reserver_btn = '';
			if ($is_request)
				$reserver_btn .= '<em>Posted by '.profile_link(get_user($p['requested_by'])).'</em>';
			if (!$is_request || isset($p['reserved_by'])){
				$user = profile_link(get_user($p['reserved_by']), FULL);
				$reserver_btn = "<div class='reserver'>$user</div>";
			}
			$lock = $p['lock'] ? "<span class='typcn typcn-tick' title='This submission has been accepted into the group gallery'></span>" : '';

			$HTML .= <<<HTML
<li>
	<div class='image $what'>
		<a href='$link'><img src='$preview' alt='preview'>$lock</a>
	</div>
	$label
	<em>Posted under <a href='$link'>$page</a> $posted</em>
	$reserver_btn
	<div>
		<a href='$link' class='btn blue typcn typcn-arrow-forward'>View</a>
	</div>
</li>

HTML;
		}

		return $HTML.($wrap?'</ul>':'');
	}
