<?php

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
	function respond($m = 'Insufficent permissions.', $s = false, $x = array()){
		header('Content-Type: application/json');
		if (is_array($m) && $s == false && empty($x)){
			$m['status'] = true;
			die(json_encode($m));
		}
		if ($m === ERR_DB_FAIL){
			global $Database;
			$m .= ": ".$Database->getLastError();
		}
		die(json_encode(array_merge(array(
			"message" => $m,
			"status" => $s,
		),$x)));
	}

	# Logging
	$LOG_DESCRIPTION = array(
		'episodes' => 'Episode management',
		'episode_modify' => 'Episode modified',
		'rolechange' => 'User group change',
		'userfetch' => 'Fetch user details',
		'banish' => 'User banished',
		'un-banish' => 'User un-banished',
	);
	function LogAction($type,$data = null){
		global $Database, $signedIn, $currentUser;
		$central = array('ip' => $_SERVER['REMOTE_ADDR']);

		if (isset($data)){
			foreach ($data as $k => $v)
				if (is_bool($v))
					$data[$k] = $v ? 1 : 0;

			$refid = $Database->insert("`log__$type`",$data);
		}

		$central['reftype'] = $type;
		if (!empty($refid))
			$central['refid'] = $refid;
		else if (!empty($data)) return false;

		if ($signedIn)
			$central['initiator'] = $currentUser['id'];
		return !!$Database->insert("log",$central);
	}

	# Format log details
	function format_log_details($logtype, $data){
		global $Database, $ROLES_ASSOC;
		$details = array();

		switch ($logtype){
			case "rolechange":
				$target =  $Database->where('id',$data['target'])->getOne('users');

				$details = array(
					array('Target user',"<a href='/u/{$target['name']}'>{$target['name']}</a>"),
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
				$user =  $Database->where('id',$data['userid'])->getOne('users');
				$details[] = array('User', profile_link($user));
			break;
			case "banish":
			case "un-banish":
				$user =  $Database->where('id',$data['target'])->getOne('users');
				$details[] = array('User', profile_link($user));
				$details[] = array('Reason', htmlspecialchars($data['reason']));
			break;
			default:
				$details[] = array('Could not process details','No data processor defined for this entry type');
			break;
		}

		return array('details' => $details);
	}

	# Render log page <tbody> content
	function log_tbody_render($LogItems){
		global $Database, $LOG_DESCRIPTION;

		if (count($LogItems) > 0) foreach ($LogItems as $item){
			if (!empty($item['initiator'])){
				$inituser = $Database->where('id',$item['initiator'])->getOne('users');
				if (empty($inituser))
					$inituser = 'Deleted user';
				else $inituser = "<a href='/u/{$inituser['name']}'>{$inituser['name']}</a>";

				if (in_array($item['ip'],array('::1','127.0.0.1'))) $ip = "localhost";
				else $ip = $item['ip'];

				if ($item['ip'] === $_SERVER['REMOTE_ADDR']) $ip .= ' <span class="self">(from your IP)</span>';
			}
			else {
				$inituser = null;
				$ip = 'Web server';
			}

			$event = isset($LOG_DESCRIPTION[$item['reftype']]) ? $LOG_DESCRIPTION[$item['reftype']] : $item['reftype'];
			if ($item['reftype'] !== 'logclear')
				$event = '<span class="expand-section typcn typcn-plus">'.$event.'</span>';
			$ts = timetag($item['timestamp']);

			if (!empty($inituser)) $ip = "$inituser<br>$ip";

			$HTML = <<<HTML
		<tr>

			<td class=entryid>{$item['entryid']}</td>
			<td class=timestamp>$ts<br><span class="dynt-el"></span></td>
			<td class=ip>$ip</td>
			<td class=reftype>$event</td>
		</tr>
HTML;

			echo $HTML;
		}
		else echo "<tr><td colspan=4>There are no log items</td></tr>";
	}
	
	// Gets the difference between 2 timestamps \\
	function timeDifference($n,$e) {
		$substract = $n - $e;
		$d = array(
			'past' => $substract > 0,
			'time' => abs($substract),
			'target' => $e
		);
		$time = $d['time'];
		
		$d['day'] = floor($time/60/60/24);
		$time -= $d['day']*60*60*24;
		
		$d['hour'] = floor($time/60/60);
		$time -= $d['hour']*60*60;
		
		$d['minute'] = floor($time/60);
		$time -= $d['minute']*60;
		
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
	 */
	$TIME_DATA = array(
		'year' => 31557600,
		'month' => 2592000,
		'day' => 86400,
		'hour' => 3600,
		'minute' => 60,
		'second' => 1,
	);
	function time_ago($timestamp){
		global $TIME_DATA;

		$delta = time() - $timestamp;
		$past = $delta > 0;
		if (!$past) $delta *= -1;

		foreach ($TIME_DATA as $n => $v){
			if ($delta >= $v){
				$left = floor($delta / $v);
				$delta -= ($left * $v);
				if (!$past && $n !== 'second')
					$left++;
				$str = "{$left} ".($left!=1?"{$n}s":$n);
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
			$ts = time_ago($time).($format !== 'c' ? ' ('.date('T').')' : '');
		else $ts = gmdate($format,$time);
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
				?"(<span class=dynt-el>$full</span>)"
				:''
			);
	}

	// Page loading function
	function loadPage($settings){
		// Page <title>
		if (isset($settings['title']))
			$title = $settings['title'];

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
		$DEFAULT_JS = array('dyntime','dialog','global');
		$customJS = array();
		// Add logged_in.js for logged in users
		global $signedIn;
		if (isset($signedIn) && $signedIn === true) $DEFAULT_JS[] = 'logged_in';
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
		if (empty($settings['view'])) $view = $do;
		else $view = $settings['view'];
		$viewPath = "views/{$view}.php";

		header('Content-Type: text/html; charset=utf-8;');

		// Kell-e fejrész?
		if (array_search('no-header',$settings) === false){
			$pageHeader = array_search('no-page-header',$settings) === false;
			require 'views/header.php';
		}
		// Megjelenésfájl betöltése
		require $viewPath;
		// Kell-e lábrész?
		if (array_search('no-footer',$settings) === false){
			//$customCSS[] = 'footer';
			require 'views/footer.php';
		}

		die();
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
		if ($type === 'css') foreach ($customType as $i => $item){
			if (!file_exists("$pathStart$item.$type")){
				if(file_exists("$pathStart$item.min.$type"))
					$customType[$i] .= '.min';
				else array_splice($customType,$i,1);
			}
		}
		else if ($type === 'js') foreach ($customType as $i => $item){
			if (strpos($item,'.min') !== false) continue;
			if (file_exists("$pathStart$item.min.$type"))
				$customType[$i] .= '.min';
		}
	}

	// Display a 404 page
	function do404(){
		if (RQMTHD == 'POST') respond("I don't know how to do: {$GLOBALS['do']}");
		loadPage(array(
			'title' => '404',
			'view' => '404',
			'css' => '404',
			'status-code' => 404,
		));
	}

	// Time Constants \\
	define('EIGHTY_YEARS',2524556160);
	define('THREE_YEARS',94608000);
	define('THIRTY_DAYS',2592000);
	define('ONE_HOUR',3600);

	// Random Array Element \\
	function array_random($array){ return $array[array_rand($array, 1)]; }

	// Color padder \\
	function clrpad($c){
		if (strlen($c) === 3) $c = $c[0].$c[0].$c[1].$c[1].$c[2].$c[2];
		return $c;
	}

	// Redirection \\
	define('STAY_ALIVE', false);
	function redirect($url = '/', $die = true){
		header("Location: $url");
		if ($die !== STAY_ALIVE) die();
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
			trigger_error('Érvénytelen státuszkód: '.$code,E_USER_ERROR);
		else
			header($_SERVER['SERVER_PROTOCOL'].' '.$code.' '.$HTTP_STATUS_CODES[$code]);

		if ($die === AND_DIE) die();
	}

	// CSRF Check \\
	function detectCSRF($CSRF = null){
		if (!isset($CSRF)) global $CSRF;
		if (isset($CSRF) && $CSRF)
			die(statusCodeHeader(401));
	}

	// oAuth Error Response Messages \\
	$OAUTH_RESPONSE = array(
		'invalid_request' => 'The authorization recest was not properly formatted.',
		'unsupported_response_type' => 'The authorization server does not support obtaining an authorization code using this method.',
		'unauthorized_client' => 'The authorization process did not complete. Please try again.',
		'invalid_scope' => 'The requested scope is invalid, unknown, or malformed.',
		'server_error' => "There's an issue on deviantArt's end. Try again later.",
		'temporarily_unavailable' => "There's an issue on deviantArt's end. Try again later.",
		'user_banned' => 'You were banned on our website by a staff member',
	);

	// Redirection URI shortcut \\
	function oauth_redirect_uri($state = true){
		global $do, $data;
		if ($do === 'index' && empty($data)) $returnURL = RELPATH;
		else $returnURL = rtrim("/$do/$data",'/');
		return '&redirect_uri='.urlencode(ABSPATH."da-auth").($state?'&state='.urlencode($returnURL):'');
	}

	/**
	 * Makes authenticated requests to the deviantArt API
	 *
	 * @param string $endpoint
	 * @param null|array $postdata
	 * @param null|string $token
	 * @return array
	 */
	function da_request($endpoint, $postdata = null, $token = null){
		global $signedIn, $currentUser;

		if (empty($token)){
			if (!$signedIn) return null;

			$token = $currentUser['Session']['access'];
		}

		$r = curl_init(preg_match('~^https?://~', $endpoint) ? $endpoint : "https://www.deviantart.com/api/v1/oauth2/$endpoint");
		curl_setopt($r, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($r, CURLOPT_HTTPHEADER, array("Authorization: Bearer $token"));

		if (!empty($postdata)){
			$query = '';
			foreach($postdata as $k => $v) $query .= "$k=$v&";
			rtrim($query, '&');
			curl_setopt($r,CURLOPT_POST, count($postdata));
			curl_setopt($r,CURLOPT_POSTFIELDS, $query);
		}
		$response = curl_exec($r);
		curl_close($r);

		return json_decode($response, true);
	}

	/**
	 * Requests or refreshes an Access Token
	 * $type defaults to 'authorization_code'
	 *
	 * @param string $code
	 * @param null|string $type
	 */
	function da_get_token($code, $type = null){
		global $Database;

		if (empty($type) || !in_array($type,array('authorization_code','refresh_token'))) $type = 'authorization_code';
		$URL_Start = 'https://www.deviantart.com/oauth2/token?client_id='.DA_CLIENT.'&client_secret='.DA_SECRET."&grant_type=$type";

		switch ($type){
			case "authorization_code":
				$json = file_get_contents("$URL_Start&code=$code".oauth_redirect_uri(false));
			break;
			case "refresh_token":
				$json = file_get_contents("$URL_Start&refresh_token=$code");
			break;
		}

		if (empty($json)) die("Ow");
		$json = json_decode($json, true);
		if (empty($json['status'])) redirect("/da-auth?error={$json['error']}&error_description={$json['error_description']}");

		$userdata = da_request('user/whoami', null, $json['access_token']);

		$User = $Database->where('id',$userdata['userid'])->getOne('users');
		if ($User['role'] === 'ban') redirect("/da-auth?error=user_banned");

		$UserID = strtolower($userdata['userid']);
		$UserData = array(
			'name' => $userdata['username'],
			'avatar_url' => $userdata['usericon'],
		);
		$AuthData = array(
			'access' => $json['access_token'],
			'refresh' => $json['refresh_token'],
			'expires' => date('c',time()+intval($json['expires_in']))
		);

		add_browser($AuthData);
		if (empty($User)){
			$MoreInfo = array('id' => $UserID, 'role' => 'user');
			$makeDev = !$Database->has('users');
			if ($makeDev)
				$MoreInfo['id'] = strtoupper($MoreInfo['id']);
			$Insert = array_merge($UserData, $MoreInfo);
			$Database->insert('users', $Insert);
			if ($makeDev) update_role($Insert, 'developer');
		}
		else $Database->where('id',$UserID)->update('users', $UserData);

		if ($type === 'refresh_token') $Database->where('refresh', $code)->update('sessions',$AuthData);
		else $Database->insert('sessions', array_merge($AuthData, array('user' => $UserID)));

		Cookie::set('access',$AuthData['access'],THREE_YEARS);
	}

	/**
	 * Adds browser info to $Authdata
	 */
	function browser(){
		require_once "includes/Browser.php";
		$browser = new Browser();
		$Return = array();
		$name = $browser->getBrowser();
		if ($name !== Browser::BROWSER_UNKNOWN){
			$Return['browser_name'] = $name;

			$ver = $browser->getVersion();
			if ($ver !== Browser::VERSION_UNKNOWN)
				$Return['browser_ver'] = $ver;
		}
		return $Return;
	}
	function add_browser(&$AuthData){
		$browser = browser();
		if (!empty($browser))
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
	function da_oembed($ID, $type = null){
		if (empty($type) || !in_array($type,array('fav.me','sta.sh'))) $type = 'fav.me';

		$data = @file_get_contents('http://backend.deviantart.com/oembed?url='.urlencode("http://$type/$ID"));

		if (empty($data))
			throw new Exception('Image not found. Please make sure that the URL is correct.');

		return array_merge(json_decode($data, true),array('_provider' => $type));
	}

	/**
	 * Caches information about a deviation in the 'deviation_cache' table
	 * Returns null on failure
	 *
	 * @param string $ID
	 * @param null|string $type
	 * @return array|null
	 */
	function da_cache_deviation($ID, $type = null){
		global $Database, $PROVIDER_FULLSIZE_KEY;

		$Deviation = $Database->where('id',$ID)->getOne('deviation_cache');
		if (empty($Deviation) || (!empty($Deviation['updated_on']) && strtotime($Deviation['updated_on'])+ONE_HOUR < time())){
			try {
				$json = da_oembed($ID, $type);
			}
			catch (Exception $e){
				if (!empty($Deviation))
					$Database->where('id',$Deviation['id'])->update('deviation_cache', array('updated_on' => date('c',strtotime('+1 minute'))));
				die("Saving local data for $ID of type $type failed, please try again in a minute.");
			}

			$insert = array(
				'title' => $json['title'],
				'preview' => $json['thumbnail_url'],
				'fullsize' => $json['url'],
				'provider' => $json['_provider'],
			);

			if (empty($Deviation)){
				$insert['id'] = $ID;
				$Database->insert('deviation_cache', $insert);
			}
			else {
				$Database->where('id',$Deviation['id'])->update('deviation_cache', $insert);
				$insert['id'] = $ID;
			}

			$Deviation = $insert;
		}

		return $Deviation;
	}

	# Get Roles from DB
	$ROLES_ASSOC = array();
	$ROLES = array();
	foreach ($Database->orderBy('value','ASC')->get('roles') as $r){
		$ROLES_ASSOC[$r['name']] = $r['label'];
		$ROLES[] = $r['name'];
	}

	# Get Permissions from DB
	$PERMISSIONS = array();
	foreach ($Database->get('permissions') as $p)
		$PERMISSIONS[$p['action']] = $p['minrole'];

	function PERM($perm, $compareAgainst = null){
		if (!is_string($perm)) return false;

		if (empty($compareAgainst)){
			global $signedIn, $currentUser;
			if (!$signedIn) return false;
			$checkRole = $currentUser['role'];
		}
		else $checkRole = $compareAgainst;

		global $ROLES, $PERMISSIONS;

		if (in_array($perm,$ROLES)) $targetRole = $perm;
		else if (!empty($PERMISSIONS[$perm])) $targetRole = $PERMISSIONS[$perm];
		else trigger_error('Invalid permission '.$perm);

		return array_search($checkRole,$ROLES) >= array_search($targetRole,$ROLES);
	}

	// Episode title matching pattern \\
	define('EP_TITLE_REGEX', '/^[A-Za-z \'\-!\d,&:?]{5,35}$/');

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

		if ($Ep['twoparter'])
			$Ep['episode'] = pad($EpNumber).pad($EpNumber+1);
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
	define('EPISODE_ID_PATTERN','S0?([1-8])E(0?[1-9]|1\d|2[0-6])(-(?:0[1-9]|1\d|2[0-6]))?(?:\D|$)');
	function episode_id_parse($id){
		$match = array();
		if (preg_match('/^'.EPISODE_ID_PATTERN.'/', $id, $match))
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
	 * @return array|null
	 */
	define('USERNAME_PATTERN', '([A-Za-z\-\d]{1,20})');
	function fetch_user($username){
		global $Database;

		if (!preg_match('/^'.USERNAME_PATTERN.'$/', $username))
			return null;

		$userdata = da_request('user/whois', array('usernames[0]' => $username));

		if (empty($userdata['results'][0]))
			return null;

		$userdata = $userdata['results'][0];

		$insert = array(
			'id' => strtolower($userdata['userid']),
			'name' => $userdata['username'],
			'avatar_url' => $userdata['usericon'],
		);

		if (!$Database->insert('users',$insert))
			return null;

		LogAction('userfetch',array('userid' => $insert['id']));

		return get_user($insert['name'], 'name');
	}

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
		global $Database;

		if ($coloumn === "access"){
			$Auth = $Database->where('access', $value)->getOne('sessions');

			if (empty($Auth)) return null;
			$coloumn = 'id';
			$value = $Auth['user'];
		}

		if (empty($dbcols)){
			$User = $Database->rawQuerySingle(
				"SELECT
					users.*,
					roles.label as rolelabel
				FROM users
				LEFT JOIN roles ON roles.name = users.role
				WHERE users.`$coloumn` = ?",array($value));

			if (empty($User) && $coloumn === 'name')
				$User = fetch_user($value);

			if (!empty($User) && isset($Auth)) $User['Session'] = $Auth;
		}
		else $User = $Database->where($coloumn, $value)->getOne('users',$dbcols);

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
	 * deviantArt profile link generator
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
		if (!is_array($User)) trigger_error('$User is not an array');

		$Username = $User['name'];
		$username = strtolower($Username);
		$avatar = $format == FULL ? "<img src='{$User['avatar_url']}' class=avatar> " : '';
		$link = "http://$username.deviantart.com/";

		if ($format === LINK_ONLY) return $link;
		return "<a href='$link' class=da-userlink>$avatar<span class=name>$Username</span></a>";
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
		$Username = $User['name'];

		$avatar = $format == FULL ? "<img src='{$User['avatar_url']}' class=avatar> " : '';

		return "<a href='/u/$Username' class=da-userlink>$avatar<span class=name>$Username</span></a>";
	}

	// Reserved by section creator \\
	function get_reserver_button($By = null, $R = false){
		global $signedIn, $currentUser;

		if ($By === false) return PERM('reservations.create') ? "<button class='reserve-request typcn typcn-user-add'>Reserve</button>" : '';
		if (empty($By) || $By === true){
			if (!$signedIn) trigger_error('Trying to get reserver button while not signed in');
			$By = $currentUser;
		}
		$dAlink = profile_link($By, FULL);

		$HTML =  "<div class=reserver>$dAlink</div>";
		$sameUser = $signedIn && $By['id'] === $currentUser['id'];

		$finished = !!$R['finished'];
		$Buttons = array();
		if (!$finished && ($sameUser || PERM('inspector'))){
			$Buttons[] = array('user-delete red cancel', 'Cancel');
			$Buttons[] = array('attachment green finish', ($sameUser ? "I'm" : 'Mark as').' finished');
		}
		if ($finished && PERM('inspector')){
			$Buttons[] = array((empty($R['preview'])?'trash delete-only red':'media-eject orange').' unfinish',empty($R['preview'])?'Delete':'Un-finish');
		}

		if (!empty($Buttons)){
			$HTML .= '<div class=reserver-actions>';
			foreach ($Buttons as $b)
				$HTML .= "<button class='typcn typcn-{$b[0]}'>{$b[1]}</button> ";
			$HTML .= '</div>';
		}

		return $HTML;
	}

	// List ltem generator function for request & reservation renderers \\
	function get_r_li($R, $isRequest = false){
		$finished = !!$R['finished'];
		$thing = $isRequest ? 'request' : 'reservation';
		$HTML = "<li id=$thing-{$R['id']}>";
		$R['label'] = htmlspecialchars($R['label']);
		$Image = "<div class='image screencap'><a href='{$R['fullsize']}'><img src='{$R['preview']}'></a></div>";
		if (!empty($R['label'])) $Image .= "<span class=label>{$R['label']}</span>";

		if ($isRequest && PERM('inspector'))
			$Image .= "<em>Added by ".profile_link(get_user($R['requested_by'])).' '.timetag($R['posted'])."</em>";

		if (empty($R['reserved_by'])){
			$HTML .= $Image;
			if ($isRequest)
				$HTML .= get_reserver_button(false);
			if ($isRequest && PERM('reservations.create'))
				$HTML .= "<button class='typcn typcn-trash red delete'>Delete</button>";
		}
		else {
			$R['reserver'] = get_user($R['reserved_by']);
			if ($finished){
				$D = da_cache_deviation($R['deviation_id']);
				$D['title'] = preg_replace("/'/",'&apos;',$D['title']);
				$Image = "<div class='image deviation'><a href='http://fav.me/{$D['id']}'><img src='{$D['preview']}' alt='{$D['title']}'></a></div>";
			}
			$HTML .= $Image.get_reserver_button($R['reserver'], $R);
		}

		return "$HTML</li>";
	}

	// Get Request / Reservation Submission Form HTML \\
	function get_post_form($type){
		$Type = strtoupper($type[0]).substr($type,1);
		$optional = $type === 'reservation' ? 'optional, ' : '';
		$HTML = <<<HTML

		<form class="hidden post-form" data-type="$type">
			<h2>Make a $type</h2>
			<div>
				<label>
					<span>$Type description ({$optional}3-255 chars)</span>
					<input type="text" name="label" pattern="^.{3,255}$" maxlength=255 required>
				</label>
				<label>
					<span>Image URL</span>
					<input type="text" name="image_url" pattern="^.{2,255}$" required>
					<button class="check-img red typcn typcn-arrow-repeat">Check image</button>
				</label>
				<div class="img-preview">
					<div class="notice fail">Please click the <strong>Check image</strong> button after providing an URL to get a preview & verify if the link is correct.<br>Supported providers: deviantArt, Sta.sh, Imgur, Derpibooru</div>
				</div>

HTML;
			if ($type === 'request')
				$HTML .= <<<HTML
				<label>
					<span>$Type type</span>
					<select name="type" required>
						<option value="" style=display:none selected>Choose one</option>
						<optgroup label="$Type types">
							<option value=chr>Character</option>
							<option value=bg>Background</option>
							<option value=obj>Object</option>
						</optgroup>
					</select>
				</label>

HTML;
			$HTML .= <<<HTML
			</div>
			<button class=green>Submit $type</button> <button type="reset">Cancel</button>
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
				$k = (!$R['finished']?'un':'').'finished';
				if (!$returnArranged)
					$Arranged[$k] .= get_r_li($R);
				else $Arranged[$k][] = $R;
			}
		}

		if ($returnArranged) return $Arranged;

		if (PERM('reservations.create')){
			$makeRes = '<button id="reservation-btn" class=green>Make a reservation</button>';
			$resForm = get_post_form('reservation');

		}
		else $resForm = $makeRes = '';
		$addRes = PERM('inspector') ? '<button id="add-reservation-btn" class=darkblue>Add a reservation</button>' :'';

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

		$Arranged = array(
			'finished' => !$returnArranged ? '' : array(),
			'unfinished' => array(),
		);
		$Arranged['unfinished']['bg'] =
		$Arranged['unfinished']['obj'] =
		$Arranged['unfinished']['chr'] = $Arranged['finished'];
		if (!empty($Requests) && is_array($Requests)){
			foreach ($Requests as $R){
				$HTML = !$returnArranged ? get_r_li($R,true) : $R;

				if (!$returnArranged){
					if ($R['finished'])
						$Arranged['finished'] .= $HTML;
					else $Arranged['unfinished'][$R['type']] .= $HTML;
				}
				else {
					if ($R['finished'])
						$Arranged['finished'][] = $HTML;
					else $Arranged['unfinished'][$R['type']][] = $HTML;
				}
			}
		}
		if (!$returnArranged){
			$Groups = '';
			foreach ($Arranged['unfinished'] as $g => $c)
				$Groups .= "<div class=group><h3>{$REQUEST_TYPES[$g]}:</h3><ul>{$c}</ul></div>";
		}

		if ($returnArranged) return $Arranged;

		if (PERM('user')){
			$makeRq = '<button id="request-btn" class=green>Make a request</button>';
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
			'SELECT *,
				IF(!ISNULL(r.deviation_id) && !ISNULL(r.reserved_by), 1, 0) as finished
			FROM `coloumn` r
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
		$Links = $Database->get('usefullinks');

		$Render = array();
		foreach ($Links as $l){
			if (!PERM($l['minrole'])) continue;

			if (!empty($l['title'])){
				$title = str_replace("'",'&apos;',$l['title']);
				$title = "title='$title'";
			}
			else $title = '';
			$Render[] =  "<li><a href='{$l['url']}' $title>{$l['label']}</a></li>";
		}
		if (!empty($Render))
			echo '<ul class="links">'.implode('',$Render).'</ul>';
	}
	
	// Renders the user card \\
	define('GUEST_AVATAR','/img/guest.png');
	function usercard_render(){
		global $signedIn, $currentUser;
		if ($signedIn){
			$avatar = $currentUser['avatar_url'];
			$un = $currentUser['name'];
			$username = "<a href='/u/$un'>$un</a>";
			$rolelabel = $currentUser['rolelabel'];
			$Avatar = get_avatar_wrap($currentUser);
		}
		else {
			$avatar = GUEST_AVATAR;
			$username = 'Curious Pony';
			$rolelabel = 'Guest';
			$Avatar = get_avatar_wrap(array(
				'avatar_url' => $avatar,
				'name' => $username,
				'rolelabel' => $rolelabel,
				'guest' => true,
			));
		}

		echo <<<HTML
		<div class=usercard>
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
		return preg_replace('/(?:^|\s)([A-Z])|./','$1',$label);
	}

	// Renders avatar wrapper for a specific user \\
	function get_avatar_wrap($User){
		$badge = '';
		if (empty($User['guest']))
			$badge = "<span class=badge>".label_to_initials($User['rolelabel'])."</span>";
		return "<div class=avatar-wrap><img src='{$User['avatar_url']}' class=avatar>$badge</div>";
	}

	/**
	 * Adds airing-.related information to an episodes table row
	 *
	 * @param array $Episode
	 *
	 * @return array
	 */
	function add_episode_airing_data($Episode){
		$airtime = strtotime($Episode['airs']);
		$Episode['displayed'] = strtotime('-24 hours', $airtime) < time();
		$Episode['aired'] = strtotime('+'.(!$Episode['twoparter']?30:60).' minutes', $airtime) < time();
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

		$eps = $Database->orderBy('season')->orderBy('episode')->get('episodes',$count);
		foreach ($eps as $i => $ep)
			$eps[$i] = add_episode_airing_data($ep);
		return $eps;
	}

	/**
	 * Returns the last episode aired from the db
	 *
	 * @return array
	 */
	function get_latest_episode(){
		global $Database;
		return $Database->singleRow(get_episodes(1,'airs < NOW() - INTERVAL -24 HOUR'));
	}

	/**
	 * Get the <tbody> contents for the episode list table
	 *
	 * @param array|null $Episodes
	 *
	 * @return string
	 */
	define('NOW', time());
	function get_eptable_tbody($Episodes = null){
		if (!isset($Episodes)) $Episodes = get_episodes();

		if (empty($Episodes)) return "<tr class='empty align-center'><td colspan=3><em>There are no episodes to display</em></td></tr>";

		$Body = '';
		$PathStart = '/episode/';
		$displayed = false;
		foreach ($Episodes as $i => $ep) {
			$Title = format_episode_title($ep, AS_ARRAY);
			$href = $PathStart.$Title['id'];
			$adminControls = '';
			if (PERM('episodes.manage')) $adminControls = <<<HTML
<span class=admincontrols>
	<button class="edit-episode typcn typcn-spanner blue" title="Edit episode"></button>
	<button class="delete-episode typcn typcn-times red" title="Delete episode"></button>
</span>
HTML;

			$star = '';
			if (!$displayed && $ep['displayed']){
				$displayed = true;
				$star = '<span class="typcn typcn-eye" title="Curently visible on the homepage"></span> ';
			}
			$star .= '<span class="typcn typcn-media-play'.(!$ep['aired']?'-outline':'').'" title="Episode had'.($ep['aired']?' aired, voting enabled':'n\'t aired yet, voting disabled').'"></span> ';

			$airs = timetag($ep['airs'], EXTENDED, NO_DYNTIME);

			$Body .= <<<HTML
		<tr data-epid="{$Title['id']}">
			<td class=season rowspan=2>{$Title['season']}</td>
			<td class=episode rowspan=2><span>{$Title['episode']}</span></td>
			<td class=title>$star<a href="$href">{$Title['title']}</a>$adminControls</td>
		</tr>
		<tr><td class=airs>$airs</td></tr>
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
	 *
	 * @return array|null
	 */
	function get_real_episode($season, $episode){
		global $Database;

		$Ep1 = $Database->whereEp($season,$episode)->getOne('episodes');
		if (empty($Ep1)){
			$Part1 = $Database->whereEp($season,$episode-1)->getOne('episodes');
			return !empty($Part1) && isset($Part1['twoparter']) && !!$Part1['twoparter'] ? $Part1 : null;
		}
		else return add_episode_airing_data($Ep1);
	}

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
	 * Parse session array for user page
	 */
	define('CURRENT',true);
	function render_session_li($Session, $current = false){
		$browserClass = preg_replace('/[^a-z]/','',strtolower($Session['browser_name']));
		$browserTitle = "{$Session['browser_name']} {$Session['browser_ver']}".($current?' (current)':'');
		$firstuse = timetag($Session['created']);
		$lastuse = timetag($Session['lastvisit']);
		$signoutText = 'Sign out' . (!$current ? ' from this session' : '');
		$remover = "<button class='typcn typcn-arrow-back remove orange' title='$signoutText' data-sid={$Session['id']}></button>";
		echo <<<HTML
<li class="browser-$browserClass">
	<span class=browser>$remover $browserTitle</span>
	<span class=created>Created: $firstuse</span>
	<span class=used>Last used: $lastuse</span>
</li>
HTML;
	}

	// Checks the image which allows a request to be finished
	$POST_TYPES = array('request','reservation');
	function check_request_finish_image(){
		global $POST_TYPES, $Database;
		if (!isset($_POST['deviation']))
			respond('Please specify a deviation URL');
		$deviation = $_POST['deviation'];
		try {
			require 'includes/Image.php';
			$Image = new Image($deviation);

			if ($Image->provider !== 'fav.me')
				respond('The finished vector must be uploaded to deviantArt, '.$Image->provider.' links are not allowed');

			foreach ($POST_TYPES as $what){
				if ($Database->where('deviation_id', $Image->id)->has("{$what}s"))
					respond("This exact deviation has already been marked as the finished version of a different $what");
			}

			return array('deviation_id' => $Image->id);
		}
		catch (Exception $e){ respond($e->getMessage()); }
	}

	// Header link HTML generator
	define('HTML_ONLY', true);
	function get_header_link($item, $htmlOnly = false){
		global $currentSet;

		list($path, $label) = $item;
		$current = (!$currentSet || $htmlOnly === HTML_ONLY) && !!preg_match("~^$path($|/)~", $_SERVER['REQUEST_URI']);
		if ($current)
			$currentSet = true;
		$class = trim((!empty($item[2]) ? $item[2] : '').($current ? ' active' : ''));
		if (!empty($class))
			$class = " class='$class'";

		$href = $current && $htmlOnly !== HTML_ONLY ? '' : " href='$path'";
		$html = "<a$href>$label</a>";

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
	function get_episode_voting($Episode){
		if (!$Episode['aired'])
			return "<p>Voting will start ".timetag($Episode['willair']).", after the episode had aired.</p><p>When the countdown is over, the like/dislike buttons will appear automatically.</p>";
		global $Database, $signedIn;
		$HTML = '';

		$_bind = array($Episode['season'], $Episode['episode']);
		$_query = function($col,$as,$val = null){
			return "SELECT CAST(IFNULL($col,0) AS UNSIGNED INTEGER) as $as FROM episodes__votes WHERE ".(isset($val)?"vote = $val && ":'')."season = ? && episode = ?";
		};
		$VoteTally = $Database->rawQuerySingle($_query('COUNT(*)','total'), $_bind);
		$VoteTally = array_merge(
			$VoteTally,
			$Database->rawQuerySingle($_query('SUM(vote)','up',1), $_bind),
			$Database->rawQuerySingle($_query('ABS(SUM(vote))','down',-1), $_bind)
		);

		$HTML .= "<p>";
		if ($VoteTally['total'] > 0){
			$UpsDowns = $VoteTally['up'] > $VoteTally['down'] ? 'up' : 'down';
			if ($VoteTally['up'] === $VoteTally['total'] || $VoteTally['down'] === $VoteTally['total'])
				$Start = $VoteTally['total']." ".($VoteTally['total'] !== 1 ? 'ponies' : 'pony');
			else $Start = "{$VoteTally[$UpsDowns]} out of {$VoteTally['total']} ponies";
			$HTML .= "$Start ".($UpsDowns === 'down'?'dis':'')."liked this episode";
			if (PERM('user')) $UserVote = get_episode_user_vote($Episode);
			if (empty($UserVote)) $HTML .= ".";
			else $HTML .= ", ".(($UserVote['vote'] > 0 && $UpsDowns === 'up' || $UserVote['vote'] < 0 && $UpsDowns === 'down') ? 'including you' : 'but you didn\'t').".";
		}
		else $HTML .= 'Nopony voted yet.';
		$HTML .= "</p>";

		if ($VoteTally['total'] > 0){
			$fills = array();

			$upPerc = call_user_func($UpsDowns === 'up' ? 'ceil' : 'floor', ($VoteTally['up']/$VoteTally['total'])*10000)/100;
			$downPerc = call_user_func($UpsDowns === 'down' ? 'ceil' : 'floor', ($VoteTally['down']/$VoteTally['total'])*10000)/100;

			if ($upPerc > 0)
				$fills[] = "<div class=up style=width:$upPerc% ".($upPerc > 10 ? "data-width=$upPerc":'')."></div>";
			if ($downPerc > 0)
				array_splice($fills, $UpsDowns === 'up' ? 1 : 0, 0,array("<div class=down style=width:$downPerc% ".($downPerc > 10 ? "data-width=$downPerc":'')."></div>"));

			if (!empty($fills))
				$HTML .= "<div class=bar>".implode('',$fills)."</div>";
		}
		if (empty($UserVote)){
			$HTML .= "<br><p>What did <em>you</em> think about the episode?</p>";
			if ($signedIn)
				$HTML .= '<button class="typcn typcn-thumbs-up green">I liked it</button> <button class="typcn typcn-thumbs-down red">I disliked it</button>';
			else $HTML .= "<p><em>Sign in below to cast your vote!</em></p>";
		}

		return $HTML;
	}

	// Render upcoming episode HTML \\
	function get_upcoming_eps($Upcoming = null){
		if (empty($Upcoming)){
			global $Database;
			$Upcoming = $Database->where('airs > NOW()')->get('episodes');
		}
		$HTML = '';
		foreach ($Upcoming as $i => $ep){
			$airtime = strtotime($ep['airs']);
			$airs = date('c', $airtime);
			$month = date('M', $airtime);
			$day = date('j', $airtime);
			if ($i === 0){
				$diff = timeDifference(time(), $airtime);
				$time = 'in ';
				if (!empty($diff['day']))
					$time .=  "{$diff['day']} day".($diff['day']!==1?'s':'').' & ';
				if (!empty($diff['hour']))
					$time .= "{$diff['hour']}:";
				foreach (array('minute','second') as $k)
					$diff[$k] = pad($diff[$k]);
				$time = "<span class=countdown data-airs=\"$airs\">$time{$diff['minute']}:{$diff['second']} (".date('T', $airtime).")</span>";
			}
			else $time = timetag($ep['airs']);
			$HTML .= "<li><div class=calendar><span class=top>$month</span><span class=bottom>$day</span></div>".
				"<div class=meta><span class=title>{$ep['title']}</span>$time</div></li>";
		}
		return $HTML;
	}

	// Exporting all posts \\
	function export_posts($req, $res){
		global $REQUEST_TYPES;

		$res = reservations_render($res, RETURN_ARRANGED);
		$req = requests_render($req, RETURN_ARRANGED);
		$nada = 'None yet';

		$nameCache = array();

		$Export = "<h1>List of Reservations</h1>\n\n";
		if (empty($res['unfinished'])) $Export .= "\n$nada";
		else foreach ($res['unfinished'] as $r){
			if (empty($nameCache[$r['reserved_by']])){
				$u = get_user($r['reserved_by'],'id','name');
				$nameCache[$r['reserved_by']] = $u['name'];
			}
			$username = $nameCache[$r['reserved_by']];
			$Export .= "<div class=\"res-box\"> <a href=\"{$r['fullsize']}\"><img src=\"{$r['fullsize']}\"></a> by :icon$username: :dev$username:";
			if (!empty($r['label'])) $Export .= " - {$r['label']}";
			$Export .= "</div>\n";
		}
		$Export .= "\n\n<h1>Finished Reservations</h1>\n\n";
		if (empty($res['finished'])) $Export .= "\n$nada";
		else foreach ($res['finished'] as $r){
			if (empty($nameCache[$r['reserved_by']])){
				$u = get_user($r['reserved_by'],'id','name');
				$nameCache[$r['reserved_by']] = $u['name'];
			}
			$username = $nameCache[$r['reserved_by']];

			$thumbID = intval(substr($r['deviation_id'],1), 36);

			$Export .= "<div class=\"res-box\"> :thumb$thumbID: by :icon$username: :dev$username:</div>\n";
		}
		$Export .= "\n\n<h1>List of Requests</h1>";
		foreach ($req['unfinished'] as $g => $reqs){
			$Export .= "\n\n\n<h2>{$REQUEST_TYPES[$g]}:</h2>\n";
			if (empty($reqs)) $Export .= $nada;
			else foreach ($reqs as $r){
				$username = false;
				if (!empty($r['reserved_by'])){
					if (empty($nameCache[$r['reserved_by']])){
						$u = get_user($r['reserved_by'],'id','name');
						$nameCache[$r['reserved_by']] = $u['name'];
					}
					$username = $nameCache[$r['reserved_by']];
				}
				$Export .= "<div class=\"res-box\"> <a href=\"{$r['fullsize']}\"><img src=\"{$r['fullsize']}\"></a> - {$r['label']}".
					(!empty($username)?" <b>reserved by :icon$username: :dev$username:</b>":'')."</div>\n";
			}
		}
		$Export .= "\n\n<h1>Finished Requests</h1>\n\n";
		if (empty($req['finished'])) $Export .= "\n$nada";
		else foreach ($req['finished'] as $r){
			if (empty($nameCache[$r['reserved_by']])){
				$u = get_user($r['reserved_by'],'id','name');
				$nameCache[$r['reserved_by']] = $u['name'];
			}
			$username = $nameCache[$r['reserved_by']];

			$thumbID = intval(substr($r['deviation_id'],1), 36);

			$Export .= "<div class=\"res-box\"> :thumb$thumbID: by :icon$username: :dev$username:</div>\n";
		}
		return rtrim($Export);
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
     COUNT(*) as `count`
     FROM reservations res
     WHERE res.reserved_by = @id && res.deviation_id IS NULL)
    +(SELECT
      COUNT(*) as `count`
      FROM requests req
      WHERE req.reserved_by = @id && req.deviation_id IS NULL)
) as `count`
FROM `users` u
ORDER BY `count` DESC
	 */
	function res_limit_check(){
		global $Database, $currentUser;

		$reservations = $Database->rawQuerySingle(
			"SELECT
			(
			    (SELECT
			     COUNT(*) as `count`
			     FROM reservations res
			     WHERE res.reserved_by = u.id && res.deviation_id IS NULL)
			    +(SELECT
			      COUNT(*) as `count`
			      FROM requests req
			      WHERE req.reserved_by = u.id && req.deviation_id IS NULL)
			) as `count`
			FROM `users` u WHERE u.id = ?",
			array($currentUser['id'])
		);

		if (isset($reservations['count']) && $reservations['count'] >= 4)
			respond("You've already reserved {$reservations['count']} images in total, please finish or cancel some of them before making another reservation.<br>You may not have more than 4 unfinished reservations at a time.");
	}

	// Render episode video player \\
	$VIDEO_PROVIDER_NAMES = array(
		'yt' => 'YouTube',
		'dm' => 'Dailymotion',
	);
	function render_ep_video($CurrentEpisode){
		global $VIDEO_PROVIDER_NAMES, $Database;

		$HTML = '';

		$Videos = $Database
			->orderBy('provider', 'ASC')
			->whereEp($CurrentEpisode['season'],$CurrentEpisode['episode'])
			->get('episodes__videos');
		if (!empty($Videos)){
			require_once "includes/Video.php";
			$FirstVid = $Videos[0];
			$embed = Video::get_embed($FirstVid['id'], $FirstVid['provider']);
			$HTML .= "<section class=episode><h2>Watch the Episode</h2>";
			if (!empty($Videos[1])){
				$SecondVid = $Videos[1];
				$url = Video::get_embed($SecondVid['id'], $SecondVid['provider'], Video::URL_ONLY);
				$HTML .= "<p class=align-center style=margin-bottom:5px>If the video below goes down, <a href='$url' target=_blank>click here to watch it on {$VIDEO_PROVIDER_NAMES[$SecondVid['provider']]} instead</a>.</p>";
			}
			$HTML .= "<div class=responsive-embed>$embed</div></section>";
		}

		return $HTML;
	}
