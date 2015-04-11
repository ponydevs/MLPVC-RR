<?php

	// Anti-CSRF
	$CSRF = RQMTHD !== 'POST' || !isset($_POST['CSRF_TOKEN']) || !Cookie::exists('CSRF_TOKEN') || $_POST['CSRF_TOKEN'] !== Cookie::get('CSRF_TOKEN');
	if (RQMTHD !== 'POST' && $CSRF)
		Cookie::set('CSRF_TOKEN',md5(time()+rand()),COOKIE_SESSION);
	define('CSRF_TOKEN',Cookie::get('CSRF_TOKEN'));

	$signedIn = false;
	if (Cookie::exists('access_token')){
		$authKey = Cookie::get('access_token');
		$currentUser = getUser($authKey,'access_token');

		if (!empty($currentUser)){
			if (strtotime($currentUser['token_expires']) < time()){
				da_get_token($currentUser['refresh_token'],'refresh_token');
			}
			$signedIn = true;

			define('DEBUG', $currentUser['role'] === 'developer');
		}
		else {
			Cookie::delete('access_token');
			unset($currentUser);
		}
	}

	if (!defined('DEBUG')) define('DEBUG',false);