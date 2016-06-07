<?php

	if (!Permission::Sufficient('user') || !POST_REQUEST)
		CoreUtils::NotFound();
	CSRFProtection::Protect();

	if (!regex_match(new RegExp('^([gs]et)/([a-z_]+)$'), CoreUtils::Trim($data), $_match))
		CoreUtils::Respond('Preference key invalid');

	$getting = $_match[1] === 'get';
	$key = $_match[2];

	$currvalue = UserPrefs::Get($key);
	if ($getting)
		CoreUtils::Respond(array('value' => $currvalue));

	try {
		$newvalue = UserPrefs::Process($key);
	}
	catch (Exception $e){ CoreUtils::Respond('Preference value error: '.$e->getMessage()); }

	if ($newvalue === $currvalue)
		CoreUtils::Respond(array('value' => $newvalue));
	if (!UserPrefs::Set($key, $newvalue))
		CoreUtils::Respond(ERR_DB_FAIL);

	CoreUtils::Respond(array('value' => $newvalue));
