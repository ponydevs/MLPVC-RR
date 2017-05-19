<?php

namespace App\Controllers;

use App\Auth;
use App\CoreUtils;
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
	public $do = 'daauth';

	private static function _isStateRndkey(&$_match){
		return isset($_GET['state']) && preg_match(new RegExp('^[a-z\d]+$','i'), $_GET['state'], $_match);
	}

	function auth(){
		CSRFProtection::detect();

		if (!isset($_GET['error']) && (empty($_GET['code']) || empty($_GET['state'])))
			$_GET['error'] = 'unauthorized_client';
		if (isset($_GET['error'])){
			$err = $_GET['error'];
			$errdesc = $_GET['error_description'] ?? null;
			if (Auth::$signed_in)
				HTTP::redirect($_GET['state'] ?? '/', HTTP::REDIRECT_TEMP);
			$this->_error($err, $errdesc);
		}
		try {
			Auth::$user = DeviantArt::getToken($_GET['code']);
		}
		catch (CURLRequestException $e){
			if (in_array($e->getCode(),[500,503])){
				$this->_error('server_error');
			}
		}
		Auth::$signed_in = !empty(Auth::$user);

		if (isset($_GET['error'])){
			$err = $_GET['error'];
			if (isset($_GET['error_description']))
				$errdesc = $_GET['error_description'];

			if ($err === 'user_banned')
				$errdesc .= "\n\nIf you’d like to appeal your ban, please <a class='send-feedback'>contact us</a>.";
			$this->_error($err, $errdesc);
		}

		global $REWRITE_REGEX;
		if (self::_isStateRndkey($_match)){
			$confirm = str_replace('{{CODE}}', $_match[0], file_get_contents(INCPATH.'views/loginConfrim.html'));
			die($confirm);
		}
		else if (preg_match($REWRITE_REGEX, $_GET['state']))
			HTTP::redirect($_GET['state'], HTTP::REDIRECT_TEMP);

		HTTP::redirect('/', HTTP::REDIRECT_TEMP);
	}

	function signout(){
		global $Database;

		if (!Auth::$signed_in) Response::success("You've already signed out");
		CSRFProtection::protect();

		if (isset($_REQUEST['unlink'])){
			try {
				DeviantArt::request('https://www.deviantart.com/oauth2/revoke', null, array('token' => Auth::$session->access));
			}
			catch (CURLRequestException $e){
				Response::fail("Could not revoke the site’s access: {$e->getMessage()} (HTTP {$e->getCode()})");
			}
		}

		if (isset($_REQUEST['unlink']) || isset($_REQUEST['everywhere'])){
			$col = 'user';
			$val = Auth::$user->id;
			$username = Users::validateName('username', null, true);
			if (isset($username)){
				if (!Permission::sufficient('staff') || isset($_REQUEST['unlink']))
					Response::fail();
				/** @var $TargetUser User */
				$TargetUser = $Database->where('name', $username)->getOne('users','id,name');
				if (empty($TargetUser))
					Response::fail("Target user doesn’t exist");
				if ($TargetUser->id !== Auth::$user->id)
					$val = $TargetUser->id;
				else unset($TargetUser);
			}
		}
		else {
			$col = 'id';
			$val = Auth::$session->id;
		}

		if (!$Database->where($col,$val)->delete('sessions'))
			Response::fail('Could not remove information from database');

		if (empty($TargetUser))
			Cookie::delete('access', Cookie::HTTPONLY);
		Response::done();
	}

	private function _error(?string $err, ?string $errdesc = null){
		$rndkey = self::_isStateRndkey($match) ? $match[0] : null;

		HTTP::statusCode(500);
		CoreUtils::loadPage([
			'title' => 'DeviantArt authentication error',
			'js' => "{$this->do}-error",
			'import' => [
				'err' => $err,
				'errdesc' => $errdesc,
				'rndkey' => $rndkey,
			]
		], $this);
	}
}
