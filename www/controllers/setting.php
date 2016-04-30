<?php

	if (!Permission::Sufficient('inspector') || !POST_REQUEST)
		CoreUtils::NotFound();
	CSRFProtection::Protect();

	if (!regex_match(new RegExp('^([gs]et)/([a-z_]+)$'), trim($data), $_match))
		CoreUtils::Respond('Setting key invalid');

	$getting = $_match[1] === 'get';
	$key = $_match[2];

	$currvalue = Configuration::Get($key);
	if ($currvalue === false)
		CoreUtils::Respond('Setting does not exist');
	if ($getting)
		CoreUtils::Respond(array('value' => $currvalue));

	if (!isset($_POST['value']))
		CoreUtils::Respond('Missing setting value');

	$newvalue = Configuration::Process($key);
	if ($newvalue === $currvalue)
		CoreUtils::Respond(array('value' => $newvalue));
	if (!Configuration::Set($key, $newvalue))
		CoreUtils::Respond(ERR_DB_FAIL);

	CoreUtils::Respond(array('value' => $newvalue));
