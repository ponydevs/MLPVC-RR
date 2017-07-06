<?php

namespace App;

use App\Models\UserPref;

class UserPrefs extends GlobalSettings {
	const DEFAULTS = [
		'cg_itemsperpage' => 7,
		'cg_hidesynon' => 0,
		'cg_hideclrinfo' => 0,
		'cg_fulllstprev' => 1,
		'p_vectorapp' => '',
		'p_hidediscord' => 0,
		'p_hidepcg' => 0,
		'ep_noappprev' => 0,
	];

	/**
	 * Gets a user preference item's value
	 *
	 * @param string $key
	 * @param User   $for
	 *
	 * @return mixed
	 */
	static function get(string $key, $for = null){
		global $Database;
		if (empty($for) && Auth::$signed_in)
			$for = Auth::$user;

		if (isset(Users::$_PREF_CACHE[$for->id][$key]))
			return Users::$_PREF_CACHE[$for->id][$key];

		$default = null;
		if (isset(static::DEFAULTS[$key]))
			$default = static::DEFAULTS[$key];
		if (empty($for->id) && !Auth::$signed_in)
			return $default;

		$q = UserPref::find_for($key, $for);
		Users::$_PREF_CACHE[$for->id][$key] = isset($q->value) ? $q->value : $default;
		return Users::$_PREF_CACHE[$for->id][$key];
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
		if (empty($for)){
			if (!Auth::$signed_in)
				throw new \Exception("Empty \$for when setting user preference $key to ");
			$for = Auth::$user->id;
		}

		if (!isset(static::DEFAULTS[$key]))
			Response::fail("Key $key is not allowed");
		$default = static::DEFAULTS[$key];

		if (UserPref::exists($for, $key)){
			$pref = UserPref::find($for, $key);
			if ($value == $default)
				return $pref->delete();
			else return $pref->update_attributes(['value' => $value]);
		}
		else if ($value != $default){
			return (new UserPref([
				'user' => Auth::$user->id,
				'key' => $key,
				'value' => $value,
			]))->save();
		}
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
