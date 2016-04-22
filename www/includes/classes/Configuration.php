<?php

	class Configuration {
		/**
		 * Gets a global cofiguration item's value
		 *
		 * @param string $key
		 *
		 * @return mixed
		 */
		static function Get($key){
			global $Database;

			$q = $Database->where('key', $key)->getOne('global_settings','value');
			return isset($q['value']) ? $q['value'] : false;
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

			return $Database->where('key', $key)->update('global_settings', array('value' => $value));
		}

		/**
		 * Processes a configuration item's new value
		 *
		 * @param string $key
		 *
		 * @return mixed
		 */
		static function Process($key){
			$value = trim($_POST['value']);

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
