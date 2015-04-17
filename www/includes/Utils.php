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
	function respond($m = 'You need to be signed in to use that.', $s = false, $x = array()){
		header('Content-Type: application/json');
		die(json_encode(array_merge(array(
			"message" => $m,
			"status" => $s,
		),$x)));
	}

	# Logging (TODO)
	function LogAction($type,$data = null){
		global $Database, $signedIn;
		$central = array('ip' => $_SERVER['REMOTE_ADDR']);

		if (isset($data)){
			foreach ($data as $k => $v)
				if (is_bool($v))
					$data[$k] = $v ? 1 : 0;

			$refid = $Database->insert("log_$type",$data,true);
		}

		$central['reftype'] = $type;
		if (isset($refid) && $refid > 0){
			$central['refid'] = $refid;
		}
		else if (!isset($data)){
			$central['refid'] = 0;
		}
		else return false;
		if ($signedIn)
			$central['initiator'] = $GLOBALS['currentUser']['id'];
		$Database->insert("log_central",$central);
	}

	// Page loading fornction
	function loadPage($settings){
		// Page <title>
		if (isset($settings['title']))
			$title = $settings['title'];

		# CSS
		$DEFAULT_CSS = array('theme','forms','dialog','colors');
		$customCSS = array();
		// Only add defaults when needed
		if (array_search('no-default-css',$settings) === false)
			$customCSS = array_merge($customCSS, $DEFAULT_CSS);

		# JavaScript
		$DEFAULT_JS = array('dyntime','dialog','quotes','jquery.smoothWheel');
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
	function array_random($array, $num = 1){ return $array[array_rand($array, $num)]; }

	// Time validator \\
	function is_time_string($date){
		return strtotime($date) != false;
	}

	// Color padder \\
	function clrpad($c){
		if (strlen($c) === 3) $c = $c[0].$c[0].$c[1].$c[1].$c[2].$c[2];
		return $c;
	}

	// Redirection \\
	function redirect($url, $die = true){
		header("Location: $url");
		if ($die) die();
	}

	// Number padder \\
	/**
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
	function statusCodeHeader($code){
		global $HTTP_STATUS_CODES;

		if (!isset($HTTP_STATUS_CODES[$code]))
			trigger_error('Érvénytelen státuszkód: '.$code,E_USER_ERROR);
		else
			header($_SERVER['SERVER_PROTOCOL'].' '.$code.' '.$HTTP_STATUS_CODES[$code]);
	}

	// DJDavid98(TM) Path Notation Resolver \\
	function djpth($path = null,$relative = false){
		if (empty($path) || !is_string($path)) return RELPATH;
		// Parse "current directory" mark
		$path = preg_replace('/\\|/','./',$path);
		// Avoid changing relative path to absolute
		$path = preg_replace('/^</','../',$path);
		// Replace other occurences of the "up" mark
		$path = preg_replace('/</','/../',$path);
		// Add basic dividers
		$path = preg_replace('/>/','/',$path);
		// Replace double slashes not at the beginning of the string
		$_begins_with_dblslash = strpos($path,'//') === 0;
		if ($_begins_with_dblslash) $path = substr($path,2);
		while (strpos($path,'\/\/') !== false) $path = preg_replace('/\/\//','/',$path);
		if ($_begins_with_dblslash) $path = '//'.$path;
		// Resolve path
		$resolved_path = explode('/',(!$relative?RELPATH:'').$path);
		$removed = 0;
		foreach ($resolved_path as $index => $part){
			if ($part == '..'){
				array_splice($resolved_path,$index-1,2);
				$removed++;
			}
			else if (($question_mark = strpos($part,'?')) !== 0){
				if ($question_mark !== false){
					$resolved_path[$index-($removed*2)] = preg_replace('/\+/','%20',urlencode(substr($part,0,$question_mark))).substring($part,$question_mark);
				}
				else $resolved_path[$index-($removed*2)] = preg_replace('/\+/','%20',urlencode($part));
			}
		}
		$path = implode('/',$resolved_path);
		return $path;
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
	 * @param string $url
	 * @param null|array $postdata
	 * @param null|string $token
	 * @return array
	 */
	function da_request($url, $postdata = null, $token = null){
		global $signedIn, $currentUser;

		if (empty($token)){
			if (!$signedIn) die(trigger_error('Trying to make a request without signing in'));

			$token = $currentUser['access_token'];
		}

		$r = curl_init($url);
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
	 * Returns the first element of an array of arrays
	 *  returned by the MySqlidb::query method
	 *  as received through $query
	 * Returns null if the query contains no results
	 *
	 * @param null|array $query
	 * @return null|array
	 */
	function rawquery_get_single_result($query){
		if (empty($query[0])) return null;
		else return $query[0];
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

		$userdata = da_request('https://www.deviantart.com/api/v1/oauth2/user/whoami', null, $json['access_token']);

		$data = array(
			'username' => $userdata['username'],
			'avatar_url' => $userdata['usericon'],
			'access_token' => $json['access_token'],
			'refresh_token' => $json['refresh_token'],
			'token_expires' => date('c',time()+intval($json['expires_in']))
		);

		if (empty($Database->where('id',$userdata['userid'])->get('users')))
			$Database->insert('users', array_merge($data, array('id' => strtolower($userdata['userid']))));
		else $Database->where('id',$userdata['userid'])->update('users', $data);

		Cookie::set('access_token',$data['access_token'],THREE_YEARS);
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

		return array_merge(json_decode(file_get_contents('http://backend.deviantart.com/oembed?url='.urlencode("http://$type/$ID")), true),array('_provider' => $type));
	}

	/**
	 * Caches information about a deviation in the 'deviation_cache' table
	 * Returns null on failure
	 *
	 * @param string $ID
	 * @param null|string $type
	 * @return array|null
	 */
	$PROVIDER_FULLSIZE_KEY = array(
		'sta.sh' => 'url',
		'fav.me' => 'fullsize_url',
	);
	function da_cache_deviation($ID, $type = null){
		global $Database, $PROVIDER_FULLSIZE_KEY;

		$Deviation = $Database->where('id',$ID)->getOne('deviation_cache');
		if (empty($Deviation) || (!empty($Deviation['updated_on']) && strtotime($Deviation['updated_on'])+ONE_HOUR < time())){
			$json = da_oembed($ID, $type);
			if (empty($json)) return null;

			$Deviation = array(
				'title' => $json['title'],
				'preview' => $json['thumbnail_url'],
				'fullsize' => $json[$PROVIDER_FULLSIZE_KEY[$json['_provider']]],
				'provider' => $json['_provider'],
			);

			if (!empty($Deviation)){
				$Deviation['id'] = $ID;
				$Database->insert('deviation_cache', $Deviation);
			}
			else $Database->where('id',$Deviation['id'])->update('deviation_cache', $Deviation);
		}

		return $Deviation;
	}

	// Check Permissions \\
	# Temporary function to only fetch role data from DB once, and only when needed
	$PERM = function($perm, $reload = true){
		# Make global variables for $PERM
		global $Database, $ROLES, $ROLES_ASSOC, $PERMISSIONS, $PERM, $PERM_RELOAD;

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

		$PERM_RELOAD = $PERM;
		$PERM = function($perm, $reload = false){
			if ($reload){
				global $PERM_RELOAD;
				$PERM_RELOAD($perm, $reload);
			}
			global $signedIn, $currentUser, $ROLES, $PERMISSIONS;

			if (!$signedIn) return false;

			if (!empty($PERMISSIONS[$perm])) $targetRole = $PERMISSIONS[$perm];
			else if (in_array($perm,$ROLES)) $targetRole = $perm;
			else return false;

			return array_search($currentUser['role'],$ROLES) >= array_search($targetRole,$ROLES);
		};
		return $PERM($perm);
	};
	function PERM($perm){
		global $PERM;
		return $PERM($perm);
	}

	/**
	 * Turns an 'episode' database row into a readable title
	 * The episode numbers 1-2 & 25-26 will be represented with both numbers
	 *
	 * @param array $Ep
	 * @return string
	 */
	function format_episode_title($Ep){
		$EpNumber = intval($Ep['episode']);
		if ($EpNumber <= 2) $Ep['episode'] = '0102';
		else if ($EpNumber >= 25) $Ep['episode'] = '2526';
		else $Ep['episode'] = pad($Ep['episode']);
		$Ep['season'] = pad($Ep['season']);
		return "S{$Ep['season']}E{$Ep['episode']}: {$Ep['title']}";
	}

	/**
	 * User Information Retriever
	 * --------------------------
	 * Gets a single row from the 'users' database
	 *  where $coloumn is equal to $value
	 * Returns null if user is not found
	 *
	 * If $basic is set to false, then role
	 *  information will also be fetched
	 *
	 * @param string $value
	 * @param string $coloumn
	 * @param bool $basic
	 * @return array|null
	 */
	define('GETUSER_BASIC', true);
	function get_user($value, $coloumn = 'id', $basic = false){
		global $Database;

		if ($basic !== false) return $Database->where($coloumn, $value)->getOne('users','id, username, avatar_url');
		else return rawquery_get_single_result($Database->rawQuery(
			"SELECT
				users.*,
				roles.label as rolelabel
			FROM users
			LEFT JOIN roles ON roles.name = users.role
			WHERE `$coloumn` = ?",array($value)));
	}


	// Get Request / Reservation Submission Form HTML \\
	function post_form_html($type){
		$Type = strtoupper($type[0]).substr($type,1);
		$HTML = <<<HTML
		<form class="hidden post-form" data-type="$type">
			<h2>Make a $type</h2>
			<div>
				<label>
					<span>Image URL</span>
					<input type="text" name="image_url" pattern="^.{2,255}$" required>
					<button class="check-img red">Check image</button>
				</label>
				<div class="hidden img-preview">
					<div class="notice fail">Please click the <strong>Check image</strong> button after providing an URL to get a preview & verify if the link is correct.</div>
				</div>
				<label>
					<span>$Type label</span>
					<input type="text" name="label" pattern="^.{2,255}$" required>
				</label>
HTML;
			if ($type === 'request')
				$HTML .= <<<HTML
				<label>
					<span>$Type type</span>
					<select name="type" required>
						<option value=chr>Character</option>
						<option value=bg>Background</option>
						<option value=obj>Object</option>
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
	function reservations_render($Reservations){
		$Arranged = array();
		$Arranged['unfinished'] =
		$Arranged['finished'] = '';
		if (!empty($Reservations) && is_array($Reservations)){

			foreach ($Reservations as $R){
				$finished = !!$R['finished'] && !empty($R['deviation_id']);
				$BaseString = "<a href='{$R['fullsize']}'><img src='{$R['preview']}' class=screencap></a><span>{$R['label']}</span>";

				$ResHTML = '<li>';
				if (!empty($R['reserved_by'])){
					$R['reserver'] = get_user($R['reserved_by'],'id',GETUSER_BASIC);
					$R['reserver']['avatar_html'] = "<img src='{$R['reserver']['avatar_url']}' class=avatar>";
					$BySTR = " by {$R['reserver']['avatar_html']}{$R['reserver']['username']}";
					if (!$finished) $ResHTML .= "$BaseString <strong>reserved$BySTR</strong>";
					else {
						$D = da_cache_deviation($R['deviation_id']);
						$D['title'] = preg_replace("/'/",'&apos;',$D['title']);
						$ResHTML .= "<a href='http://fav.me/{$D['id']}'><img src='{$D['preview']}' alt='{$D['title']}' class=deviation></a>$BySTR";
					}
				}
				else $ResHTML .= $BaseString;
				$ResHTML .= '</li>';

				$Arranged[(!$finished?'un':'').'finished'] .= $ResHTML;
			}
		}

		if (PERM('reservations.create')){
			$makeRes = '<button id="reservation-btn">Make a reservation</button>';
			$resForm = post_form_html('reservation');
		}
		else $resForm = $makeRes = '';

		echo <<<HTML
	<section id="reservations">
		<div class="unfinished">
			<h2>List of Reservations$makeRes</h2>
			<ul>{$Arranged['unfinished']}</ul>
		</div>
		<div class="finished">
			<h2>Finished Reservations</h2>
			<ul>{$Arranged['finished']}</ul>
		</div>$resForm
	</section>
HTML;
	}

	// Render Requests HTML \\
	$REQUEST_TYPES = array(
		'chr' => 'Charcaters',
		'obj' => 'Objects',
		'bg' => 'Backgrounds',
	);
	function requests_render($Requests){
		global $REQUEST_TYPES;

		$Arranged = array();
		if (!empty($Requests) && is_array($Requests)){
			$Arranged['unfinished'] = array();
			$Arranged['unfinished']['bg'] =
			$Arranged['unfinished']['obj'] =
			$Arranged['unfinished']['chr'] =
			$Arranged['finished'] = '';

			foreach ($Requests as $R){
				$finished = !!$R['finished'] && !empty($R['deviation_id']);
				$BaseString = "<a href='{$R['fullsize']}'><img src='{$R['preview']}' class=screencap></a><span>{$R['label']}</span>";

				$RqHTML = '<li>';
				if (!empty($R['reserved_by'])){
					$R['reserver'] = get_user($R['reserved_by'],'id',GETUSER_BASIC);
					$R['reserver']['avatar_html'] = "<img src='{$R['reserver']['avatar_url']}' class=avatar>";
					$BySTR = " by {$R['reserver']['avatar_html']}{$R['reserver']['username']}";
					if (!$finished) $RqHTML .= "$BaseString <strong>reserved$BySTR</strong>";
					else {
						$D = da_cache_deviation($R['deviation_id']);
						$D['title'] = preg_replace("/'/",'&apos;',$D['title']);
						$RqHTML .= "<a href='http://fav.me/{$D['id']}'><img src='{$D['preview']}' alt='{$D['title']}' class=deviation></a>$BySTR";
					}
				}
				else $RqHTML .= $BaseString;
				$RqHTML .= '</li>';

				if ($finished)
					$Arranged['finished'] .= $RqHTML;
				else {
					$Arranged['unfinished'][$R['type']] .= $RqHTML;
				}
			}

			$Groups = '';
			foreach ($Arranged['unfinished'] as $g => $c)
				$Groups .= "<div class=group><h3>{$REQUEST_TYPES[$g]}:</h3><ul>{$c}</ul></div>";
		}
		else {
			$Groups = '<ul></ul>';
			$Arranged['finished'] = '';
		}

		if (PERM('user')){
			$makeRq = '<button id="request-btn">Make a request</button>';
			$reqForm = post_form_html('request');
		}
		else $reqForm = $makeRes = '';
		
		echo <<<HTML
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
	 * Renders a sidebar link <li> item
	 *
	 * Accepts:
	 *   1) An URL, text and an optional title for the link
	 *   2) An array of arrays. The arrays should contain
	 *      / the elements of harmony. ...ahem, I mean... /
	 *      the elements:
	 *        0: url
	 *        1: text
	 *        2: title
	 *
	 * @param mixed $url
	 * @param string $text
	 * @param string $title
	 */
	function sidebar_link_render($url, $text = '', $title = null){
		$render = function($u, $tx, $tt){
			if (!preg_match('~^https?://~',$u)) $u = djpth($u);
			$tt = str_replace("'",'&apos;',$tt);
			echo "<li><a href='$u' title='$tt'>$tx</a></li>";
		};
		if (is_array($url) && empty($text) && empty($title))
			foreach ($url as $l) $render($l[0], $l[1], isset($l[2])?$l[2]:null);
		else $render($url, $text, $title);
	}

	function sidebar_links_render(){
		echo '<ul class="links">';
		// Member only links
		if (PERM('member'))
			sidebar_link_render(array(
				array(EP_DL_SITE,'Episode downloads','Download iTunes RAW 1080p episodes for best screencaps')
			));
		echo '</ul>';
	}
	
	// Renders the user card in the sidebar \\
	define('GUEST_AVATAR',djpth('img>favicon.png'));
	function usercard_render(){
		global $signedIn, $currentUser;
		if ($signedIn){
			$avatar = $currentUser['avatar_url'];
			$username = $currentUser['username'];
			$rolelabel = $currentUser['rolelabel'];
		}
		else {
			$avatar = GUEST_AVATAR;
			$username = 'Curious Pony';
			$rolelabel = 'Guest';
		}

		if (PERM('member')){
			$groupInitials = preg_replace('/[^A-Z]/','',$rolelabel);
			$badge = "<span class=badge>$groupInitials</span>";
		}
		else $badge = '';
		
		echo <<<HTML
		<div class="usercard">
			<div class="avatar-wrap">
				<img src="$avatar" class=avatar>$badge
			</div>
			<span class="un">$username</span>
			<span class="role">$rolelabel</span>
		</div>
HTML;
	}