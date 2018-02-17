<?php

namespace App;

use App\Models\GlobalSetting;

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
	public static function get(string $key){
		$q = GlobalSetting::find($key);
		return isset($q->value) ? $q->value : static::DEFAULTS[$key];
	}

	/**
	 * Sets a global cofiguration item's value
	 *
	 * @param string $key
	 * @param mixed $value
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
			else return $setting->update_attributes(['value' => $value]);
		}
		else if ($value != $default)
			return (new GlobalSetting([
				'key' => $key,
				'value' => $value,
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
	public static function process(string $key){
		$value = CoreUtils::trim($_POST['value']);

		if ($value === '')
			return null;

		switch ($key){
			case 'reservation_rules':
			case 'about_reservations':
				$value = CoreUtils::sanitizeHtml($value, $key === 'reservation_rules'? ['li', 'ol'] : ['p']);
			break;
		}

		return $value;
	}
}
