<?php

use App\Cookie;
use App\CSRFProtection;
use App\DeviantArt;
use App\Exceptions\cURLRequestException;
use App\Permission;
use App\Response;
use App\Users;
use App\User;

if (!$signedIn) Response::Success("You've already signed out");
CSRFProtection::Protect();

if (isset($_REQUEST['unlink'])){
	try {
		DeviantArt::Request('https://www.deviantart.com/oauth2/revoke', null, array('token' => $currentUser->Session['access']));
	}
	catch (cURLRequestException $e){
		Response::Fail("Coulnd not revoke the site's access: {$e->getMessage()} (HTTP {$e->getCode()})");
	}
}

if (isset($_REQUEST['unlink']) || isset($_REQUEST['everywhere'])){
	$col = 'user';
	$val = $currentUser->id;
	$username = Users::ValidateName('username', null, true);
	if (isset($username)){
		if (!Permission::Sufficient('staff') || isset($_REQUEST['unlink']))
			Response::Fail();
		/** @var $TargetUser User */
		$TargetUser = $Database->where('name', $username)->getOne('users','id,name');
		if (empty($TargetUser))
			Response::Fail("Target user doesn't exist");
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
	Response::Fail('Could not remove information from database');

if (empty($TargetUser))
	Cookie::Delete('access', Cookie::HTTPONLY);
Response::Done();
