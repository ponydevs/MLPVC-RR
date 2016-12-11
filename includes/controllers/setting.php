<?php

use App\CoreUtils;
use App\CSRFProtection;
use App\GlobalSettings;
use App\Permission;
use App\RegExp;
use App\Response;

/** @var $data string */

if (!Permission::sufficient('staff') || !POST_REQUEST)
	CoreUtils::notFound();
CSRFProtection::protect();

if (!preg_match(new RegExp('^([gs]et)/([a-z_]+)$'), CoreUtils::trim($data), $_match))
	Response::fail('Setting key invalid');

$getting = $_match[1] === 'get';
$key = $_match[2];

$currvalue = GlobalSettings::get($key);
if ($getting)
	Response::done(array('value' => $currvalue));

if (!isset($_POST['value']))
	Response::fail('Missing setting value');

try {
	$newvalue = GlobalSettings::process($key);
}
catch (Exception $e){ Response::fail('Preference value error: '.$e->getMessage()); }

if ($newvalue === $currvalue)
	Response::done(array('value' => $newvalue));
if (!GlobalSettings::set($key, $newvalue))
	Response::dbError();

Response::done(array('value' => $newvalue));
