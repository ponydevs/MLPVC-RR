<?php

	// Anti-CSRF
	$CSRF = RQMTHD !== 'POST' || !isset($_POST['CSRF_TOKEN']) || !Cookie::exists('CSRF_TOKEN') || $_POST['CSRF_TOKEN'] !== Cookie::get('CSRF_TOKEN');
	if (RQMTHD !== 'POST' && $CSRF)
		Cookie::set('CSRF_TOKEN',md5(time()+rand()),COOKIE_SESSION);
	define('CSRF_TOKEN',Cookie::get('CSRF_TOKEN'));

	$signedIn = false;
	if (Cookie::exists('access')){
		$authKey = Cookie::get('access');
		$currentUser = get_user($authKey,'access');

		if (!empty($currentUser['Session'])){
			if (strtotime($currentUser['Session']['expires']) < time()){
				da_get_token($currentUser['Session']['refresh'],'refresh_token');
			}

			$signedIn = true;
			$Database->where('user', $currentUser['id'])->update('sessions', array('lastvisit' => gmdate('c')));

			define('DEBUG', $currentUser['role'] === 'developer');
		}
		else {
			Cookie::delete('access');
			unset($currentUser);
		}
	}

	if (!defined('DEBUG')) define('DEBUG',false);