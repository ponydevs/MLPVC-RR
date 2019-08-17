<?php

namespace App\Controllers;

use App\CoreUtils;
use App\Models\Session;
use App\Permission;
use App\RegExp;
use App\Response;

class AboutController extends Controller {
  public function index() {
    CoreUtils::loadPage(__METHOD__, [
      'title' => 'About',
      'css' => [true],
      'js' => [true],
    ]);
  }

  public function browser($params) {
    $user_agent = null;
    if (isset($params['session'])){
      if (Permission::insufficient('developer'))
        CoreUtils::noPerm();
      $session = Session::find($params['session']);
      if (!empty($session))
        $user_agent = $session->user_agent;
    }
    else $session = null;
    $browser = CoreUtils::detectBrowser($user_agent);
    if (empty($browser['platform']))
      CoreUtils::error_log('Could not find platform based on the following UA string: '.preg_replace(new RegExp(INVERSE_PRINTABLE_ASCII_PATTERN), '', $user_agent));
    if (!empty($browser['browser_name']))
      $browser['browser_class'] = CoreUtils::browserNameToClass($browser['browser_name']);

    if ($session !== null){
      $session->platform = $browser['platform'];
      $session->browser_name = $browser['browser_name'];
      $session->browser_ver = $browser['browser_ver'];
      $session->save();
    }

    CoreUtils::fixPath('/about/browser'.(!empty($session) ? "/{$session->id}" : ''));

    CoreUtils::loadPage(__METHOD__, [
      'title' => 'Browser recognition test page',
      'css' => [true],
      'noindex' => true,
      'import' => [
        'session' => $session ?? null,
        'browser' => $browser,
      ],
    ]);
  }

  public function upcoming() {
    if ($this->action !== 'GET')
      CoreUtils::notAllowed();

    Response::done(['html' => CoreUtils::getSidebarUpcoming(NOWRAP)]);
  }

  public function privacy() {
    CoreUtils::loadPage(__METHOD__, [
      'title' => 'Privacy Policy',
      'css' => [true],
    ]);
  }
}
