<?php

namespace App\Controllers;

use App\Auth;
use App\CoreUtils;
use App\CSRFProtection;
use App\Cookie;
use App\DB;
use App\DeviantArt;
use App\File;
use App\HTTP;
use App\Permission;
use App\RegExp;
use App\Response;
use App\Users;
use App\Models\User;
use App\Exceptions\CURLRequestException;

class AuthController extends Controller {
	private static function _isStateRndkey(&$_match){
		return isset($_GET['state']) && preg_match(new RegExp('^[a-z\d]+$','i'), $_GET['state'], $_match);
	}

	public function auth(){
		CSRFProtection::detect();

		if (!isset($_GET['error']) && (empty($_GET['code']) || empty($_GET['state'])))
			$_GET['error'] = 'unauthorized_client';
		if (isset($_GET['error'])){
			$err = $_GET['error'];
			$errdesc = $_GET['error_description'] ?? null;
			if (Auth::$signed_in)
				$this->_moveToState($_GET['state'] ?? null);
			$this->_error($err, $errdesc);
		}
		try {
			Auth::$user = DeviantArt::getAccessToken($_GET['code']);
		}
		catch (CURLRequestException $e){
			if ($e->getCode() >= 500){
				CoreUtils::error_log(__METHOD__.': '.$e->getMessage()."\n".$e->getTraceAsString());
				$this->_error('server_error');
			}
		}
		catch(Exception $e){
			CoreUtils::error_log(__METHOD__.': '.$e->getMessage()."\n".$e->getTraceAsString());
			$this->_error('server_error');
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

		if (self::_isStateRndkey($_match)){
			$confirm = str_replace('{{CODE}}', $_match[0], File::get(INCPATH.'views/loginConfrim.html'));
			die($confirm);
		}
		else $this->_moveToState($_GET['state']);
	}

	public function signout(){
		if (!Auth::$signed_in) Response::success("You've already signed out");
		CSRFProtection::protect();

		$unlink = isset($_REQUEST['unlink']);
		if ($unlink){
			try {
				DeviantArt::request('https://www.deviantart.com/oauth2/revoke', null, ['token' => Auth::$session->access]);
			}
			catch (CURLRequestException $e){
				Response::fail("Could not revoke the site’s access: {$e->getMessage()} (HTTP {$e->getCode()})");
			}
		}

		if (isset($_REQUEST['everywhere'])){
			$col = 'user_id';
			$val = Auth::$user->id;
			$username = Users::validateName('username', null, true);
			if ($username !== null){
				if ($unlink || !Permission::sufficient('staff'))
					Response::fail();
				/** @var $TargetUser User */
				$TargetUser = Users::get($username, 'name');
				if (empty($TargetUser))
					Response::fail('Target user doesn’t exist');
				if ($TargetUser->id !== Auth::$user->id)
					$val = $TargetUser->id;
				else unset($TargetUser);
			}
		}
		else {
			$col = 'id';
			$val = Auth::$session->id;
		}

		if (!DB::$instance->where($col,$val)->delete('sessions'))
			Response::fail('Could not remove information from database');

		if (empty($TargetUser))
			Cookie::delete('access', Cookie::HTTPONLY);
		Response::done();
	}

	private function _error(?string $err, ?string $errdesc = null){
		$rndkey = self::_isStateRndkey($match) ? $match[0] : null;

		HTTP::statusCode(500);
		CoreUtils::loadPage('ErrorController::auth', [
			'title' => 'DeviantArt authentication error',
			'js' => [true],
			'import' => [
				'err' => $err,
				'errdesc' => $errdesc,
				'rndkey' => $rndkey,
			]
		]);
	}

	/**
	 * Move to a different state or fall back to the home page if it's invalid
	 * Disclaimer: relocation not covered by the application
	 *
	 * @param string $state Path to move to
	 */
	private function _moveToState(?string $state){
		global $REWRITE_REGEX;

		if ($state === null || !$REWRITE_REGEX->match($state))
			$state = '/';

		HTTP::redirect($state, HTTP::REDIRECT_TEMP);
	}
}
