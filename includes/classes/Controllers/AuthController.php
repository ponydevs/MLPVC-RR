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
use App\Logs;
use App\Models\Logs\FailedAuthAttempt;
use App\Permission;
use App\RegExp;
use App\Response;
use App\Users;
use App\Models\User;
use App\Exceptions\CURLRequestException;

class AuthController extends Controller {
	public function begin(){
		$authUrl = DeviantArt::OAuthProviderInstance()->getAuthorizationUrl([
			'scope' => ['user', 'browse'],
		]);
		if (isset($_GET['return']) && CoreUtils::isURLSafe($_GET['return']))
			Auth::$session->setData('return_url', $_GET['return']);
		Auth::$session->setData('da_state', DeviantArt::OAuthProviderInstance()->getState());
		HTTP::tempRedirect($authUrl);
	}

	public function end(){
		if (!isset($_GET['error']) && (empty($_GET['code']) || empty($_GET['state']) || $_GET['state'] !== Auth::$session->pullData('da_state')))
			$_GET['error'] = 'unauthorized_client';
		if (isset($_GET['error'])){
			$err = $_GET['error'];
			$errdesc = $_GET['error_description'] ?? null;
			if (Auth::$signed_in)
				$this->_redirectBack();
			$this->_error($err, $errdesc);
		}

		if (FailedAuthAttempt::canAuthenticate()){
			try {
				Auth::$user = DeviantArt::getAccessToken($_GET['code']);
			}
			catch(\Exception $e){
				CoreUtils::error_log(__METHOD__.': '.$e->getMessage()."\n".$e->getTraceAsString());
				FailedAuthAttempt::record();
				$this->_error('server_error');
			}
			Auth::$signed_in = !empty(Auth::$user);
		}
		else {
			$_GET['error'] = 'time_out';
			$_GET['error_description'] = "You've made too many failed login attempts in a short period of time. Please wait a few minutes before trying again.";
		}

		if (isset($_GET['error'])){
			$err = $_GET['error'];
			if (isset($_GET['error_description'])){
				$errdesc = $_GET['error_description'];

				if ($err === 'user_banned')
					$errdesc .= "\n\nIf you'd like to appeal your ban, please <a class='send-feedback'>contact us</a>.";
			}
			if ($err !== 'time_out')
				FailedAuthAttempt::record();
			$this->_error($err, $errdesc ?? null);
		}

		if (Auth::$session->hasData('return_url'))
			$this->_redirectBack();

		die(File::get(INCPATH.'views/loginConfirm.html'));
	}

	public function signout(){
		if (!Auth::$signed_in)
			Response::success("You've already signed out");
		CSRFProtection::protect();

		$unlink = isset($_REQUEST['unlink']);
		if ($unlink){
			try {
				DeviantArt::request('https://www.deviantart.com/oauth2/revoke', null, ['token' => Auth::$session->access]);
			}
			catch (CURLRequestException $e){
				Response::fail("Could not revoke the site's access: {$e->getMessage()} (HTTP {$e->getCode()})");
			}
		}

		if (isset($_REQUEST['everywhere'])){
			$col = 'user_id';
			$val = Auth::$user->id;
			$username = Users::validateName('name');
			if ($username !== null){
				if ($unlink || !Permission::sufficient('staff'))
					Response::fail();
				/** @var $TargetUser User */
				$TargetUser = Users::get($username, 'name');
				if (empty($TargetUser))
					Response::fail("Target user doesn't exist");
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
		if ($err !== 'time_out')
			CoreUtils::error_log("DeviantArt authentication error ($err): $errdesc");

		HTTP::statusCode(500);
		CoreUtils::loadPage('ErrorController::auth', [
			'title' => 'DeviantArt authentication error',
			'js' => [true],
			'import' => [
				'err' => $err,
				'errdesc' => $errdesc,
			]
		]);
	}

	private function _redirectBack(){
		$return_url = Auth::$session->pullData('return_url');

		HTTP::tempRedirect($return_url ?? '/');
	}
}
