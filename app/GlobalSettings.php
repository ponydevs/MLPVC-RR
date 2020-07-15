<?php

namespace App;

use ActiveRecord\ActiveRecordException;
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
    $q = GlobalSetting::find_by_name($key);

    return $q->val ?? static::DEFAULTS[$key];
  }

  /**
   * Sets a global configuration item's value
   *
   * @param string $name
   * @param mixed  $value
   *
   * @return bool
   * @throws ActiveRecordException
   */
  public static function set(string $name, string $value):bool {
    if (!isset(static::DEFAULTS[$name]))
      Response::fail("Key $name is not allowed");
    $default = static::DEFAULTS[$name];

    $setting = GlobalSetting::find_by_name($name);
    if ($setting !== null){
      if ($value === $default)
        return $setting->delete();
      else return $setting->update_attributes(['val' => $value]);
    }
    else if ($value !== $default)
      return (new GlobalSetting([
        'name' => $name,
        'val' => $value,
      ]))->save();
    else return true;
  }

  /**
   * Processes a configuration item's new value
   *
   * @param string $name
   *
   * @return mixed
   */
  public static function process(string $name) {
    $value = CoreUtils::trim($_REQUEST['value']);

    if ($value === '')
      return null;

    switch ($name){
      case 'reservation_rules':
      case 'about_reservations':
        $value = CoreUtils::sanitizeHtml($value, $name === 'reservation_rules' ? ['li', 'ol'] : ['p']);
      break;

      case 'dev_role_label':
        if (Permission::insufficient('developer'))
          Response::fail("You cannot change the $name setting");

        if (empty($value) || !isset(Permission::ROLES_ASSOC[$value]))
          throw new RuntimeException('The specified role is invalid');
      break;
    }

    return $value;
  }
}
