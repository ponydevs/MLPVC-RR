<?php

use App\CoreUtils;
use App\CSRFProtection;
use App\Permission;
use App\RegExp;
use App\Response;
use App\UserPrefs;

/** @var $data string */

if (!Permission::sufficient('user') || !POST_REQUEST)
	CoreUtils::notFound();
CSRFProtection::protect();

if (!preg_match(new RegExp('^([gs]et)/([a-z_]+)$'), CoreUtils::trim($data), $_match))
	Response::fail('Preference key invalid');

$getting = $_match[1] === 'get';
$key = $_match[2];

// TODO Support changing some preferences of other users by staff
$currvalue = UserPrefs::get($key);
if ($getting)
	Response::done(array('value' => $currvalue));

try {
	$newvalue = UserPrefs::process($key);
}
catch (Exception $e){ Response::fail('Preference value error: '.$e->getMessage()); }

if ($newvalue === $currvalue)
	Response::done(array('value' => $newvalue));
if (!UserPrefs::set($key, $newvalue))
	Response::dbError();

Response::done(array('value' => $newvalue));
