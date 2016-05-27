<?php

	if (!$signedIn) CoreUtils::Respond("You've already signed out",1);
	CSRFProtection::Protect();

	if (isset($_REQUEST['unlink'])){
		try {
			DeviantArt::Request('https://www.deviantart.com/oauth2/revoke', null, array('token' => $currentUser['Session']['access']));
		}
		catch (cURLRequestException $e){
			CoreUtils::Respond("Coulnd not revoke the site's access: {$e->getMessage()} (HTTP {$e->getCode()})");
		}
	}

	if (isset($_REQUEST['unlink']) || isset($_REQUEST['everywhere'])){
		$col = 'user';
		$val = $currentUser['id'];
		$username = (new Input('username','username',array(
			'optional' => true,
			'errors' => array(
				Input::$ERROR_INVALID => 'Username (@value) is invalid',
			),
		)))->out();
		if (isset($username)){
			if (!Permission::Sufficient('staff') || isset($_REQUEST['unlink']))
				CoreUtils::Respond();
			$TargetUser = $Database->where('name', $username)->getOne('users','id,name');
			if (empty($TargetUser))
				CoreUtils::Respond("Target user doesn't exist");
			if ($TargetUser['id'] !== $currentUser['id'])
				$val = $TargetUser['id'];
			else unset($TargetUser);
		}
	}
	else {
		$col = 'id';
		$val = $currentUser['Session']['id'];
	}

	if (!$Database->where($col,$val)->delete('sessions'))
		CoreUtils::Respond('Could not remove information from database');

	if (empty($TargetUser))
		Cookie::delete('access');
	CoreUtils::Respond(true);
