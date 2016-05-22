<?php

	class GlobalSettings {
		protected static
			$_db = 'global_settings',
			$_allowedKeys = array(
				'reservation_rules' => true,
				'about_reservations' => true,
			);

		/**
		 * Gets a global cofiguration item's value
		 *
		 * @param string $key
		 * @param mixed  $default
		 *
		 * @return mixed
		 */
		static function Get($key, $default = false){
			global $Database;

			$q = $Database->where('key', $key)->getOne(static::$_db,'value');
			return isset($q['value']) ? $q['value'] : $default;
		}

		/**
		 * Sets a global cofiguration item's value
		 *
		 * @param string $key
		 * @param mixed $value
		 *
		 * @return bool
		 */
		static function Set($key, $value){
			global $Database;

			if (!isset(static::$_allowedKeys[$key]))
				CoreUtils::Respond("Key $key is not allowed");

			if ($Database->where('key', $key)->has(static::$_db))
				return $Database->where('key', $key)->update(static::$_db, array('value' => $value));
			else return $Database->insert(static::$_db, array('key' => $key, 'value' => $value));
		}

		/**
		 * Processes a configuration item's new value
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
				case "reservation_rules":
				case "about_reservations":
					$value = CoreUtils::SanitizeHtml($value, $key === 'reservation_rules'? array('li', 'ol') : array('p'));
				break;
			}

			return $value;
		}
	}
