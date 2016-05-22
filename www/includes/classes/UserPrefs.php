<?php

	class UserPrefs extends GlobalSettings {
		protected static
			$_db = 'user_prefs',
			$_allowedKeys = array(
				'cg_itemsperpage' => true,
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

			if (!$signedIn)
				return $default;
			$Database->where('user', $currentUser['id']);
			return parent::Get($key, $default);
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

			if (!isset(static::$_allowedKeys[$key]))
				CoreUtils::Respond("Key $key is not allowed");

			if ($Database->where('key', $key)->where('user', $currentUser['id'])->has(static::$_db))
				return $Database->where('key', $key)->where('user', $currentUser['id'])->update(static::$_db, array('value' => $value));
			else return $Database->insert(static::$_db, array('user' => $currentUser['id'], 'key' => $key, 'value' => $value));
		}

		/**
		 * Processes a preference item's new value
		 *
		 * @param string $key
		 *
		 * @return mixed
		 */
		static function Process($key){
			$value = CoreUtils::Trim($_POST['value']);

			if ($value === '')
				return null;

			switch ($key){
				case "cg_itemsperpage":
					$thing = 'Color Guide items per page';
					if (!is_numeric($value))
						throw new Exception("$thing must be a number");
					if ($value < 7 || $value > 20)
						throw new Exception("$thing must be between 7 and 20");

				break;
			}

			return $value;
		}
	}
