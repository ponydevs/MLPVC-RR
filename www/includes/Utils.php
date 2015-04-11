<?php

	# AJAX reply function (for JavaScript)
	function respond($m = 'You need to be signed in to use that.', $s = 0, $x = array()){
		header('Content-Type: application/json');
		die(json_encode(array_merge(array(
			"message" => $m,
			"status" => $s,
		),$x)));
	}

	# Logging (TODO)
	function naplo($type,$data = null){
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

		// Page heading
		if (isset($settings['heading']))
			$heading = $settings['heading'];

		// Alternate favicon
		if (isset($settings['favicon']))
			$faviconpath = $settings['favicon'];

		# CSS
		$DEFAULT_CSS = array('grid','theme','forms','dialog','colors');
		$customCSS = array();
		// Only add defaults when needed
		if (array_search('no-default-css',$settings) === false)
			$customCSS = array_merge($customCSS, $DEFAULT_CSS);

		# JavaScript
		$DEFAULT_JS = array('dyntime','dialog','jquery.smoothWheel');
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

		# Putting it together
		/* Together, we'll always shine! */
		$view = $settings['view'];
		$viewPath = "views/{$view}.php";
		// Import global variables
		foreach ($GLOBALS as $nev => $ertek)
			if (!isset($$nev))
				$$nev = $ertek;

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

	// oAuth Responses \\
	$OAUTH_RESPONSE = array(
		'invalid_request' => 'The authorization recest was not properly formatted.',
		'unsupported_response_type' => 'The authorization server does not support obtaining an authorization code using this method.',
		'unauthorized_client' => 'The authorization process did not complete. Please try again.',
		'invalid_scope' => 'The requested scope is invalid, unknown, or malformed.',
		'server_error' => "There's an issue on deviantArt's end. Try again later.",
		'temporarily_unavailable' => "There's an issue on deviantArt's end. Try again later.",
	);

	function oauth_redirect_uri($state = true){
		global $do, $data;
		if ($do === 'index' && empty($data)) $returnURL = RELPATH;
		else $returnURL = rtrim("/$do/$data",'/');
		return '&redirect_uri='.urlencode(ABSPATH."da-auth").($state?'&state='.urlencode($returnURL):'');
	}

	function da_request($url, $postdata = null, $token = null){
		global $signedIn, $currentUser;

		if (empty($token)){
			if (!$signedIn){
				$err = 'Trying to make a request without signing in';
				die(trigger_error($err));
			}
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
			$Database->insert('users', array_merge($data, array('id' => $userdata['userid'])));
		else $Database->where('id',$userdata['userid'])->update('users', $data);

		Cookie::set('access_token',$data['access_token'],THREE_YEARS);
	}

	// Compare Ranks \\
	if (isset($Database)){
		$_RoleData = $Database->orderBy('value','ASC')->get('roles');
		$POSSIBLE_ROLES_ASSOC = array();
		$POSSIBLE_ROLES = array();
		foreach ($_RoleData as $r){
			$POSSIBLE_ROLES_ASSOC[$r['name']] = $r['label'];
			$POSSIBLE_ROLES[] = $r['name'];
		}

		function rankCompare($left,$right,$canEqual = false){
			global $POSSIBLE_ROLES;
			if ($canEqual)
				return array_search($left,$POSSIBLE_ROLES) <= array_search($right,$POSSIBLE_ROLES);
			else
				return array_search($left,$POSSIBLE_ROLES) < array_search($right,$POSSIBLE_ROLES);
		}
		unset($_RoleData);
	}

	// Format Episode Title \\
	function format_episode_title($Ep){
		$EpNumber = intval($Ep['episode']);
		if ($EpNumber <= 2) $Ep['episode'] = '0102';
		else if ($EpNumber >= 25) $Ep['episode'] = '2526';
		return "S{$Ep['season']}E{$Ep['episode']}: {$Ep['title']}";
	}