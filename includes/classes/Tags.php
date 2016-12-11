<?php

namespace App;

use App\Permission;
use App\CoreUtils;

class Tags {
	// List of available tag types
	static $TAG_TYPES_ASSOC = array(
		'app' => 'Clothing',
		'cat' => 'Category',
		'ep' => 'Episode',
		'gen' => 'Gender',
		'spec' => 'Species',
		'char' => 'Character',
	);

	/**
	 * Retrieve set of tags for a given appearance
	 *
	 * @param int       $PonyID
	 * @param array|int $limit
	 * @param bool      $showEpTags
	 * @param bool      $exporting
	 *
	 * @return array|null
	 */
	static function getFor($PonyID = null, $limit = null, $showEpTags = false, $exporting = false){
		global $CGDb;

		if (!$exporting){
			$showSynonymTags = $showEpTags || Permission::sufficient('staff');
			if (!$showSynonymTags)
				$CGDb->where('"synonym_of" IS NULL');

			$CGDb
				->orderByLiteral('CASE WHEN tags.type IS NULL THEN 1 ELSE 0 END')
				->orderBy('tags.type', 'ASC')
				->orderBy('tags.name', 'ASC');
			if (!$showEpTags)
				$CGDb->where("tags.type != 'ep'");
		}
		else {
			$showSynonymTags = true;
			if (isset($PonyID))
				$ODER_BY = ' ORDER BY tags.tid ASC';
			else $CGDb->orderBy('tags.tid','ASC');
		}
		return isset($PonyID)
			? $CGDb->rawQuery(
				'SELECT tags.* FROM tagged
				LEFT JOIN tags ON (tagged.tid = tags.tid'.($showSynonymTags?' OR tagged.tid = tags.synonym_of':'').')'.
				"WHERE tagged.ponyid = ?".($ODER_BY ?? '').
				(isset($limit)?"LIMIT $limit[1] OFFSET $limit[0]":''), array($PonyID)
			)
			: $CGDb->get('tags',$limit);
	}

	/**
	 * Gets a specifig tag while resolving synonym relations
	 *
	 * @param mixed  $value
	 * @param string $column
	 * @param bool   $as_bool Return a boolean reflecting existence
	 *
	 * @return array|bool
	 */
	static function getActual($value, $column = 'tid', $as_bool = false){
		global $CGDb;

		$arg1 = array('tags', $as_bool === RETURN_AS_BOOL ? 'synonym_of,tid' : '*');

		$Tag = $CGDb->where($column, $value)->getOne(...$arg1);

		if (!empty($Tag['synonym_of'])){
			$arg2 = $as_bool === RETURN_AS_BOOL ? 'tid' : $arg1[1];
			$OrigTag = $Tag;
			$Tag = self::getSynonymOf($Tag, $arg2);
			$Tag['Original'] = $OrigTag;
		}

		return $as_bool === RETURN_AS_BOOL ? !empty($Tag) : $Tag;
	}

	/**
	 * Gets the tag which the specified tag is a synonym of
	 *
	 * @param array       $Tag
	 * @param string|null $returnCols
	 *
	 * @return array
	 */
	static function getSynonymOf($Tag, $returnCols = null){
		global $CGDb;

		if (empty($Tag['synonym_of']))
			return null;

		return $CGDb->where('tid', $Tag['synonym_of'])->getOne('tags',$returnCols);
	}

	/**
	 * Update use count on a tag
	 *
	 * @param int  $TagID
	 * @param bool $returnCount
	 *
	 * @return array
	 */
	static function updateUses(int $TagID, bool $returnCount = false):array {
		global $CGDb;

		$Tagged = $CGDb->where('tid', $TagID)->count('tagged');
		$return = array('status' => $CGDb->where('tid', $TagID)->update('tags',array('uses' => $Tagged)));
		if ($returnCount)
			$return['count'] = $Tagged;
		return $return;
	}

	/**
	 * Generates the markup for the tags sub-page
	 *
	 * @param array $Tags
	 * @param bool  $wrap
	 *
	 * @return string
	 */
	static function getTagListHTML($Tags, $wrap = WRAP){
		global $CGDb;
		$HTML =
		$utils =
		$refresh = '';

		$canEdit = Permission::sufficient('staff');
		if ($canEdit){
			$refresh = " <button class='typcn typcn-arrow-sync refresh' title='Refresh use count'></button>";
			$utils = "<td class='utils align-center'><button class='typcn typcn-minus delete' title='Delete'></button> ".
			         "<button class='typcn typcn-flow-merge merge' title='Merge'></button> <button class='typcn typcn-flow-children synon' title='Synonymize'></button></td>";
		}

		if (!empty($Tags)) foreach ($Tags as $t){
			$trClass = $t['type'] ? " class='typ-{$t['type']}'" : '';
			$type = $t['type'] ? self::$TAG_TYPES_ASSOC[$t['type']] : '';
			$search = CoreUtils::aposEncode(urlencode($t['name']));
			$titleName = CoreUtils::aposEncode($t['name']);

			if (!empty($t['synonym_of'])){
				$Syn = self::getSynonymOf($t,'name');
				$t['title'] .= (empty($t['title'])?'':'<br>')."<em>Synonym of <strong>{$Syn['name']}</strong></em>";
			}

			$HTML .= <<<HTML
			<tr $trClass>
				<td class="tid">{$t['tid']}</td>
				<td class="name"><a href='/cg?q=$search' title='Search for $titleName'><span class="typcn typcn-zoom"></span>{$t['name']}</a></td>$utils
				<td class="title">{$t['title']}</td>
				<td class="type">$type</td>
				<td class="uses"><span>{$t['uses']}</span>$refresh</td>
			</tr>
HTML;
		}

		return $wrap ? "<tbody>$HTML</tbody>" : $HTML;
	}
}
