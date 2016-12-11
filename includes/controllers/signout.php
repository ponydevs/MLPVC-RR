<?php

use App\Cookie;
use App\CSRFProtection;
use App\DeviantArt;
use App\Exceptions\CURLRequestException;
use App\Permission;
use App\Response;
use App\Users;
use App\Models\User;

/** @var $signedIn bool */

if (!$signedIn) Response::success("You've already signed out");
CSRFProtection::protect();

if (isset($_REQUEST['unlink'])){
	try {
		DeviantArt::request('https://www.deviantart.com/oauth2/revoke', null, array('token' => $currentUser->Session['access']));
	}
	catch (CURLRequestException $e){
		Response::fail("Coulnd not revoke the site's access: {$e->getMessage()} (HTTP {$e->getCode()})");
	}
}

if (isset($_REQUEST['unlink']) || isset($_REQUEST['everywhere'])){
	$col = 'user';
	$val = $currentUser->id;
	$username = Users::validateName('username', null, true);
	if (isset($username)){
		if (!Permission::sufficient('staff') || isset($_REQUEST['unlink']))
			Response::fail();
		/** @var $TargetUser User */
		$TargetUser = $Database->where('name', $username)->getOne('users','id,name');
		if (empty($TargetUser))
			Response::fail("Target user doesn't exist");
		if ($TargetUser->id !== $currentUser->id)
			$val = $TargetUser->id;
		else unset($TargetUser);
	}
}
else {
	$col = 'id';
	$val = $currentUser->Session['id'];
}

if (!$Database->where($col,$val)->delete('sessions'))
	Response::fail('Could not remove information from database');

if (empty($TargetUser))
	Cookie::delete('access', Cookie::HTTPONLY);
Response::done();
