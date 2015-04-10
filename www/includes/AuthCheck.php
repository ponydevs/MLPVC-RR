<?php

	// Anti-CSRF
	$CSRF = RQMTHD !== 'POST' || !isset($_POST['CSRF_TOKEN']) || !Cookie::exists('CSRF_TOKEN') || $_POST['CSRF_TOKEN'] !== Cookie::get('CSRF_TOKEN');
	if (RQMTHD !== 'POST' && $CSRF)
		Cookie::set('CSRF_TOKEN',md5(time()+rand()),COOKIE_SESSION);
	define('CSRF_TOKEN',Cookie::get('CSRF_TOKEN'));

	$signedIn = false;
	if (Cookie::exists('access_token')){
		$authKey = Cookie::get('access_token');
		$currentUser = $Database->rawQuery(
			"SELECT
				users.*,
				roles.label as rolelabel
			FROM users
			LEFT JOIN roles ON roles.name = users.role
			WHERE access_token = ?",array($authKey));

		if (!empty($currentUser[0])){
			$currentUser = $currentUser[0];
			if (strtotime($currentUser['token_expires']) < time()){
				da_get_token($currentUser['refresh_token'],'refresh_token');
			}
			$signedIn = true;
		}
		else {
			Cookie::delete('access_token');
			unset($currentUser);
		}
	}