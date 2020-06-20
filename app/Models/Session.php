<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\Cookie;
use App\CoreUtils;
use App\JSON;
use App\Time;
use App\Twig;

/**
 * @property int            $id
 * @property int            $user_id
 * @property string         $platform
 * @property string         $browser_name
 * @property string         $browser_ver
 * @property string         $user_agent
 * @property string         $token           (Cookie Auth)
 * @property DateTime       $created
 * @property DateTime       $last_visit
 * @property string         $data
 * @property bool           $updating
 * @property string         $access          (Via magic method)
 * @property string         $refresh         (Via magic method)
 * @property DateTime       $expires         (Via magic method)
 * @property bool           $expired         (Via magic method)
 * @property User           $user            (Via relations)
 * @method static Session find_by_token(string $token)
 * @method static Session find_by_access(string $access)
 * @method static Session find_by_refresh(string $code)
 * @method static Session find(int $id)
 */
class Session extends NSModel {
  public static $belongs_to = [
    ['user'],
  ];

  /** For Twig */
  public function getUser():User {
    return $this->user;
  }

  public static $attr_protected = ['data'];

  public function get_access() {
    return $this->user->deviantart_user->access;
  }

  public function get_refresh() {
    return $this->user->deviantart_user->refresh;
  }

  public function get_expires() {
    return $this->user->deviantart_user->access_expires;
  }

  public function get_expired(): bool {
    return
      $this->user !== null
      && $this->user->isDeviantartLinked()
      && $this->expires->getTimestamp() < time();
  }

  public function detectBrowser(?string $ua = null) {
    foreach (CoreUtils::detectBrowser($ua) as $k => $v)
      if (!empty($v))
        $this->{$k} = $v;
  }

  public static function generateCookie():string {
    return bin2hex(random_bytes(64));
  }

  public static function setCookie(string $value) {
    Cookie::set('access', $value, time() + Time::IN_SECONDS['year'], Cookie::HTTP_ONLY);
  }

  public static function newGuestSession():self {
    $session = new self();
    $cookie = self::generateCookie();
    $session->token = CoreUtils::sha256($cookie);
    $session->detectBrowser();
    self::setCookie($cookie);

    return $session;
  }

  /** @var array */
  private $_data;

  private function _serializeData() {
    $this->data = JSON::encode($this->_data, JSON_FORCE_OBJECT);
    $this->save();
  }

  private function _unserializeData() {
    $this->_data = $this->data === null ? [] : JSON::decode($this->data);
  }

  public function importData(string $data) {
    $this->data = $data;
    $this->_unserializeData();
  }

  public function setData(string $key, $value) {
    if ($this->_data === null)
      $this->_unserializeData();
    $this->_data[$key] = $value;
    $this->_serializeData();
  }

  public function unsetData(string $key) {
    if ($this->_data === null)
      $this->_unserializeData();
    unset($this->_data[$key]);
    $this->_serializeData();
  }

  public function getData(string $key, $default = null) {
    if ($this->_data === null)
      $this->_unserializeData();

    return $this->_data[$key] ?? $default;
  }

  public function pullData(string $key) {
    $value = $this->getData($key);
    $this->unsetData($key);

    return $value;
  }

  public function hasData(string $key) {
    if ($this->_data === null)
      $this->_unserializeData();

    return isset($this->_data[$key]);
  }

  public function registerVisit() {
    if (CoreUtils::tsDiff($this->last_visit) > Time::IN_SECONDS['minute']){
      $this->last_visit = date('c');
      $this->detectBrowser();
      $this->save();
    }
  }

  public function refreshAccessToken() {
    if ($this->updating === true)
      return;

    $this->updating = true;
    $this->save();
    // Update access token in the BG
    CoreUtils::callScript('access_token_refresher', [$this->id], $out);
  }

  public function getProfileCard(bool $is_current) {
    $data = [
      'session' => $this,
      'browser_class' => CoreUtils::browserNameToClass($this->browser_name),
      'is_current' => $is_current,
    ];

    return Twig::$env->render('user/_profile_session.html.twig', $data);
  }
}

