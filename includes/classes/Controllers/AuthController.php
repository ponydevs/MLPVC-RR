<?php

namespace App\Controllers;

use App\CSRFProtection;
use App\Cookie;
use App\DeviantArt;
use App\Episodes;
use App\HTTP;
use App\Permission;
use App\RegExp;
use App\Response;
use App\UserPrefs;
use App\Users;
use App\Models\User;
use App\Exceptions\CURLRequestException;

class AuthController extends Controller {
	public $do = 'da-auth';

	function auth(){
		CSRFProtection::detect();

		if (!isset($_GET['error']) && (empty($_GET['code']) || empty($_GET['state'])))
			$_GET['error'] = 'unauthorized_client';
		if (isset($_GET['error'])){
			$err = $_GET['error'];
			if (isset($_GET['error_description']))
				$errdesc = $_GET['error_description'];
			global $signedIn;
			if ($signedIn)
				HTTP::redirect($_GET['state']);
			Episodes::loadPage();
		}
		$currentUser = DeviantArt::getToken($_GET['code']);
		$signedIn = !empty($currentUser);

		if (isset($_GET['error'])){
			$err = $_GET['error'];
			if (isset($_GET['error_description']))
				$errdesc = $_GET['error_description'];

			if ($err === 'user_banned')
				$errdesc .= "\n\nIf you’d like to appeal your ban, please <a href='http://mlp-vectorclub.deviantart.com/notes/'>send the group a note</a>.";
			Episodes::loadPage();
		}

		global $REWRITE_REGEX;
		if (preg_match(new RegExp('^[a-z\d]+$','i'), $_GET['state'], $_match)){
			$confirm = str_replace('{{CODE}}', $_match[0], file_get_contents(INCPATH.'views/loginConfrim.html'));
			$confirm = str_replace('{{USERID}}', Permission::sufficient('developer') || UserPrefs::get('p_disable_ga') ? '' : $currentUser->id, $confirm);
			die($confirm);
		}
		else if (preg_match($REWRITE_REGEX, $_GET['state']))
			HTTP::redirect($_GET['state'], 302);

		HTTP::redirect('/', 302);
	}

	function signout(){
		global $signedIn, $currentUser, $Database;

		if (!$signedIn) Response::success("You've already signed out");
		CSRFProtection::protect();

		if (isset($_REQUEST['unlink'])){
			try {
				DeviantArt::request('https://www.deviantart.com/oauth2/revoke', null, array('token' => $currentUser->Session['access']));
			}
			catch (CURLRequestException $e){
				Response::fail("Could not revoke the site’s access: {$e->getMessage()} (HTTP {$e->getCode()})");
			}
		}

		if (isset($_REQUEST['unlink']) || isset($_REQUEST['everywhere'])){
			$col = 'user';
			$val = $currentUser->id;
			$username = Users::validateName('username', null, true);
			if (isset($username)){
				if (!Permission::sufficient('staff') || isset($_REQUEST['unlink']))
					Response::fail();
				/** @var $TargetUser User */
				$TargetUser = $Database->where('name', $username)->getOne('users','id,name');
				if (empty($TargetUser))
					Response::fail("Target user doesn’t exist");
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
			Response::fail('Could not remove information from database');

		if (empty($TargetUser))
			Cookie::delete('access', Cookie::HTTPONLY);
		Response::done();
	}
}
