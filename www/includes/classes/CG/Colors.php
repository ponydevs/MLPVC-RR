<?php

	namespace CG;

	class Colors {
		/**
		 * Get the colors belonging to a color group
		 *
		 * @param int $GroupID
		 *
		 * @return array
		 */
		static function Get($GroupID){
			global $CGDb;

			return $CGDb->where('groupid', $GroupID)->orderBy('groupid', 'ASC')->orderBy('"order"', 'ASC')->get('colors');
		}
	}
