<?php

namespace App\Controllers;

use App\Auth;
use App\CoreUtils;
use App\CSRFProtection;
use App\HTTP;
use App\JSON;
use App\Models\DiscordMember;
use App\Models\User;
use App\Permission;
use App\Response;
use App\Time;
use App\UserPrefs;
use App\Users;
use League\OAuth2\Client\Token\AccessToken;
use RestCord\DiscordClient;
use Wohali\OAuth2\Client\Provider\Discord;
use Wohali\OAuth2\Client\Provider\DiscordResourceOwner;
use Wohali\OAuth2\Client\Provider\Exception\DiscordIdentityProviderException;

class DiscordAuthController extends Controller {
	/** @var Discord */
	private $provider;

	public function __construct(){
		if (isset($_POST['key'])){
			if (!hash_equals(WS_SERVER_KEY, $_POST['key']))
				CoreUtils::noPerm();
		}
		else {
			parent::__construct();

			if (!Auth::$signed_in){
				if (CoreUtils::isJSONExpected()){
					Response::fail();
				}
				CoreUtils::noPerm();
			}
		}

		$this->provider = self::getProvider();
	}

	private function _getReturnUrl():string {
		return Auth::$user->toURL().'#discord-connect';
	}

	public static function getProvider():Discord {
		return new Discord([
			'clientId' => DISCORD_CLIENT,
			'clientSecret' => DISCORD_SECRET,
			'redirectUri' => ABSPATH.'discord-connect/end',
		]);
	}

	private function _redirectIfAlreadyLinked(){
		if (Auth::$user->isDiscordLinked())
			HTTP::tempRedirect($this->_getReturnUrl());
	}

	public function begin(){
		$this->_redirectIfAlreadyLinked();

		$authUrl = $this->provider->getAuthorizationUrl([
			'scope' => ['identify', 'guilds'],
		]);
		Auth::$session->setData('discord_state', $this->provider->getState());
		HTTP::tempRedirect($authUrl);
	}

	public function end(){
		$this->_redirectIfAlreadyLinked();

		$returnUrl = $this->_getReturnUrl();

		if (!isset($_GET['code'], $_GET['state']) || $_GET['state'] !== Auth::$session->pullData('discord_state'))
			HTTP::tempRedirect($returnUrl);

		try {
			$token = $this->provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);
		}
		catch(DiscordIdentityProviderException $e){
			if (stripos($e->getMessage(), 'invalid_grant') !== false){
				CoreUtils::error_log('Discord connection resulted in invalid_grant error, redirecting to beginning');
				HTTP::tempRedirect('/discord-connect/begin');
			}
			throw $e;
		}
		$user = DiscordMember::getUserData($this->provider, $token);
		if ($user === null)
			HTTP::tempRedirect($returnUrl);

		$discordUser = DiscordMember::find($user->getId());
		if (empty($discordUser)){
			$discordUser = new DiscordMember();
			$discordUser->id = $user->getId();
		}
		$discordUser->user_id = Auth::$user->id;
		$discordUser->last_synced = date('c');
		$discordUser->updateFromApi($user);
		$discordUser->updateAccessToken($token);
		$discordUser->checkServerMembership();

		HTTP::tempRedirect($returnUrl);
	}

	/** @var User */
	private $_target;
	/** @var bool */
	private $_sameUser;
	private function _setTarget($params){
		if ($params['name'] === Auth::$user->name)
			$this->_target = Auth::$user;
		else {
			$this->_target = Users::get($params['name'], 'name');
			if (false === $this->_target instanceof User)
				CoreUtils::notFound();
			if ($this->_target->id !== Auth::$user->id && Permission::insufficient('staff'))
				Response::fail();
		}

		if ($this->_target->discord_member === null)
			Response::fail('You must be bound to a Discord user to perform this action');

		$this->_sameUser = $this->_target->id === Auth::$user->id;
	}

	public function sync($params){
		$this->_setTarget($params);

		$discordUser = $this->_target->discord_member;
		if ($discordUser->access === null)
			Response::fail('The Discord account must be linked before syncing');

		if (!$discordUser->canBeSynced())
			Response::fail('The account information was last updated '.Time::format($discordUser->last_synced->getTimestamp(), Time::FORMAT_READABLE).', please wait at least 5 minutes before syncing again.');

		$discordUser->sync($this->provider);
		Response::done();
	}

	public function unlink($params){
		$this->_setTarget($params);

		$discordUser = $this->_target->discord_member;
		if ($discordUser->isLinked()){
			$req = $this->provider->getRequest('POST', $this->provider->apiDomain.'/oauth2/token/revoke', [
				'body' => http_build_query([
					'token' => $discordUser->refresh,
					'token_type_hint' => 'refresh_token'
				]),
				'headers' => [
					'Content-Type' => 'application/x-www-form-urlencoded',
				]
			]);
			$res = $this->provider->getResponse($req);
			if ($res->getStatusCode() !== 200){
				// Revoke failed
				CoreUtils::error_log("Revoking Discord access failed for {$this->_target->name}, details:\n".JSON::encode([
					'statusCode' => $res->getStatusCode(),
					'body' => (string)$res->getBody(),
				], JSON_PRETTY_PRINT));
				Response::fail('Revoking access failed, please <a class="send-feedback">let us know</a> so we can look into the issue.');
			}
		}

		// Revoke successful
		$discordUser->delete();

		$Your = $this->_sameUser ? 'Your' : 'This';
		Response::success("$Your Discord account was successfully unlinked.".($this->_sameUser?' If you want to verify it yourself, check your Authorized Apps in your settings.':''));
	}

	public function botUpdate($params){
		if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1')
			CoreUtils::notFound();

		if (!hash_equals(WS_SERVER_KEY, $_POST['key']))
			CoreUtils::noPerm();

		$discordUser = DiscordMember::find($params['id']);
		if (empty($discordUser) || !$discordUser->isLinked())
			Response::done();

		$discordUser->sync($this->provider, true);
		Response::done();
	}
}
