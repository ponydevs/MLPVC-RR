<?php
	##########################################################
	## Utility functions related to the color guide feature ##
	##########################################################

	// Response creator for typeahead.js
	function typeahead_results($str){
		header('Content-Type: application/json');
		if (is_array($str))
			$str = JSON::Encode($str);
		die($str);
	}

	// Cutie Mark orientation related constants
	define('CM_DIR_TAIL_TO_HEAD', false);
	define('CM_DIR_HEAD_TO_TAIL', true);
	define('CM_DIR_UNSPECIFIED', '???');

	// Some patterns for validation
	define('TAG_NAME_PATTERN', '^[a-z\d ().\-\']{3,30}$');
	$TAG_NAME_REGEX = new RegExp(TAG_NAME_PATTERN,'u');
	define('INVERSE_TAG_NAME_PATTERN', '[^a-z\d ().\-\']');

	// EQG Color Guide URL pattern
	$EQG_URL_PATTERN = new RegExp('^eqg/');

	// Get the colors in a given color groups
	function get_colors($GroupID){
		global $CGDb;

		return $CGDb->where('groupid', $GroupID)->orderBy('groupid', 'ASC')->orderBy('"order"', 'ASC')->get('colors');
	}

	// Return the markup for the specified color group
	define('NO_COLON', false);
	define('OUTPUT_COLOR_NAMES', true);
	function get_cg_html($GroupID, $wrap = true, $colon = true, $colorNames = false){
		global $CGDb;

		if (is_array($GroupID)) $Group = $GroupID;
		else $Group = $CGDb->where('groupid',$GroupID)->getOne('colorgroups');

		$label = htmlspecialchars($Group['label']).($colon?': ':'');
		$HTML = $wrap ? "<li id='cg{$Group['groupid']}'>" : '';
		$HTML .=
			"<span class='cat'>$label".
				($colorNames?'<span class="admin"><button class="blue typcn typcn-pencil edit-cg"></button><button class="red typcn typcn-trash delete-cg"></button></span>':'').
			"</span>";
		$Colors = get_colors($Group['groupid']);
		if (!empty($Colors))
			foreach ($Colors as $i => $c){
				$title = apos_encode($c['label']);
				$color = '';
				if (!empty($c['hex'])){
					$color = $c['hex'];
					$title .= "' style='background-color:$color' class='valid-color'";
				}

				$append = "<span title='$title'>$color</span>";
				if ($colorNames)
					$append = "<div class='color-line'>$append<span>{$c['label']}</span></div>";
				$HTML .= $append;
			};

		if ($wrap) $HTML .= "</li>";

		return $HTML;
	}

	// Order color groups
	function order_cgs($dir = 'ASC'){
		global $CGDb;

		return $CGDb
			->orderByLiteral('CASE WHEN "order" IS NULL THEN 1 ELSE 0 END', $dir)
			->orderBy('"order"', $dir)
			->orderBy('groupid', $dir);
	}

	// Get color groups
	function get_cgs($PonyID, $cols = '*', $sort_dir = 'ASC', $cnt = null){
		global $CGDb;

		order_cgs($sort_dir);
		$CGDb->where('ponyid',$PonyID);
		return $cnt === 1 ? $CGDb->getOne('colorgroups',$cols) : $CGDb->get('colorgroups',$cnt,$cols);
	}

	// Returns the markup of the color list for a specific pony \\
	function get_colors_html($PonyID, $wrap = true, $colon = true, $colorNames = false){
		global $CGDb;

		$ColorGroups = get_cgs($PonyID);

		$HTML = $wrap ? "<ul class='colors'>" : '';
		if (!empty($ColorGroups)){
			foreach ($ColorGroups as $cg)
				$HTML .= get_cg_html($cg, WRAP, $colon, $colorNames);
		}
		if ($wrap) $HTML .= "</ul>";
		return $HTML;
	}

	/**
	 * Retrieve set of tags for a given appearance
	 *
	 * @param int $PonyID
	 * @param array|int $limit
	 * @param bool $showEpTags
	 *
	 * @return array|null
	 */
	function get_tags($PonyID = null, $limit = null, $showEpTags = false){
		global $CGDb;

		$showSynonymTags = $showEpTags || PERM('inspector');
		if (!$showSynonymTags)
			$CGDb->where('"synonym_of" IS NULL');

		$CGDb
			->orderByLiteral('CASE WHEN tags.type IS NULL THEN 1 ELSE 0 END')
			->orderBy('tags.type', 'ASC')
			->orderBy('tags.name', 'ASC');
		if (!$showEpTags)
			$CGDb->where("tags.type != 'ep'");
		return isset($PonyID)
			? $CGDb
				->join('tags','tagged.tid = tags.tid'.($showSynonymTags?' OR tagged.tid = tags.synonym_of':''),'LEFT')
				->where('tagged.ponyid',$PonyID)
				->get('tagged',$limit,'tags.*')
			: $CGDb->get('tags',$limit);
	}

	// Return the markup of a set of tags belonging to a specific pony \\
	function get_tags_html($PonyID, $wrap = true){
		global $CGDb;

		$Tags = get_tags($PonyID, null, PERM('inspector'));

		$HTML = $wrap ? "<div class='tags'>" : '';
		if (PERM('inspector') && $PonyID !== 0)
			$HTML .= "<input type='text' class='addtag tag' placeholder='Enter tag' pattern='".TAG_NAME_PATTERN."' maxlength='30' required>";
		if (!empty($Tags)) foreach ($Tags as $i => $t){
			$class = " class='tag id-{$t['tid']}".(!empty($t['synonym_of'])?' synonym':'').(!empty($t['type'])?' typ-'.$t['type']:'')."'";
			$title = !empty($t['title']) ? " title='".apos_encode($t['title'])."'" : '';
			$HTML .= "<span$class$title>{$t['name']}</span>";
		}
		if ($wrap) $HTML .= "</div>";

		return $HTML;
	}

	// List of available tag types
	$TAG_TYPES_ASSOC = array(
		'app' => 'Appearance',
		'cat' => 'Category',
		'ep' => 'Episode',
		'gen' => 'Gender',
		'spec' => 'Species',
		'char' => 'Character',
	);
	$TAG_TYPES = array_keys($TAG_TYPES_ASSOC);

	// Returns the markup for the notes displayed under an appaerance
	define('NOTE_TEXT_ONLY', false);
	function get_notes_html($p, $wrap = true, $cmLink = true){
		$hasNotes = !empty($p['notes']);
		$hasCM = !empty($p['cm_favme']) && $cmLink !== NOTE_TEXT_ONLY;
		if ($hasNotes || $hasCM){
			$notes = '';
			if ($hasNotes){
				if ($p['id'] !== 0)
					$p['notes'] = htmlspecialchars($p['notes']);
				$notes = '<span>'.nl2br($p['notes']).'</span>';
			}
			if ($hasCM){
				$dir = '';
				if (isset($p['cm_dir'])){
					$head_to_tail = $p['cm_dir'] === CM_DIR_HEAD_TO_TAIL;
					$CMPreviewUrl = get_cm_preview_url($p);
					$dir = ' <span class="cm-direction" data-cm-preview="'.$CMPreviewUrl.'" data-cm-dir="'.($head_to_tail ? 'ht' : 'th').'"><span class="typcn typcn-info-large"></span> '.($head_to_tail ? 'Head-Tail' : 'Tail-Head').' orientation';
				}
				$notes .= "<a href='http://fav.me/{$p['cm_favme']}'><span>Cutie Mark</span>$dir</a>";
			}
		}
		else {
			if (!PERM('inspector')) return '';
			$notes = '';
		}
		return $wrap ? "<div class='notes'>$notes</div>" : $notes;
	}
	// Returns the URL for sprite images
	define('DEFAULT_SPRITE', '/img/blank-pixel.png');
	function get_sprite_url($p, $fallback = null){
		$imgPth = "img/cg/{$p['id']}.png";
		if (file_Exists(APPATH.$imgPth)){
			$imgPth = $imgPth.'?'.filemtime(APPATH.$imgPth);
			return "/$imgPth";
		}
		return !empty($fallback) ? $fallback : '';
	}

	// Returns the HTML for sprite images
	function get_sprite_html($p){
		$imgPth = get_sprite_url($p);
		if (!empty($imgPth)){
			$img = "<a href='$imgPth' target='_blank' title='Open image in new tab'><img src='$imgPth' alt='".apos_encode($p['label'])."'></a>";
			if (PERM('inspector'))
				$img = "<div class='upload-wrap'>$img</div>";
		}
		else if (PERM('inspector'))
			$img = "<div class='upload-wrap'><a><img src='/img/blank-pixel.png'></a></div>";
		else return '';

		return "<div class='sprite'>$img</div>";
	}

	// Returns the markup for the time of last update displayed under an appaerance
	function get_update_html($PonyID, $wrap = true){
		global $Database;

		$update = get_updates($PonyID, MOST_RECENT);
		if (!empty($update)){
			$update = "Last updated ".timetag($update['timestamp']);
		}
		else {
			if (!PERM('inspector')) return '';
			$update = '';
		}
		return $wrap ? "<div class='update'>$update</div>" : $update;
	}

	function order_appearances($dir = 'ASC'){
		global $CGDb;

		$CGDb
			->orderByLiteral('CASE WHEN "order" IS NULL THEN 1 ELSE 0 END', $dir)
			->orderBy('"order"', $dir)
			->orderBy('id', $dir);
	}

	// Get list of ponies
	function get_appearances($EQG, $limit = null, $cols = '*'){
		global $CGDb;

		order_appearances();
		return $CGDb->where('ishuman', $EQG)->where('id',0,'!=')->get('appearances', $limit, $cols);
	}

	// Returns the markup for an array of pony datase rows \\
	function render_ponies_html($Ponies, $wrap = true){
		global $CGDb, $_MSG, $color;

		$HTML = $wrap ? '<ul id="list" class="appearance-list">' : '';
		if (!empty($Ponies)) foreach ($Ponies as $p){
			$p['label'] = htmlspecialchars($p['label']);

			$img = get_sprite_html($p);
			$updates = get_update_html($p['id']);
			$notes = get_notes_html($p);
			$tags = get_tags_html($p['id']);
			$colors = get_colors_html($p['id']);

			$RenderPath = APPATH."img/cg_render/{$p['id']}.png";
			$FileModTime = '?t='.(file_exists($RenderPath) ? filemtime($RenderPath) : time());
			$Actions = "<a class='darkblue btn typcn typcn-image' title='View as PNG' href='/{$color}guide/appearance/{$p['id']}.png$FileModTime' target='_blank'></a>";
			if (PERM('inspector'))
				$Actions .= "<button class='edit typcn typcn-pencil blue' title='Edit'></button>".
				            ($p['id']!==0?"<button class='delete typcn typcn-trash red' title='Delete'></button>":'');

			$HTML .= "<li id='p{$p['id']}'>$img<div><strong><a href='/colorguide/appearance/{$p['id']}'>{$p['label']}</a>$Actions</strong>$updates$notes$tags$colors</div></li>";
		}
		else {
			if (empty($_MSG))
				$_MSG = "No appearances to show";
			$HTML .= "<div class='notice info align-center'><label>$_MSG</label></div>";
		}

		return $HTML.($wrap?'</ul>':'');
	}

	$GroupTagIDs_Assoc = array(
		6  => 'Mane Six & Spike',
		45 => 'Cutie Mark Crusaders',
		59 => 'Royalty',
		9  => 'Antagonists',
		44 => 'Foals',
		78 => 'Original Characters',
		1  => 'Unicorns',
		3  => 'Pegasi',
		2  => 'Earth Ponies',
		10 => 'Pets',
		// add other tags here
		64 => 'Objects',
		-1 => 'Other',
	);

	// Renders HTML for full guide list
	function render_full_list_html($Appearances, $GuideOrder, $wrap = WRAP){
		$elementName = $GuideOrder ? 'ul' : 'div';
		$HTML = $wrap ? "<$elementName id='full-list'>" : '';
		if (!empty($Appearances)){
			global $color;
			if (!$GuideOrder){
				$PrevFirstLetter = '';
				foreach ($Appearances as $p){
					$FirstLetter = strtoupper($p['label'][0]);
					if ($FirstLetter !== $PrevFirstLetter){
						if ($PrevFirstLetter !== ''){
							$HTML = rtrim($HTML, ', ')."</div></section>";
						}
						$PrevFirstLetter = $FirstLetter;
						$HTML .= "<section><h2>$PrevFirstLetter</h2><div>";
					}
					$HTML .= "<a href='/{$color}guide/appearance/{$p['id']}'>{$p['label']}</a>, ";
				}
				$HTML = rtrim($HTML, ', ');
			}
			else {
				global $GroupTagIDs_Assoc;
				$Sorted = sort_appearances($Appearances);
				foreach ($GroupTagIDs_Assoc as $Category => $CategoryName){
					if (empty($Sorted[$Category]))
						continue;

					$HTML .= "<section><h2>$CategoryName</h2><div>";
					foreach ($Sorted[$Category] as $p)
						$HTML .= "<a href='/{$color}guide/appearance/{$p['id']}'>{$p['label']}</a>, ";
					$HTML = rtrim($HTML, ', ')."</div></section>";
				}
			}
		}
		return $HTML.($wrap?"</$elementName>":'');
	}

	define('SIMPLE_ARRAY', true);
	function sort_appearances($Appearances, $simpleArray = false){
		global $CGDb, $GroupTagIDs_Assoc;
		$GroupTagIDs = array_keys($GroupTagIDs_Assoc);
		$Sorted = array();
		foreach($Appearances as $p){
			$Tagged = $CGDb->rawQuery(
				'SELECT tags.tid
				FROM tagged
				LEFT JOIN tags ON tagged.tid = tags.tid && tagged.ponyid = ?
				WHERE tags.tid IN ('.implode(',',$GroupTagIDs).')', array($p['id']));
			if (!empty($Tagged)){
				if (count($Tagged) > 1)
					usort($Tagged,function($a,$b) use ($GroupTagIDs){
						return array_search($a['tid'], $GroupTagIDs) - array_search($b['tid'], $GroupTagIDs);
					});
				$tid = $Tagged[0]['tid'];
			}
			else $tid = -1;
			$Sorted[$tid][] = $p;
		}
		if ($simpleArray){
			$idArray = array();
			foreach ($GroupTagIDs_Assoc as $Category => $CategoryName){
				if (empty($Sorted[$Category]))
					continue;
				foreach ($Sorted[$Category] as $p)
					$idArray[] = $p['id'];
			}
			return $idArray;
		}
		else return $Sorted;
	}

	function reorder_appearances($ids){
		global $CGDb;
		if (empty($ids))
			return false;

		$list = is_string($ids) ? explode(',', $ids) : $ids;
		$order = 1;
		foreach ($list as $id){
			if (!$CGDb->where('id', $id)->update('appearances', array('order' => $order++)))
				respond("Updating appearance #$id failed, process halted");
		}
	}

	function get_sort_reorder_appearances($EQG){
		if ($EQG)
			return;
		reorder_appearances(sort_appearances(get_appearances($EQG,null,'id'), SIMPLE_ARRAY));
	}

	// Check image type
	function check_image_type($tmp, $allowedMimeTypes){
		$imageSize = getimagesize($tmp);
		if (is_array($allowedMimeTypes) && !in_array($imageSize['mime'], $allowedMimeTypes))
			respond("This type of image is now allowed: ".$imageSize['mime']);
		list($width,$height) = $imageSize;

		if ($width + $height === 0) respond('The uploaded file is not an image');

		return array($width, $height);
	}

	// Check image size
	function check_image_size($path, $width, $height, $minwidth, $minheight){
		if ($width < $minwidth || $height < $minheight){
			unlink($path);
			respond('The image is too small in '.(
				$width < $minwidth
				?(
					$height < $minheight
					?'width and height'
					:'width'
				)
				:(
					$height < $minheight
					?'height'
					:''
				)
			).", please uploadd a bigger image.<br>The minimum size is {$minwidth}px by {$minheight}px.</p>");
		}
	}

	/**
	 * Function to process uploaded images
	 *
	 * Checks the $_FILES array for an item named $key,
	 *  checks if file is an image, and it's mime type
	 *  can be found in $allowedMimeTypes, and finally
	 *  checks if the size is at least $minwidth by $minheight,
	 *  then moves it to the requested $path.
	 *
	 * @param string $key
	 * @param string $path
	 * @param array|null $allowedMimeTypes
	 * @param int $minwidth
	 * @param int|null $minheight
	 *
	 * @return null
	 */
	function process_uploaded_image($key,$path,$allowedMimeTypes,$minwidth,$minheight = null){
		if (!isset($minheight)) $minheight = $minwidth;
		if (!isset($_FILES[$key]))
			return get_offsite_image($path,$allowedMimeTypes,$minwidth,$minheight);
		$file = $_FILES[$key];
		$tmp = $file['tmp_name'];
		if (strlen($tmp) < 1) respond('File upload failed; Reason unknown');

		list($width, $height) = check_image_type($tmp, $allowedMimeTypes);
		upload_folder_create($path);

		if (!move_uploaded_file($tmp, $path)){
			@unlink($tmp);
			respond('File upload failed; Writing image file was unsuccessful');
		}

		check_image_size($path, $width, $height, $minwidth, $minheight);
	}

	/**
	 * Gets the uploaded image for process_uploaded_image
	 *
	 * @param string $path
	 * @param array|null $allowedMimeTypes
	 * @param int $minwidth
	 * @param int $minheight
	 *
	 * @return null
	 */
	function get_offsite_image($path,$allowedMimeTypes,$minwidth,$minheight){
		if (empty($_POST['image_url']))
			respond("Please provide an image URL");

		require 'includes/Image.php';
		try {
			$Image = new Image($_POST['image_url']);
		}
		catch (Exception $e){ respond($e->getMessage()); }

		if ($Image->fullsize === false)
			respond('Image could not be retrieved from external provider');

		$remoteFile = @file_get_contents($Image->fullsize);
		if (empty($remoteFile))
			respond('Remote file could not be found');
		if (!file_put_contents($path, $remoteFile))
			respond('Writing local image file was unsuccessful');

		list($width, $height) = check_image_type($path, $allowedMimeTypes);
		check_image_size($path, $width, $height, $minwidth, $minheight);
	}

	// Checks and shortens episode tags
	function ep_tag_name_check($tag){
		global $EPISODE_ID_REGEX;

		$_match = array();
		if (regex_match($EPISODE_ID_REGEX,$tag,$_match)){
			$season = intval($_match[1], 10);
			if ($season == 0)
				return false;
			return 's'.intval($_match[1], 10).'e'.intval($_match[2], 10).(!empty($_match[3]) ? '-'.intval($_match[3], 10) : '');
		}
		else return false;
	}

	// Generates the markup for the tags sub-page
	function get_taglist_html($Tags, $wrap = true){
		global $TAG_TYPES_ASSOC, $CGDb;
		$HTML = $wrap ? '<tbody>' : '';

		$canEdit = PERM('inspector');

		$utils =
		$refresh = '';
		if ($canEdit){
			$refresh = " <button class='typcn typcn-arrow-sync refresh' title='Refresh use count'></button>";
			$utils = "<td class='utils align-center'><button class='typcn typcn-minus delete' title='Delete'></button> ".
			         "<button class='typcn typcn-flow-merge merge' title='Merge'></button> <button class='typcn typcn-flow-children synon' title='Synonymize'></button></td>";
		}


		if (!empty($Tags)) foreach ($Tags as $t){
			$trClass = $t['type'] ? " class='typ-{$t['type']}'" : '';
			$type = $t['type'] ? $TAG_TYPES_ASSOC[$t['type']] : '';
			$search = apos_encode(str_replace(' ','+',$t['name']));
			$titleName = apos_encode($t['name']);

			if ($canEdit && !empty($t['synonym_of'])){
				$Syn = get_tag_synon($t,'name');
				$t['title'] .= "<br><em>Synonym of <strong>{$Syn['name']}</strong></em>";
			}

			$HTML .= <<<HTML
			<tr$trClass>
				<td class="tid">{$t['tid']}</td>
				<td class="name"><a href='/colorguide/?q=$search' title='Search for $titleName'><span class="typcn typcn-zoom"></span>{$t['name']}</a></td>$utils
				<td class="title">{$t['title']}</td>
				<td class="type">$type</td>
				<td class="uses"><span>{$t['uses']}</span>$refresh</td>
			</tr>
HTML;
		}

		if ($wrap) $HTML .= '</tbody>';
		return $HTML;
	}

	/**
	 * Apply pre-defined template to an appearance
	 * $EQG controls whether to apply EQG or Pony template
	 *
	 * @param int $PonyID
	 * @param bool $EQG
	 *
	 * @return null
	 */
	function apply_template($PonyID, $EQG){
		global $CGDb, $Color;

		if (empty($PonyID) || !is_numeric($PonyID))
			throw new Exception('Incorrect value for $PonyID while applying template');

		if ($CGDb->where('ponyid', $PonyID)->has('colorgroups'))
			throw new Exception('Template can only be applied to empty appearances');

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
					"Fill 1",
					"Fill 2",
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
				throw new Exception(rtrim("Color group \"$GroupName\" could not be created: ".$CGDb->getLastError()), ': ');

			foreach ($ColorNames as $label){
				if (!$CGDb->insert('colors',array(
					'groupid' => $GroupID,
					'label' => $label,
					'order' => $ci++,
				))) throw new Exception(rtrim("Color \"$label\" could not be added: ".$CGDb->getLastError()), ': ');
			}
		}
	}

	// Gets the list of updates for an appearance
	define('MOST_RECENT', 1);
	function get_updates($PonyID, $count = null){
		global $Database;

		if (!empty($count)){
			if (strpos($count, ',') !== false){
				$count = explode(',', $count);
				$LIMIT = "LIMIT {$count[1]} OFFSET {$count[0]}";
			}
			else $LIMIT = "LIMIT $count";
		}
		else $LIMIT = '';
		$WHERE = isset($PonyID) ? "WHERE cm.ponyid = $PonyID" :'';
		$query = $Database->rawQuery(
			"SELECT cm.*, l.initiator, l.timestamp
			FROM log__color_modify cm
			LEFT JOIN log l ON cm.entryid = l.refid && l.reftype = 'color_modify'
			{$WHERE}
			ORDER BY l.timestamp DESC
			{$LIMIT}");

		return ($count === MOST_RECENT && isset($query[0])) ? $query[0] : $query;
	}

	// Renders HTML of the list of changes
	define('SHOW_APPEARANCE_NAMES', true);
	function render_changes_html($Changes, $wrap = true, $showAppearance = false){
		$seeInitiator = PERM('inspector');
		$PonyCache = array();
		$HTML = $wrap ? '<ul id="changes">' : '';
		foreach ($Changes as $c){
			$initiator = $appearance = '';
			if ($seeInitiator)
				$initiator = " by ".profile_link(get_user($c['initiator']));
			if ($showAppearance){
				global $CGDb, $color;

				$PonyID = $c['ponyid'];
				if (empty($PonyCache[$PonyID])){
					$PonyCache[$PonyID] = $CGDb->where('id', $PonyID)->getOne('appearances');
				}
				$Pony = $PonyCache[$PonyID];
				$appearance = "<a href='/{$color}guide/appearance/{$Pony['id']}'>{$Pony['label']}</a>: ";
			}
			$HTML .= "<li>$appearance{$c['reason']} - ".timetag($c['timestamp'])."$initiator</li>";
		}
		return $HTML . ($wrap ? '</ul>' : '');
	}

	// Update use count on a tag
	function update_tag_count($TagID, $returnCount = false){
		global $CGDb;

		$Tagged = $CGDb->where('tid', $TagID)->count('tagged');
		$return = array('status' => $CGDb->where('tid', $TagID)->update('tags',array('uses' => $Tagged)));
		if ($returnCount) $return['count'] = $Tagged;
		return $return;
	}

	// Preseving alpha
	function preserveAlpha($img, &$background = null) {
		$background = imagecolorallocatealpha($img, 0, 0, 0, 127);
		imagecolortransparent($img, $background);
		imagealphablending($img, false);
		imagesavealpha($img, true);
		return $img;
	}

	// Transparent Image creator
	function imageCreateTransparent($width, $height = null) {
		if (!isset($height))
			$height = $width;

		$png = preserveAlpha(imagecreatetruecolor($width, $height), $transparency);
		imagefill($png, 0, 0, $transparency);
		return $png;
	}

	// White Image creator
	function imageCreateWhiteBG($width, $height = null) {
		if (!isset($height))
			$height = $width;
		$png = imagecreatetruecolor($width, $height);

		$white = imagecolorallocate($png, 255, 255, 255);
		imagefill($png, 0, 0, $white);
		return $png;
	}

	// Draw Rectangle on image
	function imageDrawRectangle($image, $x, $y, $size, $fill, $outline){
		if (!empty($fill))
			$fill = imagecolorallocate($image, ...hex2rgb($fill));
		if (is_string($outline))
			$outline = imagecolorallocate($image, ...hex2rgb($outline));

		if (is_array($size)){
			$x2 = $x + $size[0];
			$y2 = $y + $size[1];
		}
		else {
			$x2 = $x + $size;
			$y2 = $y + $size;
		}

		$x2--; $y2--;

		if (!empty($fill))
			imagefilledrectangle($image, $x, $y, $x2, $y2, $fill);
		imagerectangle($image, $x, $y, $x2, $y2, $outline);
	}

	// Writes on an image
	function imageWrite($image, $text, $x, $fontsize, $fontcolor, &$origin, $FontFile){
		if (is_string($fontcolor))
			$fontcolor = imagecolorallocate($image, 0, 0, 0);

		$box = imagettfsanebbox($fontsize, $FontFile, $text);
		$origin['y'] += $box['height'];

		imagettftext($image, $fontsize, 0, $x, $origin['y'], $fontcolor, $FontFile, $text);
	}

	// imagettfbbox wrapper with sane output
	function imagettfsanebbox($fontsize, $fontfile, $text){
		/*
		    imagettfbbox returns (x,y):
		    6,7--4,5
		     |    |
		     |    |
		    0,1--2,3
		*/
		$box = imagettfbbox($fontsize, 0, $fontfile, $text);

		$return =  array(
			'bottom left' => array('x' => $box[0], 'y' => $box[1]),
			'bottom right' => array('x' => $box[2], 'y' => $box[3]),
			'top right' => array('x' => $box[4], 'y' => $box[5]),
			'top left' => array('x' => $box[6], 'y' => $box[7]),
		);
		$return['width'] = max(
			$return['top right']['x'] - $return['top left']['x'],
			$return['bottom right']['x'] - $return['bottom left']['x']
		);
		$return['height'] = max(
			$return['bottom left']['y'] - $return['top left']['y'],
			$return['bottom right']['y'] - $return['top right']['y']
		);

		return $return;
	}

	// Output png file to browser
	function outputpng($resource, $path, $FileRelPath){
		outputimage($resource, $path, $FileRelPath, function($fp,$fd){ imagepng($fd, $fp); }, 'png');
	}

	// Output svg file to browser
	function outputsvg($svgdata, $path, $FileRelPath){
		outputimage($svgdata, $path, $FileRelPath, function($fp,$fd){ file_put_contents($fp, $fd); }, 'svg+xml');
	}

	/**
	 * @param resource|string $data
	 * @param string $path
	 * @param string $relpath
	 * @param callable $write_callback
	 * @param string $content_type
	 */
	function outputimage($data, $path, $relpath, $write_callback, $content_type){
		if (isset($data))
			$write_callback($path, $data);

		fix_path("$relpath?t=".filemtime($path));
		header("Content-Type: image/$content_type");
		readfile($path);
		exit;
	}

	// Constants for getimagesize() return array keys
	define('WIDTH', 0);
	define('HEIGHT', 1);

	define('CM_DIR_ONLY',true);
	function clear_rendered_image($AppearanceID, $cm_dir_only = false){
		$RenderedPath = APPATH."img/cg_render/$AppearanceID";
		if ($cm_dir_only !== CM_DIR_ONLY && file_exists("$RenderedPath.png"))
			unlink("$RenderedPath.png");
		if (file_exists("$RenderedPath.svg"))
			unlink("$RenderedPath.svg");
	}

	function imageCopyExact($dest, $source, $x, $y, $w, $h){
		imagecopyresampled($dest, $source, $x, $y, $x, $y, $w, $h, $w, $h);
	}

	function get_episode_appearances($AppearanceID, $wrap = true){
		global $CGDb;

		$EpTagsOnAppearance = $CGDb->rawQuery(
				"SELECT t.tid
		FROM tagged tt
		LEFT JOIN tags t ON tt.tid = t.tid
		WHERE tt.ponyid = ? &&  t.type = ?",array($AppearanceID, 'ep'));
		if (!empty($EpTagsOnAppearance)){
			foreach ($EpTagsOnAppearance as $k => $row)
				$EpTagsOnAppearance[$k] = $row['tid'];

			$EpAppearances = $CGDb->rawQuery("SELECT DISTINCT t.name FROM tags t WHERE t.tid IN (".implode(',',$EpTagsOnAppearance).")");
			if (empty($EpAppearances))
				return '';

			$List = '';
			foreach ($EpAppearances as $tag){
				$name = strtoupper($tag['name']);
				$EpData = episode_id_parse($name);
				$Ep = get_real_episode($EpData['season'], $EpData['episode']);
				$List .= (
					empty($Ep)
					? $name
					: "<a href='/episode/S{$Ep['season']}E{$Ep['episode']}'>".format_episode_title($Ep).'</a>'
				).', ';
			}
			$List = rtrim($List, ', ');
			$N_episodes = plur('episode',count($EpAppearances),PREPEND_NUMBER);
			$hide = '';
		}
		else {
			$N_episodes = 'no episodes';
			$List = '';
			$hide = 'style="display:none"';
		}

		if (!$wrap)
			return $List;
		return <<<HTML
		<section id="ep-appearances" $hide>
			<h2><span class='typcn typcn-video'></span>Appears in $N_episodes</h2>
			<p>$List</p>
		</section>
HTML;
	}

	// Generate CM preview image
	function render_cm_direction_svg($AppearanceID, $dir){
		global $CGDb, $CGPath;

		$OutputPath = APPATH."img/cg_render/$AppearanceID.svg";
		$FileRelPath = "$CGPath/appearance/$AppearanceID.svg";
		if (file_exists($OutputPath))
			outputsvg(null,$OutputPath,$FileRelPath);

		if (is_null($dir))
			do404();

		$DefaultColorMapping = array(
			'Coat Outline' => '#0D0D0D',
			'Coat Shadow Outline' => '#000000',
			'Coat Fill' => '#2B2B2B',
			'Coat Shadow Fill' => '#171717',
			'Mane & Tail Outline' => '#333333',
			'Mane & Tail Fill' => '#5E5E5E',
		);
		$Colors = $CGDb->rawQuery(
			"SELECT cg.label as cglabel, c.label as label, c.hex
			FROM colorgroups cg
			LEFT JOIN colors c on c.groupid = cg.groupid
			WHERE cg.ponyid = ?
			ORDER BY cg.label ASC, c.label ASC", array($AppearanceID));

		$ColorMapping = array();
		foreach ($Colors as $row){
			$label = $row['cglabel'].' '.regex_replace(new RegExp('(\s\d+)?(/.*)?$'),'', $row['label']);
			if (isset($DefaultColorMapping[$label]) && !isset($ColorMapping[$label]))
				$ColorMapping[$label] = $row['hex'];
		}
		if (!isset($ColorMapping['Coat Shadow Outline']) && isset($ColorMapping['Coat Outline']))
			$ColorMapping['Coat Shadow Outline'] = $ColorMapping['Coat Outline'];

		$img = file_get_contents(APPATH.'img/cm-direction-'.($dir===CM_DIR_HEAD_TO_TAIL?'ht':'th').'.svg');
		foreach ($DefaultColorMapping as $label => $defhex){
			if (isset($ColorMapping[$label]))
				$img = str_replace($defhex, $ColorMapping[$label], $img);
		}

		outputsvg($img,$OutputPath,$FileRelPath);
	}

	// Retruns CM preview image link
	function get_cm_preview_url($Appearance){
		if (!empty($Appearance['cm_preview']))
			$preview = $Appearance['cm_preview'];
		else {
			$CM = da_cache_deviation($Appearance['cm_favme']);
			$preview = $CM['preview'];
		}
		return $preview;
	}

	// Render appearance PNG image
	function render_appearance_png($Appearance){
		global $CGPath;

		$OutputPath = APPATH."img/cg_render/{$Appearance['id']}.png";
		$FileRelPath = "$CGPath/appearance/{$Appearance['id']}.png";
		//if (file_exists($OutputPath))
		//	outputpng(null,$OutputPath,$FileRelPath);

		$SpriteRelPath = "img/cg/{$Appearance['id']}.png";

		$OutWidth = 0;
		$OutHeight = 0;
		$SpriteWidth = $SpriteHeight = 0;
		$SpriteRightMargin = 10;
		$ColorSquareSize = 25;
		$FontFile = APPATH.'font/Celestia Medium Redux.ttf';
		if (!file_exists($FontFile))
			trigger_error('Font file missing', E_USER_ERROR);
		$Name = $Appearance['label'];
		$NameVerticalMargin = 5;
		$NameFontSize = 22;
		$TextMargin = 10;

		// Detect if sprite exists and adjust image size & define starting positions
		$SpritePath = APPATH.$SpriteRelPath;
		$SpriteExists = file_exists($SpritePath);
		if ($SpriteExists){
			$SpriteSize = getimagesize($SpritePath);
			$Sprite = preserveAlpha(imagecreatefrompng($SpritePath));
			$SpriteHeight = $SpriteSize[HEIGHT];
			$SpriteWidth = $SpriteSize[WIDTH];
			$SpriteRealWidth = $SpriteWidth + $SpriteRightMargin;

			$OutWidth += $SpriteRealWidth;
			if ($SpriteHeight > $OutHeight)
				$OutHeight = $SpriteHeight;
		}
		else $SpriteRealWidth = 0;
		$origin = array(
			'x' => $SpriteExists ? $SpriteRealWidth : $TextMargin,
			'y' => 0,
		);

		// Get color groups & calculate the space they take up
		$ColorGroups = get_cgs($Appearance['id']);
		$CGCount = count($ColorGroups);
		$CGFontSize = $NameFontSize/1.5;
		$CGVerticalMargin = $NameVerticalMargin*1.5;
		$GroupLabelBox = imagettfsanebbox($CGFontSize, $FontFile, 'AGIJKFagijkf');
		$CGsHeight = $CGCount*($GroupLabelBox['height'] + ($CGVerticalMargin*2) + $ColorSquareSize);

		// Get export time & size
		$ExportTS = "Image last updated: ".format_timestamp(time(), FORMAT_FULL);
		$ExportFontSize = $CGFontSize/1.5;
		$ExportBox = imagettfsanebbox($ExportFontSize, $FontFile, $ExportTS);

		// Check how long & tall appearance name is, and set image width
		$NameBox = imagettfsanebbox($NameFontSize, $FontFile, $Name);
		$OutWidth = $origin['x'] + max($NameBox['width'], $ExportBox['width']) + $TextMargin;

		// Set image height
		$OutHeight = $origin['y'] + (($NameVerticalMargin*3) + $NameBox['height'] + $ExportBox['height']) + $CGsHeight;

		// Create base image
		$BaseImage = imageCreateTransparent($OutWidth, $OutHeight);
		$BLACK = imagecolorallocate($BaseImage, 0, 0, 0);

		// If sprite exists, output it on base image
		if ($SpriteExists)
			imageCopyExact($BaseImage, $Sprite, 0, 0, $SpriteWidth, $SpriteHeight);

		// Output appearance name
		$origin['y'] += $NameVerticalMargin;
		imageWrite($BaseImage, $Name, $origin['x'], $NameFontSize, $BLACK, $origin, $FontFile);
		$origin['y'] += $NameVerticalMargin;

		// Output generation time
		imageWrite($BaseImage, $ExportTS, $origin['x'], $ExportFontSize, $BLACK, $origin, $FontFile);
		$origin['y'] += $NameVerticalMargin;

		if (!empty($ColorGroups))
			foreach ($ColorGroups as $cg){
				imageWrite($BaseImage, $cg['label'], $origin['x'], $CGFontSize , $BLACK, $origin, $FontFile);
				$origin['y'] += $CGVerticalMargin;

				$Colors = get_colors($cg['groupid']);
				if (!empty($Colors)){
					$part = 0;
					foreach ($Colors as $c){
						$add = $part === 0 ? 0 : $part*5;
						$x = $origin['x']+($part*$ColorSquareSize)+$add;
						if ($x+$ColorSquareSize > $OutWidth){
							$part = 0;
							$SizeIncrease = $ColorSquareSize + $CGVerticalMargin;
							$origin['y'] += $SizeIncrease;
							$x = $origin['x'];

							// Create new base image since height will increase, and copy contents of old one
							$NewBaseImage = imageCreateTransparent($OutWidth, $OutHeight + $SizeIncrease);
							imageCopyExact($NewBaseImage, $BaseImage, 0, 0, $OutWidth, $OutHeight);
							imagedestroy($BaseImage);
							$BaseImage = $NewBaseImage;
							$OutHeight += $SizeIncrease;
						}

						imageDrawRectangle($BaseImage, $x, $origin['y'], $ColorSquareSize, $c['hex'], $BLACK);
						$part++;
					}

					$origin['y'] += $ColorSquareSize + $CGVerticalMargin;
				}
			};

		$sizeArr = array($OutWidth, $OutHeight);
		$FinalBase = imageCreateWhiteBG(...$sizeArr);
		imageDrawRectangle($FinalBase, 0, 0, $sizeArr, null, $BLACK);
		imageCopyExact($FinalBase, $BaseImage, 0, 0, ...$sizeArr);

		if (!upload_folder_create($OutputPath))
			respond('Failed to create render directory');
		outputpng($FinalBase, $OutputPath, $FileRelPath);
	}

	function get_actual_tag($value, $column = 'tid', $bool = false, $returnCols = null){
		global $CGDb;

		$arg1 = $bool === RETURN_AS_BOOL
			? array('tags', 'synonym_of,tid')
			: array('tags', !empty($returnCols)?"synonym_of,$returnCols":'*');

		$Tag = $CGDb->where($column, $value)->getOne(...$arg1);

		if (!empty($Tag['synonym_of'])){
			$arg2 = $bool === RETURN_AS_BOOL ? 'tid' : $arg1[1];
			$Tag = get_tag_synon($Tag, $arg2);
		}

		return $bool === RETURN_AS_BOOL ? !empty($Tag) : $Tag;
	}

	function get_tag_synon($Tag, $returnCols = null){
		global $CGDb;

		if (empty($Tag['synonym_of']))
			return null;

		return $CGDb->where('tid', $Tag['synonym_of'])->getOne('tags',$returnCols);
	}
