<?php

	class UserPrefs extends GlobalSettings {
		protected static
			$_db = 'user_prefs',
			$_defaults = array(
				'cg_itemsperpage' => 7,
				'cg_hidesynon' => 0,
				'cg_hideclrinfo' => 0,
			);

		/**
		 * Gets a user preference item's value
		 *
		 * @param string $key
		 * @param mixed  $default
		 *
		 * @return mixed
		 */
		static function Get($key, $default = false){
			global $Database, $signedIn, $currentUser;

			if (isset(User::$_PREF_CACHE[$key]))
				return User::$_PREF_CACHE[$key];

			if (isset(static::$_defaults[$key]))
				$default = static::$_defaults[$key];
			if (!$signedIn)
				return $default;
			$Database->where('user', $currentUser['id']);
			return User::$_PREF_CACHE[$key] = parent::Get($key, $default);
		}

		/**
		 * Sets a preference item's value
		 *
		 * @param string $key
		 * @param mixed $value
		 *
		 * @return bool
		 */
		static function Set($key, $value){
			global $Database, $signedIn, $currentUser;

			if (!$signedIn)
				CoreUtils::Respond();

			if (!isset(static::$_defaults[$key]))
				CoreUtils::Respond("Key $key is not allowed");
			$default = static::$_defaults[$key];

			if ($Database->where('key', $key)->where('user', $currentUser['id'])->has(static::$_db)){
				$Database->where('key', $key)->where('user', $currentUser['id']);
				if ($value == $default)
					return $Database->delete(static::$_db);
				else return $Database->update(static::$_db, array('value' => $value));
			}
			else if ($value != $default)
				return $Database->insert(static::$_db, array('user' => $currentUser['id'], 'key' => $key, 'value' => $value));
			else return true;
		}

		/**
		 * Processes a preference item's new value
		 *
		 * @param string $key
		 *
		 * @return mixed
		 */
		static function Process($key){
			$value = isset($_POST['value']) ? CoreUtils::Trim($_POST['value']) : null;

			if ($value === '')
				return null;

			switch ($key){
				case "cg_itemsperpage":
					$thing = 'Color Guide items per page';
					if (!is_numeric($value))
						throw new Exception("$thing must be a number");
					$value = intval($value, 10);
					if ($value < 7 || $value > 20)
						throw new Exception("$thing must be between 7 and 20");
				break;
				case "cg_hidesynon":
				case "cg_hideclrinfo":
					$value = (bool) $value;
				break;
			}

			return $value;
		}
	}
