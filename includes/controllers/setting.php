<?php

use App\CoreUtils;
use App\CSRFProtection;
use App\GlobalSettings;
use App\Permission;
use App\RegExp;
use App\Response;

if (!Permission::Sufficient('staff') || !POST_REQUEST)
	CoreUtils::notFound();
CSRFProtection::Protect();

if (!preg_match(new RegExp('^([gs]et)/([a-z_]+)$'), CoreUtils::trim($data), $_match))
	Response::Fail('Setting key invalid');

$getting = $_match[1] === 'get';
$key = $_match[2];

$currvalue = GlobalSettings::Get($key);
if ($getting)
	Response::Done(array('value' => $currvalue));

if (!isset($_POST['value']))
	Response::Fail('Missing setting value');

try {
	$newvalue = GlobalSettings::Process($key);
}
catch (Exception $e){ Response::Fail('Preference value error: '.$e->getMessage()); }

if ($newvalue === $currvalue)
	Response::Done(array('value' => $newvalue));
if (!GlobalSettings::Set($key, $newvalue))
	Response::DBError();

Response::Done(array('value' => $newvalue));
