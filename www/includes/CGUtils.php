<?php
	##########################################################
	## Utility functions related to the color guide feature ##
	##########################################################

	// Response creator for typeahead.js
	function typeahead_results($str){
		header('Content-Type: application/json');
		if (is_array($str)) $str = json_encode($str);
		die($str);
	}

	// Some patterns for validation
	define('TAG_NAME_PATTERN', '^[a-z\d ().-]{4,30}$');
	define('INVERSE_TAG_NAME_PATTERN', '[^a-z\d ().-]');
	define('HEX_COLOR_PATTERN','/^#?([A-Fa-f0-9]{6})$/u');

	// Get the colors in a given color groups
	function get_colors($GroupID){
		global $CGDb;

		return $CGDb->rawQuery('SELECT * FROM colors WHERE groupid = ? ORDER BY `order`, colorid', array($GroupID));
	}

	// Return the markup for the specified color group
	define('NO_COLON', false);
	function get_cg_html($GroupID, $wrap = true, $colon = true){
		global $CGDb;

		if (is_array($GroupID)) $Group = $GroupID;
		else $Group = $CGDb->where('groupid',$GroupID)->getOne('colorgroups');

		$label = htmlspecialchars($Group['label']);
		$HTML = $wrap ? "<li id='cg{$Group['groupid']}'>" : '';
		$HTML .= "<span class='cat'>$label".($colon?':':'')." </span>";
		$Colors = get_colors($Group['groupid']);
		if (!empty($Colors))
			foreach ($Colors as $i => $c){
				$title = apos_encode($c['label']);
				$color = '';
				if (!empty($c['hex'])){
					$color = $c['hex'];
					$title .= "' style='background-color:$color";
				}

				$HTML .= "<span id='c{$c['colorid']}' title='$title'>$color</span>";
			};

		if ($wrap) $HTML .= "</li>";

		return $HTML;
	}

	// Get color groups
	function get_cgs($PonyID, $cols = '*'){
		global $CGDb;

		return $CGDb
			->where('ponyid',$PonyID)
			->orderBy('`order`','ASC')
			->orderBy('groupid','ASC')
			->get('colorgroups',null,$cols);
	}

	// Returns the markup of the color list for a specific pony \\
	function get_colors_html($PonyID, $wrap = true){
		global $CGDb;

		$ColorGroups = get_cgs($PonyID);

		$HTML = $wrap ? "<ul class='colors'>" : '';
		if (!empty($ColorGroups)){
			foreach ($ColorGroups as $cg)
				$HTML .= get_cg_html($cg);
		}
		if ($wrap) $HTML .= "</ul>";
		return $HTML;
	}

	function get_tags($PonyID = null, $limit = null){
		global $CGDb;

		$CGDb
			->orderByLiteral('CASE WHEN tags.type IS NULL THEN 1 ELSE 0 END')
			->orderBy('CONCAT(tags.type)', 'ASC')
			->orderBy('tags.name', 'ASC');
		return !empty($PonyID)
			? $CGDb
				->join('tags','tagged.tid = tags.tid','LEFT')
				->where('tagged.ponyid',$PonyID)
				->get('tagged',$limit,'tags.*')
			: $CGDb->get('tags',$limit);
	}

	// Return the markup of a set of tags belonging to a specific pony \\
	function get_tags_html($PonyID, $wrap = true){
		global $CGDb;

		$Tags = get_tags($PonyID);

		$HTML = $wrap ? "<div class='tags'>" : '';
		if (PERM('inspector'))
			$HTML .= "<input type='text' class='addtag tag' placeholder='Enter tag' pattern='".TAG_NAME_PATTERN."' maxlength='30' required>";
		if (!empty($Tags)) foreach ($Tags as $i => $t){
			$class = " class='tag id-{$t['tid']}".(!empty($t['type'])?' typ-'.$t['type']:'')."'";
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
	function get_notes_html($p, $wrap = true){
		if (!empty($p['notes'])){
			$notes = '<span>'.htmlspecialchars($p['notes']).'</span>';
			if (!empty($p['cm_favme']))
				$notes .= "<a href='http://fav.me/{$p['cm_favme']}'>Cutie mark vector</a>";
		}
		else {
			if (!PERM('inspector')) return '';
			$notes = '';
		}
		return $wrap ? "<div class='notes'>$notes</div>" : $notes;
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

	function order_ponies(){
		global $CGDb;

		$CGDb
			->orderByLiteral('CASE WHEN `order` IS NULL THEN 1 ELSE 0 END')
			->orderBy('`order`', 'ASC')
			->orderBy('id', 'ASC');
	}

	// Get list of ponies
	function get_ponies($limit = null){
		global $CGDb;

		order_ponies();
		return $CGDb->get('ponies',$limit);
	}

	// Returns the markup for an array of pony datase rows \\
	function render_ponies_html($Ponies, $wrap = true){
		global $CGDb, $_MSG;

		$HTML = $wrap ? '<ul id="list">' : '';
		if (!empty($Ponies)) foreach ($Ponies as $p){
			$p['label'] = htmlspecialchars($p['label']);
			$imgPth = "img/cg/{$p['id']}.png";
			if (!file_Exists(APPATH.$imgPth)) $imgPth = "img/blank-pixel.png";
			else $imgPth = $imgPth.'?'.filemtime(APPATH.$imgPth);
			$img = "<a href='/$imgPth' target='_blank' title='Open image in new tab'><img src='/$imgPth' alt='".apos_encode($p['label'])."'></a>";
			if (PERM('inspector')) $img = "<div class='upload-wrap'>$img</div>";
			$img = "<div>$img</div>";

			$updates = get_update_html($p['id']);
			$notes = get_notes_html($p);
			$tags = get_tags_html($p['id']);
			$colors = get_colors_html($p['id']);
			$editBtn = PERM('inspector') ? '<button class="edit typcn typcn-pencil blue" title="Edit"></button><button class="delete typcn typcn-trash red" title="Delete"></button>' : '';

			$HTML .= "<li id='p{$p['id']}'>$img<div><strong><a href='/colorguide/appearance/{$p['id']}'>{$p['label']}</a>$editBtn</strong>$updates$notes$tags$colors</div></li>";
		}
		else {
			if (empty($_MSG))
				$_MSG = "No appearances to show";
			$HTML .= "<div class='notice fail'><label>Search error</label><p>$_MSG</p></div>";
		}

		return $HTML.($wrap?'</ul>':'');
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

	// Create upload destination folder
	function upload_folder_create($path){
		$DS = preg_quote(DIRECTORY_SEPARATOR);
		$folder = preg_replace("~^(.*$DS)[^$DS]+$~",'$1',$path);
		if (!is_dir($folder)) mkdir($folder,0777,true);
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
		$_match = array();
		if (preg_match('/^'.EPISODE_ID_PATTERN.'/i',$tag,$_match)){
			$season = intval($_match[1], 10);
			if ($season == 0)
				return false;
			return 's'.intval($_match[1], 10).'e'.intval($_match[2], 10).(!empty($_match[3]) ? intval($_match[3], 10) : '');
		}
		else return false;
	}

	// Generates the markup for the tags sub-page
	function get_taglist_html($Tags, $wrap = true){
		global $TAG_TYPES_ASSOC;
		$HTML = $wrap ? '<tbody>' : '';

		$utils = PERM('inspector') ? "<td class='utils'><button class='typcn typcn-minus delete' title='Delete'></button> <button class='typcn typcn-flow-merge merge' title='Merge'></button></td>" : '';
		$refresh = PERM('inspector') ? " <button class='typcn typcn-arrow-sync refresh' title='Refresh use count'></button>" : '';

		if (!empty($Tags)) foreach ($Tags as $t){
			$trClass = $t['type'] ? " class='typ-{$t['type']}'" : '';
			$type = $t['type'] ? $TAG_TYPES_ASSOC[$t['type']] : '';
			$HTML .= <<<HTML
			<tr$trClass>
				<td class="tid">{$t['tid']}</td>
				<td class="name">{$t['name']}</td>$utils
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
	 * Apply pre/defined template to an appearance
	 *
	 * @param int $PonyID
	 *
	 * @return null
	 */
	function apply_template($PonyID){
		global $CGDb, $Color;

		if ($CGDb->where('ponyid', $PonyID)->has('colorgroups'))
			throw new Exception('Template can only be applied to empty appearances');

		$Scheme = array(
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
				"$Color 1",
				"$Color 2",
			),
		);

		$cgi = $ci = 0;
		foreach ($Scheme as $GroupName => $ColorNames){
			$GroupID = $CGDb->insert('colorgroups',array(
				'ponyid' => $PonyID,
				'label' => $GroupName,
				'order' => $cgi++,
			));
			if (!$GroupID)
				throw new Exception(rtrim("Color group \"$GroupName\" could not be created: ".$CGDb->getLastError()), ': ');

			foreach ($ColorNames as $label){
				if (!$CGDb->insert('colors',array(
					'groupid' => $GroupID,
					'label' => $label,
				))) throw new Exception(rtrim("Color \"$label\" could not be added: ".$CGDb->getLastError()), ': ');
			}
		}
	}

	// Gets the list of updates for an appearance
	define('MOST_RECENT', 1);
	function get_updates($PonyID, $count = null){
		global $Database;

		$LIMIT = isset($count) ? "LIMIT $count":'';
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
		$UserCache = array();
		$PonyCache = array();
		$HTML = $wrap ? '<ul id="changes">' : '';
		foreach ($Changes as $c){
			$initiator = $appearance = '';
			if ($seeInitiator){
				$UserID = $c['initiator'];
				if (empty($UserCache[$UserID])){
					$UserCache[$UserID] = get_user($UserID);
				}
				$User = $UserCache[$UserID];
				$initiator = " by <a href='/u/{$User['name']}'>{$User['name']}</a>";
			}
			if ($showAppearance){
				global $CGDb, $color;

				$PonyID = $c['ponyid'];
				if (empty($PonyCache[$PonyID])){
					$PonyCache[$PonyID] = $CGDb->where('id', $PonyID)->getOne('ponies');
				}
				$Pony = $PonyCache[$PonyID];
				$appearance = "<a href='/{$color}guide/appearance/{$Pony['id']}'>{$Pony['label']}</a>: ";
			}
			$HTML .= "<li>$appearance{$c['reason']} - ".timetag($c['timestamp'])."$initiator</li>";
		}
		return $HTML . ($wrap ? '</ul>' : '');
	}
