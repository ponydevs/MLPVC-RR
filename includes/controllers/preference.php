<?php

	if (!Permission::Sufficient('user') || !POST_REQUEST)
		CoreUtils::NotFound();
	CSRFProtection::Protect();

	if (!regex_match(new RegExp('^([gs]et)/([a-z_]+)$'), CoreUtils::Trim($data), $_match))
		Response::Fail('Preference key invalid');

	$getting = $_match[1] === 'get';
	$key = $_match[2];

	// TODO Support changing some preferences of other users by staff
	$currvalue = UserPrefs::Get($key);
	if ($getting)
		Response::Done(array('value' => $currvalue));

	try {
		$newvalue = UserPrefs::Process($key);
	}
	catch (Exception $e){ Response::Fail('Preference value error: '.$e->getMessage()); }

	if ($newvalue === $currvalue)
		Response::Done(array('value' => $newvalue));
	if (!UserPrefs::Set($key, $newvalue))
		Response::DBError();

	Response::Done(array('value' => $newvalue));
