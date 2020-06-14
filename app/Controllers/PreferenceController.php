<?php

namespace App\Controllers;

use App\Auth;
use App\CoreUtils;
use App\Models\User;
use App\Permission;
use App\Response;
use App\UserPrefs;
use Exception;

class PreferenceController extends Controller {
  public function __construct() {
    parent::__construct();

    if (Permission::insufficient('user'))
      CoreUtils::noPerm();
  }

  private $value;
  private string $preference;
  private ?User $user;

  public function load_preference($params) {
    $this->preference = $params['key'];

    if (empty($params['id']))
      CoreUtils::notFound();
    $user = User::find($params['id']);
    if (empty($user))
      Response::fail('The specified user does not exist');
    if (Auth::$user->id !== $user->id && Permission::insufficient('staff'))
      Response::fail();

    $this->user = $user;
    $this->value = UserPrefs::get($this->preference, $this->user);
  }

  public function api($params) {
    $this->load_preference($params);

    switch ($this->action){
      case 'GET':
        Response::done(['value' => $this->value]);
      break;
      case 'PUT':
        try {
          $newvalue = UserPrefs::process($this->preference);
        }
        catch (Exception $e){
          Response::fail('Preference value error: '.$e->getMessage());
        }

        if ($newvalue === $this->value)
          Response::done(['value' => $newvalue]);
        if (!UserPrefs::set($this->preference, $newvalue, $this->user))
          Response::dbError();

        Response::done(['value' => $newvalue]);
      break;
      default:
        CoreUtils::notAllowed();
    }
  }
}
