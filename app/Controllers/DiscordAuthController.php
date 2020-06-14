<?php

namespace App\Controllers;

use App\Auth;
use App\CoreUtils;
use App\DB;
use App\HTTP;
use App\JSON;
use App\Models\DiscordMember;
use App\Models\User;
use App\Permission;
use App\Response;
use App\Time;
use GuzzleHttp\Exception\RequestException;
use Wohali\OAuth2\Client\Provider\Discord;
use Wohali\OAuth2\Client\Provider\Exception\DiscordIdentityProviderException;

class DiscordAuthController extends Controller {
  /** @var Discord */
  private $provider;

  public function __construct() {
    if (isset($_POST['key'])){
      if (!hash_equals(CoreUtils::env('WS_SERVER_KEY'), $_POST['key']))
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

  private function getReturnUrl():string {
    return Auth::$user->toURL(false).'#discord-connect';
  }

  public static function getProvider():Discord {
    return new Discord([
      'clientId' => CoreUtils::env('DISCORD_CLIENT'),
      'clientSecret' => CoreUtils::env('DISCORD_SECRET'),
      'redirectUri' => ABSPATH.'discord-connect/end',
    ]);
  }

  private function redirectIfAlreadyLinked():void {
    if (Auth::$user->isDiscordLinked())
      HTTP::tempRedirect($this->getReturnUrl());
  }

  public function begin() {
    $this->redirectIfAlreadyLinked();

    $authUrl = $this->provider->getAuthorizationUrl([
      'scope' => ['identify', 'guilds'],
    ]);
    Auth::$session->setData('discord_state', $this->provider->getState());
    HTTP::tempRedirect($authUrl);
  }

  public function end() {
    $this->redirectIfAlreadyLinked();

    $returnUrl = $this->getReturnUrl();

    if (!isset($_GET['code'], $_GET['state']) || $_GET['state'] !== Auth::$session->pullData('discord_state'))
      HTTP::tempRedirect($returnUrl);

    try {
      $token = $this->provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);
    }
    catch (DiscordIdentityProviderException $e){
      if (CoreUtils::contains($e->getMessage(), 'invalid_grant')){
        CoreUtils::error_log('Discord connection resulted in invalid_grant error, redirecting to beginning');
        HTTP::tempRedirect('/discord-connect/begin');
      }
      throw $e;
    }
    $discord_user_res = DiscordMember::getUserData($this->provider, $token);
    if ($discord_user_res === null) {
      sd('No discord user res');
      HTTP::tempRedirect($returnUrl);
    }

    $discord_id = (int) $discord_user_res->getId();

    $discord_user = DiscordMember::find($discord_id);
    if (empty($discord_user)){
      $discord_user = new DiscordMember();
      $discord_user->id = $discord_id;
    }

    // Delete any existing member records for this user that do not have this Discord user ID
    DB::$instance->where('user_id', Auth::$user->id)->where('id', $discord_id, '!=')->delete('discord_members');

    $discord_user->user_id = Auth::$user->id;
    $discord_user->last_synced = date('c');
    $discord_user->updateFromApi($discord_user_res);
    $discord_user->updateAccessToken($token);
    $discord_user->checkServerMembership();

    HTTP::tempRedirect($returnUrl);
  }

  private ?User $target;
  private bool $same_user;

  private function setTarget($params):void {
    $this->target = User::find($params['user_id']);
    if (false === $this->target instanceof User)
      CoreUtils::notFound();
    if ($this->target->id !== Auth::$user->id && Permission::insufficient('staff'))
      Response::fail();

    if (!$this->target->boundToDiscordMember())
      Response::fail('You must be bound to a Discord user to perform this action');

    $this->same_user = $this->target->id === Auth::$user->id;
  }

  public function sync($params) {
    $this->setTarget($params);

    $discordUser = $this->target->discord_member;
    if ($discordUser->access === null)
      Response::fail('The Discord account must be linked before syncing');

    if (!$discordUser->canBeSynced())
      Response::fail('The account information was last updated '.Time::format($discordUser->last_synced->getTimestamp(), Time::FORMAT_READABLE).', please wait at least 5 minutes before syncing again.');

    $discordUser->sync($this->provider);
    Response::done();
  }

  public function unlink($params) {
    $this->setTarget($params);

    $discord_user = $this->target->discord_member;
    if ($discord_user->isLinked()){
      $status_code = null;
      try {
        $req = $this->provider->getRequest('POST', $this->provider->apiDomain.'/oauth2/token/revoke', [
          'body' => http_build_query([
            'token' => $discord_user->refresh,
            'token_type_hint' => 'refresh_token',
          ]),
          'headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded',
          ],
        ]);
        $res = $this->provider->getResponse($req);
        $status_code = $res->getStatusCode();
      }
      catch (RequestException $e){
        if ((string)$e->getResponse()->getBody() === '{"error": "invalid_client"}'){
          $status_code = 200;
        }
        else throw $e;
      }
      if ($status_code !== 200){
        // Revoke failed
        CoreUtils::error_log("Revoking Discord access failed for {$this->target->name}, details:\n".JSON::encode([
            'statusCode' => $res->getStatusCode(),
            'body' => (string)$res->getBody(),
          ], JSON_PRETTY_PRINT));
        Response::fail('Revoking access failed, please <a class="send-feedback">let us know</a> so we can look into the issue.');
      }
    }

    // Revoke successful
    $discord_user->delete();

    $Your = $this->same_user ? 'Your' : 'This';
    Response::success("$Your Discord account was successfully unlinked.".($this->same_user
        ? ' If you want to verify it yourself, check your Authorized Apps in your settings.' : ''));
  }

  public function botUpdate($params) {
    if (!hash_equals(CoreUtils::env('WS_SERVER_KEY'), $_POST['key']))
      CoreUtils::noPerm();

    $discordUser = DiscordMember::find($params['id']);
    if (empty($discordUser) || !$discordUser->isLinked())
      Response::done();

    $discordUser->sync($this->provider, true);
    Response::done();
  }
}
