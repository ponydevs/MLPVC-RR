<?php

namespace App;

use App\Models\UserPref;
use App\Models\User;

class UserPrefs extends GlobalSettings {
	/** @see process */
	const DEFAULTS = [
		'cg_itemsperpage' => 7,
		'cg_hidesynon' => 0,
		'cg_hideclrinfo' => 0,
		'cg_fulllstprev' => 1,
		'p_vectorapp' => '',
		'p_hidediscord' => 0,
		'p_hidepcg' => 0,
		'ep_noappprev' => 0,
		'ep_revstepbtn' => 0,
	];

	/**
	 * Gets a user preference item's value
	 *
	 * @param string $key
	 * @param User   $for
	 *
	 * @return mixed
	 */
	public static function get(string $key, ?User $for = null){
		if (empty($for) && Auth::$signed_in)
			$for = Auth::$user;

		$for_set = $for !== null && !empty($for->id);

		if ($for_set && isset(Users::$_PREF_CACHE[$for->id][$key]))
			return Users::$_PREF_CACHE[$for->id][$key];

		$default = null;
		if (isset(static::DEFAULTS[$key]))
			$default = static::DEFAULTS[$key];
		if (!$for_set && !Auth::$signed_in)
			return $default;

		$q = UserPref::find_for($key, $for);
		$value = isset($q->value) ? $q->value : $default;
		if ($for_set)
			Users::$_PREF_CACHE[$for->id][$key] = $value;
		return $value;
	}

	/**
	 * Sets a preference item's value
	 *
	 * @param string $key
	 * @param mixed  $value
	 * @param User $for
	 *
	 * @return bool
	 */
	public static function set(string $key, $value, ?User $for = null):bool {
		if (empty($for)){
			if (!Auth::$signed_in)
				throw new \RuntimeException("Empty \$for when setting user preference $key to ");
			$for = Auth::$user;
		}

		if (!isset(static::DEFAULTS[$key]))
			Response::fail("Key $key is not allowed");
		$default = static::DEFAULTS[$key];

		if (UserPref::has($key, $for)){
			$pref = UserPref::find_for($key, $for);
			if ($value == $default)
				return $pref->delete();
			else return $pref->update_attributes(['value' => $value]);
		}
		else if ($value != $default){
			return (new UserPref([
				'user_id' => Auth::$user->id,
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
	public static function process(string $key){
		$value = isset($_POST['value']) ? CoreUtils::trim($_POST['value']) : null;

		switch ($key){
			case 'cg_itemsperpage':
				$thing = 'Color Guide items per page';
				if (!is_numeric($value))
					throw new \Exception("$thing must be a number");
				$value = intval($value, 10);
				if ($value < 7 || $value > 20)
					throw new \Exception("$thing must be between 7 and 20");
			break;
			case 'p_vectorapp':
				if (!empty($value) && !isset(CoreUtils::$VECTOR_APPS[$value]))
					throw new \Exception('The specified app is invalid');
			break;
			case 'p_hidediscord':
			case 'cg_hidesynon':
			case 'cg_hideclrinfo':
			case 'cg_fulllstprev':
			case 'ep_revstepbtn':
				$value = $value ? 1 : 0;
			break;

			case 'discord_token':
				Response::fail("You cannot change the $key setting");
		}

		return $value;
	}
}
