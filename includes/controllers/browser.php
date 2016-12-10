<?php

use App\CoreUtils;
use App\Permission;
use App\RegExp;

$AgentString = null;
if (is_numeric($data) && Permission::Sufficient('developer')){
	$SessionID = intval($data, 10);
	$Session = $Database->where('id', $SessionID)->getOne('sessions');
	if (!empty($Session))
		$AgentString = $Session['user_agent'];
}
$browser = CoreUtils::detectBrowser($AgentString);
if (empty($browser['platform']))
	error_log('Could not find platform based on the following UA string: '.preg_replace(new RegExp(INVERSE_PRINTABLE_ASCII_PATTERN), '', $AgentString));

CoreUtils::fixPath('/browser'.(!empty($Session)?"/{$Session['id']}":''));

CoreUtils::loadPage(array(
	'title' => 'Browser recognition test page',
	'do-css',
	'no-robots',
));
