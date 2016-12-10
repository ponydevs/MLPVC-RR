<?php

use App\DeviantArt;
use App\Episodes;
use App\HTTP;
use App\Permission;
use App\RegExp;
use App\UserPrefs;

if (!isset($_GET['error']) && (empty($_GET['code']) || empty($_GET['state'])))
	$_GET['error'] = 'unauthorized_client';
if (isset($_GET['error'])){
	$err = $_GET['error'];
	if (isset($_GET['error_description']))
		$errdesc = $_GET['error_description'];
	global $signedIn;
	if ($signedIn)
		HTTP::Redirect($_GET['state']);
	Episodes::loadPage();
}
$currentUser = DeviantArt::GetToken($_GET['code']);
$signedIn = !empty($currentUser);

if (isset($_GET['error'])){
	$err = $_GET['error'];
	if (isset($_GET['error_description']))
		$errdesc = $_GET['error_description'];

	if ($err === 'user_banned')
		$errdesc .= "\n\nIf you'd like to appeal your ban, please <a href='http://mlp-vectorclub.deviantart.com/notes/'>send the group a note</a>.";
	Episodes::loadPage();
}


if (preg_match(new RegExp('^[a-z\d]+$','i'), $_GET['state'], $_match)){
	$confirm = str_replace('{{CODE}}', $_match[0], file_get_contents(INCPATH.'views/loginConfrim.html'));
	$confirm = str_replace('{{USERID}}', Permission::Sufficient('developer') || UserPrefs::Get('p_disable_ga') ? '' : $currentUser->id, $confirm);
	die($confirm);
}
else if (preg_match($REWRITE_REGEX, $_GET['state']))
	HTTP::Redirect($_GET['state']);

HTTP::Redirect('/');
