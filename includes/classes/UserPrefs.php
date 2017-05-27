<?php

namespace App;

use PHPUnit\Runner\Exception;

class UserPrefs extends GlobalSettings {
	protected static
		$_db = 'user_prefs',
		$_defaults = array(
			'discord_token' => '',

			'cg_itemsperpage' => 7,
			'cg_hidesynon' => 0,
			'cg_hideclrinfo' => 0,
			'cg_fulllstprev' => 1,
			'p_vectorapp' => '',
			'p_hidediscord' => 0,
			'p_hidepcg' => 0,
			'ep_noappprev' => 0,
		);

	/**
	 * Gets a user preference item's value
	 *
	 * @param string $key
	 * @param string $for
	 *
	 * @return mixed
	 */
	static function get(string $key, $for = null){
		global $Database;
		if (empty($for) && Auth::$signed_in)
			$for = Auth::$user->id;

		if (isset(Users::$_PREF_CACHE[$for][$key]))
			return Users::$_PREF_CACHE[$for][$key];

		$default = null;
		if (isset(static::$_defaults[$key]))
			$default = static::$_defaults[$key];
		if (empty($for) && !Auth::$signed_in)
			return $default;

		$Database->where('user', $for);
		return Users::$_PREF_CACHE[$for][$key] = parent::get($key, $default);
	}

	/**
	 * Sets a preference item's value
	 *
	 * @param string $key
	 * @param mixed  $value
	 * @param string $for
	 *
	 * @return bool
	 */
	static function set(string $key, $value, $for = null):bool {
		global $Database;
		if (empty($for)){
			if (!Auth::$signed_in)
				throw new Exception("Empty \$for when setting user preference $key to ");
			$for = Auth::$user->id;
		}

		if (!isset(static::$_defaults[$key]))
			Response::fail("Key $key is not allowed");
		$default = static::$_defaults[$key];

		if ($Database->where('key', $key)->where('user', $for)->has(static::$_db)){
			$Database->where('key', $key)->where('user', $for);
			if ($value == $default)
				return $Database->delete(static::$_db);
			else return $Database->update(static::$_db, array('value' => $value));
		}
		else if ($value != $default)
			return $Database->insert(static::$_db, array('user' => Auth::$user->id, 'key' => $key, 'value' => $value));
		else return true;
	}

	/**
	 * Processes a preference item's new value
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	static function process(string $key){
		$value = isset($_POST['value']) ? CoreUtils::trim($_POST['value']) : null;

		switch ($key){
			case "cg_itemsperpage":
				$thing = 'Color Guide items per page';
				if (!is_numeric($value))
					throw new \Exception("$thing must be a number");
				$value = intval($value, 10);
				if ($value < 7 || $value > 20)
					throw new \Exception("$thing must be between 7 and 20");
			break;
			case "p_vectorapp":
				if (!empty($value) && !isset(CoreUtils::$VECTOR_APPS[$value]))
					throw new \Exception("The specified app is invalid");
			break;
			case "p_hidediscord":
			case "cg_hidesynon":
			case "cg_hideclrinfo":
			case "cg_fulllstprev":
				$value = $value ? 1 : 0;
			break;

			case "discord_token":
				Response::fail("You cannot change the $key setting");
		}

		return $value;
	}
}
