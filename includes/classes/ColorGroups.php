<?php

namespace App;



class ColorGroups {
	/**
	 * Get color groups
	 *
	 * @param int      $PonyID
	 * @param string   $cols
	 * @param string   $sort_dir
	 * @param int|null $cnt
	 *
	 * @return array
	 */
	static function get($PonyID, $cols = '*', $sort_dir = 'ASC', $cnt = null){
		global $Database;

		self::_order($sort_dir);
		$Database->where('ponyid',$PonyID);
		return $cnt === 1 ? $Database->getOne('colorgroups',$cols) : $Database->get('colorgroups',$cnt,$cols);
	}

	/**
	 * Get the colors belonging to a color group
	 *
	 * @param int $GroupID
	 *
	 * @return array
	 */
	static function getColors($GroupID){
		global $Database;

		return $Database->where('groupid', $GroupID)->orderBy('"order"', 'ASC')->get('colors');
	}

	/**
	 * Get the colors belonging to a set of color groups
	 *
	 * @param array $Groups
	 *
	 * @return array
	 */
	static function getColorsForEach($Groups){
		global $Database;

		$GroupIDs = [];
		foreach ($Groups as $g)
			$GroupIDs[] = $g['groupid'];
		if (empty($GroupIDs))
			return null;

		$data = $Database->where('groupid IN ('.implode(',',$GroupIDs).')')->orderBy('groupid','ASC')->orderBy('"order"', 'ASC')->get('colors');
		if (empty($data))
			return null;

		$sorted = [];
		foreach ($data as $row)
			$sorted[$row['groupid']][] = $row;
		return $sorted;
	}

	/**
	 * Get HTML for a color group
	 *
	 * @param int|array  $GroupID
	 * @param array|null $AllColors
	 * @param bool       $wrap
	 * @param bool       $colon
	 * @param bool       $colorNames
	 * @param bool       $force_extra_info
	 *
	 * @return string
	 */
	static function getHTML($GroupID, $AllColors = null, bool $wrap = true, bool $colon = true, bool $colorNames = false, bool $force_extra_info = false):string {
		global $Database;

		if (is_array($GroupID)) $Group = $GroupID;
		else $Group = $Database->where('groupid',$GroupID)->getOne('colorgroups');

		$label = CoreUtils::escapeHTML($Group['label']).($colon?': ':'');
		$HTML =
			"<span class='cat'>$label".
				($colorNames && Permission::sufficient('staff')?'<span class="admin"><button class="blue typcn typcn-pencil edit-cg"></button><button class="red typcn typcn-trash delete-cg"></button></span>':'').
			"</span>";
		if (!isset($AllColors))
			$Colors = self::getColors($Group['groupid']);
		else $Colors = $AllColors[$Group['groupid']] ?? null;
		if (!empty($Colors)){
			$extraInfo = $force_extra_info || !UserPrefs::get('cg_hideclrinfo');
			foreach ($Colors as $i => $c){
				$title = CoreUtils::aposEncode($c['label']);
				$color = '';
				if (!empty($c['hex'])){
					$color = $c['hex'];
					$title .= "' style='background-color:$color' class='valid-color";
				}

				$append = "<span title='$title'>$color</span>";
				if ($colorNames){
					$append = "<div class='color-line".(!$extraInfo || empty($color)?' no-detail':'')."'>$append<span><span class='label'>{$c['label']}";
					if ($extraInfo && !empty($color)){
						$rgb = CoreUtils::hex2Rgb($color);
						$rgb = 'rgb('.implode(',',$rgb).')';
						$append .= "</span><span class='ext'>$color &bull; $rgb";
					}
					$append .= '</span></div>';
				}
				$HTML .= $append;
			}
		}

		return $wrap ? "<li id='cg{$Group['groupid']}'>$HTML</li>" : $HTML;
	}

	/**
	 * Order color groups
	 *
	 * @param string $dir
	 */
	 private static function _order($dir = 'ASC'){
		global $Database;

		$Database
			->orderByLiteral('CASE WHEN "order" IS NULL THEN 1 ELSE 0 END', $dir)
			->orderBy('"order"', $dir)
			->orderBy('groupid', $dir);
	}

	static function stringifyColors($colors){
		if (empty($colors))
			return null;

		$return = [];
		foreach ($colors as $c)
			$return[] = "{$c['hex']} {$c['label']}";

		return implode("\n", $return);
	}

	static function stringify($cgs){
		if (empty($cgs))
			return null;

		$return = [];
		foreach ($cgs as $i => $c)
			$return[] = $c['label'];

		return implode("\n", $return);
	}
}
