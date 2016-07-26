<?php

	namespace CG;

	class Appearances {
		/**
		 * @param bool      $EQG
		 * @param int|int[] $limit
		 * @param  string   $cols
		 *
		 * @return array
		 */
		static function Get($EQG, $limit = null, $cols = '*'){
			global $CGDb;

			self::_order();
			if (isset($EQG))
				$CGDb->where('ishuman', $EQG)->where('id',0,'!=');
			return $CGDb->get('appearances', $limit, $cols);
		}

		/**
		 * Order appearances
		 *
		 * @param string $dir
		 */
		private static function _order($dir = 'ASC'){
			global $CGDb;
			$CGDb
				->orderByLiteral('CASE WHEN "order" IS NULL THEN 1 ELSE 0 END', $dir)
				->orderBy('"order"', $dir)
				->orderBy('id', $dir);
		}

		/**
		 * @param array $Appearances
		 * @param bool  $wrap
		 *
		 * @return string
		 */
		static function GetHTML($Appearances, $wrap = WRAP){
			global $CGDb, $_MSG, $Search;

			$HTML = '';
			if (!empty($Appearances)) foreach ($Appearances as $p){
				$p['label'] = \CoreUtils::EscapeHTML($p['label']);

				$img = self::GetSpriteHTML($p);
				$updates = self::GetUpdatesHTML($p['id']);
				$notes = self::GetNotesHTML($p);
				$tags = $p['id'] ? self::GetTagsHTML($p['id'], true, $Search) : '';
				$colors = self::GetColorsHTML($p['id']);
				$eqgp = $p['ishuman'] ? 'eqg/' : '';

				$RenderPath = APPATH."img/cg_render/{$p['id']}.png";
				$FileModTime = '?t='.(file_exists($RenderPath) ? filemtime($RenderPath) : time());
				$Actions = "<a class='btn typcn typcn-image darkblue' title='View as PNG' href='/cg/{$eqgp}v/{$p['id']}.png$FileModTime' target='_blank'></a>".
				           "<button class='getswatch typcn typcn-brush teal' title='Download swatch file'></button>";
				if (\Permission::Sufficient('staff'))
					$Actions .= "<button class='edit typcn typcn-pencil blue' title='Edit'></button>".
					            ($p['id']!==0?"<button class='delete typcn typcn-trash red' title='Delete'></button>":'');
				$safelabel = self::GetSafeLabel($p);
				$HTML .= "<li id='p{$p['id']}'>$img<div><strong><a href='/cg/v/{$p['id']}-$safelabel'>{$p['label']}</a>$Actions</strong>$updates$notes$tags$colors</div></li>";
			}
			else {
				if (empty($_MSG))
					$_MSG = "No appearances to show";
				$HTML .= "<div class='notice info align-center'><label>$_MSG</label></div>";
			}

			return $wrap ? "<ul id='list' class='appearance-list'>$HTML</ul>" : $HTML;
		}

		/**
		 * Returns the markup of the color list for a specific appearance
		 *
		 * @param int $PonyID
		 * @param bool $wrap
		 * @param bool $colon
		 * @param bool $colorNames
		 *
		 * @return string
		 */
		static function GetColorsHTML($PonyID, $wrap = WRAP, $colon = true, $colorNames = false){
			global $CGDb;

			$ColorGroups = ColorGroups::Get($PonyID);
			$AllColors = ColorGroups::GetColorsForEach($ColorGroups);

			$HTML = '';
			if (!empty($ColorGroups)) foreach ($ColorGroups as $cg)
				$HTML .= ColorGroups::GetHTML($cg, $AllColors, WRAP, $colon, $colorNames);

			return $wrap ? "<ul class='colors'>$HTML</ul>" : $HTML;
		}

		/**
		 * Return the markup of a set of tags belonging to a specific pony
		 *
		 * @param int         $PonyID
		 * @param bool        $wrap
		 * @param string|null $Search
		 *
		 * @return string
		 */
		static function GetTagsHTML($PonyID, $wrap = WRAP, $Search = null){
			global $CGDb;

			$Tags = Tags::GetFor($PonyID, null, \Permission::Sufficient('staff'));

			$HTML = '';
			if (\Permission::Sufficient('staff') && $PonyID !== 0)
				$HTML .= "<input type='text' class='addtag tag' placeholder='Enter tag' pattern='".TAG_NAME_PATTERN."' maxlength='30' required>";
			$HideSynon = \Permission::Sufficient('staff') && \UserPrefs::Get('cg_hidesynon');
			if (!empty($Tags)) foreach ($Tags as $i => $t){
				$isSynon = !empty($t['synonym_of']);
				$searchedFor = !empty($Search) && in_array($t['tid'],$Search['orig_tid']);
				if ($isSynon && $HideSynon && !$searchedFor)
					continue;
				$class = " class='tag id-{$t['tid']}".($isSynon?' synonym':'').(!empty($t['type'])?' typ-'.$t['type']:'')."'";
				$title = !empty($t['title']) ? " title='".\CoreUtils::AposEncode($t['title'])."'" : '';
				if ($searchedFor || (\Permission::Insufficient('staff') && !empty($Search['tid_assoc'][$t['tid']])))
					$t['name'] = "<mark>{$t['name']}</mark>";
				$syn_of = $isSynon ? " data-syn-of='{$t['synonym_of']}'" : '';
				$HTML .= "<span$class$title$syn_of>{$t['name']}</span>";
			}

			return $wrap ? "<div class='tags'>$HTML</div>" : $HTML;
		}

		/**
		 * Get the notes for a specific appearance
		 *
		 * @param array $Appearance
		 * @param bool  $wrap
		 * @param bool  $cmLink
		 *
		 * @return string
		 */
		static function GetNotesHTML($Appearance, $wrap = WRAP, $cmLink = true){
			global $EPISODE_ID_REGEX;

			$hasNotes = !empty($Appearance['notes']);
			$hasCM = !empty($Appearance['cm_favme']) && $cmLink !== NOTE_TEXT_ONLY;
			if ($hasNotes || $hasCM){
				$notes = '';
				if ($hasNotes){
					$Appearance['notes'] = preg_replace_callback('/'.EPISODE_ID_PATTERN.'/',function($a){
						$Ep = \Episode::GetActual((int) $a[1], (int) $a[2]);
						return !empty($Ep)
							? "<a href='/episode/S{$Ep['season']}E{$Ep['episode']}'>".\CoreUtils::AposEncode(\Episode::FormatTitle($Ep,AS_ARRAY,'title'))."</a>"
							: "<strong>{$a[0]}</strong>";
					},$Appearance['notes']);
					$notes = '<span>'.nl2br($Appearance['notes']).'</span>';
				}
				if ($hasCM){
					$dir = '';
					if (isset($Appearance['cm_dir'])){
						$head_to_tail = $Appearance['cm_dir'] === CM_DIR_HEAD_TO_TAIL;
						$CMPreviewUrl = self::GetCMPreviewURL($Appearance);
						$dir = ' <span class="cm-direction" data-cm-preview="'.$CMPreviewUrl.'" data-cm-dir="'.($head_to_tail ? 'ht' : 'th').'"><span class="typcn typcn-info-large"></span> '.($head_to_tail ? 'Head-Tail' : 'Tail-Head').' orientation</span>';
					}
					$notes .= "<a href='http://fav.me/{$Appearance['cm_favme']}'><span>Cutie Mark</span>$dir</a>";
				}
			}
			else {
				if (!\Permission::Sufficient('staff')) return '';
				$notes = '';
			}
			return $wrap ? "<div class='notes'>$notes</div>" : $notes;
		}

		/**
		 * Get sprite URL for an appearance
		 *
		 * @param int    $AppearanceID
		 * @param string $fallback
		 *
		 * @return string
		 */
		static function GetSpriteURL(int $AppearanceID, string $fallback = ''):string {
			$fpath = SPRITE_PATH."$AppearanceID.png";
			if (file_exists($fpath))
				return "/cg/v/{$AppearanceID}s.png?t=".filemtime($fpath);
			return $fallback;
		}

		/**
		 * Returns the HTML for sprite images
		 *
		 * @param array $Appearance
		 *
		 * @return string
		 */
		static function GetSpriteHTML($Appearance){
			$imgPth = self::GetSpriteURL($Appearance['id']);
			if (!empty($imgPth)){
				$img = "<a href='$imgPth' target='_blank' title='Open image in new tab'><img src='$imgPth' alt='".\CoreUtils::AposEncode($Appearance['label'])."'></a>";
				if (\Permission::Sufficient('staff'))
					$img = "<div class='upload-wrap'>$img</div>";
			}
			else if (\Permission::Sufficient('staff'))
				$img = "<div class='upload-wrap'><a><img src='/img/blank-pixel.png'></a></div>";
			else return '';

			return "<div class='sprite'>$img</div>";
		}

		/**
		 * Returns the markup for the time of last update displayed under an appaerance
		 *
		 * @param int  $PonyID
		 * @param bool $wrap
		 *
		 * @return string
		 */
		static function GetUpdatesHTML($PonyID, $wrap = WRAP){
			global $Database;

			$update = Updates::Get($PonyID, MOST_RECENT);
			if (!empty($update)){
				$update = "Last updated ".\Time::Tag($update['timestamp']);
			}
			else {
				if (!\Permission::Sufficient('staff')) return '';
				$update = '';
			}
			return $wrap ? "<div class='update'>$update</div>" : $update;
		}

		/**
		 * Sort appearances based on tags
		 *
		 * @param array $Appearances
		 * @param bool  $simpleArray
		 *
		 * @return array
		 */
		static function Sort($Appearances, $simpleArray = false){
			global $CGDb;
			$GroupTagIDs = array_keys(\CGUtils::$GroupTagIDs_Assoc);
			$Sorted = array();
			$Tagged = array();
			foreach ($CGDb->where('tid IN ('.implode(',',$GroupTagIDs).')')->orderBy('ponyid','ASC')->get('tagged') as $row)
				$Tagged[$row['ponyid']][] = $row['tid'];
			foreach ($Appearances as $p){
				if (!empty($Tagged[$p['id']])){
					if (count($Tagged[$p['id']]) > 1)
						usort($Tagged[$p['id']],function($a,$b) use ($GroupTagIDs){
							return array_search($a, $GroupTagIDs) - array_search($b, $GroupTagIDs);
						});
					$tid = $Tagged[$p['id']][0];
				}
				else $tid = -1;
				$Sorted[$tid][] = $p;
			}
			if ($simpleArray){
				$idArray = array();
				foreach (\CGUtils::$GroupTagIDs_Assoc as $Category => $CategoryName){
					if (empty($Sorted[$Category]))
						continue;
					foreach ($Sorted[$Category] as $p)
						$idArray[] = $p['id'];
				}
				return $idArray;
			}
			else return $Sorted;
		}

		/**
		 * @param string|int[] $ids
		 */
		static function Reorder($ids){
			global $CGDb;
			if (empty($ids))
				return;

			$list = is_string($ids) ? explode(',', $ids) : $ids;
			$order = 1;
			foreach ($list as $id){
				if (!$CGDb->where('id', $id)->update('appearances', array('order' => $order++)))
					\Response::Fail("Updating appearance #$id failed, process halted");
			}
		}


		/**
		 * @param bool $EQG
		 */
		static function GetSortReorder($EQG){
			if ($EQG)
				return;
			self::Reorder(self::Sort(self::Get($EQG,null,'id'), SIMPLE_ARRAY));
		}

		/**
		 * Apply pre-defined template to an appearance
		 * $EQG controls whether to apply EQG or Pony template
		 *
		 * @param int $PonyID
		 * @param bool $EQG
		 *
		 * @throws \Exception
		 */
		static function ApplyTemplate($PonyID, $EQG){
			global $CGDb, $Color;

			if (empty($PonyID) || !is_numeric($PonyID))
				throw new \Exception('Incorrect value for $PonyID while applying template');

			if ($CGDb->where('ponyid', $PonyID)->has('colorgroups'))
				throw new \Exception('Template can only be applied to empty appearances');

			$Scheme = $EQG
				? array(
					'Skin' => array(
						'Outline',
						'Fill',
					),
					'Hair' => array(
						'Outline',
						'Fill',
					),
					'Eyes' => array(
						'Gradient Top',
						'Gradient Middle',
						'Gradient Bottom',
						'Highlight Top',
						'Highlight Bottom',
						'Eyebrows',
					),
				)
				: array(
					'Coat' => array(
						'Outline',
						'Fill',
						'Shadow Outline',
						'Shadow Fill',
					),
					'Mane & Tail' => array(
						'Outline',
						'Fill',
					),
					'Iris' => array(
						'Gradient Top',
						'Gradient Middle',
						'Gradient Bottom',
						'Highlight Top',
						'Highlight Bottom',
					),
					'Cutie Mark' => array(
						'Fill 1',
						'Fill 2',
					),
					'Magic' => array(
						'Aura',
					),
				);

			$cgi = 0;
			$ci = 0;
			foreach ($Scheme as $GroupName => $ColorNames){
				$GroupID = $CGDb->insert('colorgroups',array(
					'ponyid' => $PonyID,
					'label' => $GroupName,
					'order' => $cgi++,
				), 'groupid');
				if (!$GroupID)
					throw new \Exception(rtrim("Color group \"$GroupName\" could not be created: ".$CGDb->getLastError()), ': ');

				foreach ($ColorNames as $label){
					if (!$CGDb->insert('colors',array(
						'groupid' => $GroupID,
						'label' => $label,
						'order' => $ci++,
					))) throw new \Exception(rtrim("Color \"$label\" could not be added: ".$CGDb->getLastError()), ': ');
				}
			}
		}

		/**
		 * Returns the HTML of the "Appears in # episodes" section of appearance pages
		 *
		 * @param int  $AppearanceID
		 *
		 * @return string
		 */
		static function GetRelatedEpisodesHTML($AppearanceID){
			global $CGDb;

			$EpTagsOnAppearance = $CGDb->rawQuery(
				"SELECT t.tid
				FROM tagged tt
				LEFT JOIN tags t ON tt.tid = t.tid
				WHERE tt.ponyid = ? &&  t.type = 'ep'",array($AppearanceID));

			if (!empty($EpTagsOnAppearance)){
				foreach ($EpTagsOnAppearance as $k => $row)
					$EpTagsOnAppearance[$k] = $row['tid'];

				$EpAppearances = $CGDb->rawQuery("SELECT DISTINCT name FROM tags WHERE tid IN (".implode(',',$EpTagsOnAppearance).") ORDER BY name");
				if (empty($EpAppearances))
					return '';

				$List = '';
				foreach ($EpAppearances as $tag){
					$name = strtoupper($tag['name']);
					$EpData = \Episode::ParseID($name);
					$Ep = \Episode::GetActual($EpData['season'], $EpData['episode']);
					$List .= (
						empty($Ep)
						? self::ExpandEpisodeTagName($name)
						: "<a href='/episode/S{$Ep['season']}E{$Ep['episode']}'>".\Episode::FormatTitle($Ep).'</a>'
					).', ';
				}
				$List = rtrim($List, ', ');
				$N_episodes = \CoreUtils::MakePlural('episode',count($EpAppearances),PREPEND_NUMBER);
				$hide = '';
			}
			else {
				$N_episodes = 'no episodes';
				$List = '';
				$hide = 'style="display:none"';
			}

			return <<<HTML
		<section id="ep-appearances" $hide>
			<h2><span class='typcn typcn-video'></span>Appears in $N_episodes</h2>
			<p>$List</p>
		</section>
HTML;
		}
		/**
		 * Turns "S#E#" into "S0# E0#"
		 *
		 * @param string $tagname
		 *
		 * @return string
		 */
		static function ExpandEpisodeTagName($tagname){
			global $EPISODE_ID_REGEX;
			
			regex_match($EPISODE_ID_REGEX, $tagname, $_match);
			
			return 'S'.\CoreUtils::Pad($_match[1]).' E'.\CoreUtils::Pad($_match[2]);
		}

		/**
		 * Retruns CM preview image link
		 *
		 * @param array $Appearance
		 *
		 * @return string
		 */
		static function GetCMPreviewURL($Appearance){
			return $Appearance['cm_preview'] ?? \DeviantArt::GetCachedSubmission($Appearance['cm_favme'])['preview'];
		}

		/**
		 * Replaces non-alphanumeric characters in the appearance label with dashes
		 *
		 * @param array $Appearance
		 *
		 * @return string
		 */
		static function GetSafeLabel($Appearance){
			return \CoreUtils::Trim(regex_replace(new \RegExp('-+'),'-',regex_replace(new \RegExp('[^A-Za-z\d\-]'),'-',$Appearance['label'])),'-');
		}

		static function GetSpriteColorMap($AppearanceID){
			global $Database;
			$ColorMapData = $Database->where('ponyid', $AppearanceID)->get('cg_sprite_colormap', null, 'placeholder,actual');
			$ColorMap = array();
			foreach ($ColorMapData as $row)
				$ColorMap[\CGUtils::Int2Hex($row['placeholder'])] = $row['actual'];
			return $ColorMap;
		}

		static function ValidateAppearancePageID(){
			return (new \Input('APPEARANCE_PAGE','int',array(
				\Input::IS_OPTIONAL => true,
				\Input::IN_RANGE => [0,null],
				\Input::CUSTOM_ERROR_MESSAGES => array(
					\Input::ERROR_RANGE => 'Appearance ID is invalid'
				)
			)))->out();
		}
	}
