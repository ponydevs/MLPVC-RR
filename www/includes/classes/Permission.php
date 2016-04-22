<?php

	class Permission {
		public static
			$ROLES_ASSOC = array(
				'ban'       =>  'Banished User',
				'user'      =>  'deviantArt User',
				'guest'     =>  'Guest',
				'member'    =>  'Club Member',
				'manager'   =>  'Group Manager',
				'inspector' =>  'Vector Inspector',
				'developer' =>  'Site Developer',
			),
			$ROLES = array(
				'ban'       => 0,
				'guest'     => 1,
				'user'      => 2,
				'member'    => 3,
				'inspector' => 4,
				'manager'   => 5,
				'developer' => 255,
			);

		/**
		 * Permission checking function
		 * ----------------------------
		 * Compares the currenlty logged in user's role to the one specified
		 * A "true" retun value means that the user meets the required role or surpasses it.
		 * If user isn't logged in, and $compareAgainst is missing, returns false
		 * If $compareAgainst isn't missing, compare it to $role
		 *
		 * @param string $role
		 * @param string|null $compareAgainst
		 *
		 * @return bool
		 */
		static function Sufficient($role, $compareAgainst = null){
			if (!is_string($role)) return false;

			if (empty($compareAgainst)){
				global $signedIn, $currentUser;
				if (!$signedIn)
					return false;
				$checkRole = $currentUser['role'];
			}
			else $checkRole = $compareAgainst;

			if (!isset(self::$ROLES[$role]))
				throw new Exception('Invalid role: '.$role);
			$targetRole = $role;

			return self::$ROLES[$checkRole] >= self::$ROLES[$targetRole];
		}

		/**
		 * Save as above, except the return value is inverted
		 * Added for better code readability
		 *
		 * @param string $role
		 * @param string|null $compareAgainst
		 *
		 * @return bool
		 */
		static function Insufficient($role, $compareAgainst = null){
			return !self::Sufficient($role, $compareAgainst);
		}

		/**
		 * Converts role label to badge initials
		 * -------------------------------------
		 * Related: http://stackoverflow.com/a/30740511/1344955
		 *
		 * @param string $label
		 *
		 * @return string
		 */
		static function LabelInitials($label){
			return regex_replace(new RegExp('(?:^|\s)([A-Z])|.'),'$1',$label);
		}
	}
