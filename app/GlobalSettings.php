<?php

namespace App;

use App\Models\GlobalSetting;
use RuntimeException;

class GlobalSettings {
  public const DEFAULTS = [
    'reservation_rules' => '',
    'about_reservations' => '',
    'dev_role_label' => 'developer',
  ];

  /**
   * Gets a global cofiguration item's value
   *
   * @param string $key
   *
   * @return mixed
   */
  public static function get(string $key) {
    $q = GlobalSetting::find($key);

    return isset($q->val) ? $q->val : static::DEFAULTS[$key];
  }

  /**
   * Sets a global cofiguration item's value
   *
   * @param string $key
   * @param mixed  $value
   *
   * @return bool
   */
  public static function set(string $key, $value):bool {
    if (!isset(static::DEFAULTS[$key]))
      Response::fail("Key $key is not allowed");
    $default = static::DEFAULTS[$key];

    if (GlobalSetting::exists($key)){
      $setting = GlobalSetting::find($key);
      if ($value == $default)
        return $setting->delete();
      else return $setting->update_attributes(['val' => $value]);
    }
    else if ($value != $default)
      return (new GlobalSetting([
        'key' => $key,
        'val' => $value,
      ]))->save();
    else return true;
  }

  /**
   * Processes a configuration item's new value
   *
   * @param string $key
   *
   * @return mixed
   */
  public static function process(string $key) {
    $value = CoreUtils::trim($_REQUEST['value']);

    if ($value === '')
      return null;

    switch ($key){
      case 'reservation_rules':
      case 'about_reservations':
        $value = CoreUtils::sanitizeHtml($value, $key === 'reservation_rules' ? ['li', 'ol'] : ['p']);
      break;

      case 'dev_role_label':
        if (Permission::insufficient('developer'))
          Response::fail("You cannot change the $key setting");

        if (empty($value) || !isset(Permission::ROLES_ASSOC[$value]))
          throw new RuntimeException('The specified role is invalid');
      break;
    }

    return $value;
  }
}
