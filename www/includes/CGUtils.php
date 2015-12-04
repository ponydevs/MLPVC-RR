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

	// EQG Color Guide URL pattern
	define('EQG_URL_PATTERN','~^eqg/~');

	// Get the colors in a given color groups
	function get_colors($GroupID){
		global $CGDb;

		return $CGDb->rawQuery('SELECT * FROM colors WHERE groupid = ? ORDER BY "order", colorid', array($GroupID));
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

	function get_tags($PonyID = null, $limit = null, $is_editing = false){
		global $CGDb;

		$CGDb
			->orderByLiteral('CASE WHEN tags.type IS NULL THEN 1 ELSE 0 END')
			->orderBy('tags.type', 'ASC')
			->orderBy('tags.name', 'ASC');
		if (!$is_editing)
			$CGDb->where("tags.type != 'ep'");
		return isset($PonyID)
			? $CGDb
				->join('tags','tagged.tid = tags.tid','LEFT')
				->where('tagged.ponyid',$PonyID)
				->get('tagged',$limit,'tags.*')
			: $CGDb->get('tags',$limit);
	}

	// Return the markup of a set of tags belonging to a specific pony \\
	define('NO_INPUT', true);
	function get_tags_html($PonyID, $wrap = true, $no_input = false){
		global $CGDb;

		$Editing = !$no_input && PERM('inspector');
		$Tags = get_tags($PonyID, null, $Editing);

		$HTML = $wrap ? "<div class='tags'>" : '';
		if ($Editing && $PonyID !== 0)
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
		if (!empty($p['notes']) || !empty($p['cm_favme'])){
			$notes = '';
			if (!empty($p['notes'])){
				if ($p['id'] !== 0)
					$p['notes'] = htmlspecialchars($p['notes']);
				$notes = "<span>{$p['notes']}</span>";
			}
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

	function order_appearances($dir = 'ASC'){
		global $CGDb;

		$CGDb
			->orderByLiteral('CASE WHEN "order" IS NULL THEN 1 ELSE 0 END', $dir)
			->orderBy('"order"', $dir)
			->orderBy('id', $dir);
	}

	// Get list of ponies
	function get_appearances($EQG, $limit = null){
		global $CGDb;

		order_appearances();
		return $CGDb->where('ishuman', $EQG)->where('"id" != 0')->get('appearances',$limit);
	}

	// Returns the markup for an array of pony datase rows \\
	function render_ponies_html($Ponies, $wrap = true){
		global $CGDb, $_MSG, $color;

		$HTML = $wrap ? '<ul id="list" class="appearance-list">' : '';
		if (!empty($Ponies)) foreach ($Ponies as $p){
			$p['label'] = htmlspecialchars($p['label']);

			$img = '';
			$imgPth = "img/cg/{$p['id']}.png";
			$hasSprite = file_Exists(APPATH.$imgPth);
			if ($hasSprite){
				$imgPth = $imgPth.'?'.filemtime(APPATH.$imgPth);
				$img = "<a href='/$imgPth' target='_blank' title='Open image in new tab'><img src='/$imgPth' alt='".apos_encode($p['label'])."'></a>";
				if (PERM('inspector')) $img = "<div class='upload-wrap'>$img</div>";
				$img = "<div>$img</div>";
			}

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
			}
			else foreach($Appearances as $p)
				$HTML .= "<li><a href='/{$color}guide/appearance/{$p['id']}'>{$p['label']}</a></li>";
		}
		return $HTML.($wrap?"</$elementName>":'');
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
					"$Color 1",
					"$Color 2",
				),
			);

		$cgi = 0;
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
	function imageWrite($image, $text, $x, $fontsize, $fontcolor){
		if (is_string($fontcolor))
			$fontcolor = imagecolorallocate($image, 0, 0, 0);

		global $origin, $FontFile;
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
	function outputpng($resource, $path = null){
		header('Content-Type: image/png');

		if (!is_string($resource))
			imagepng($resource, $path);
		else {
			$path = $resource;
			if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])
				&& strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= filemtime($path)){
				header('HTTP/1.0 304 Not Modified');
				exit;
			}
		}

		global $FileRelPath;
		fix_path("$FileRelPath?t=".filemtime($path));
		readfile($path);
		exit;
	}

	// Constants for getimagesize() return array keys
	define('WIDTH', 0);
	define('HEIGHT', 1);

	function clear_rendered_image($AppearanceID){
		// Remove rendered sprite image to force its re-generation
		$RenderedPath = APPATH."img/cg_render/$AppearanceID.png";
		if (file_exists($RenderedPath))
			return unlink($RenderedPath);
		else return true;
	}

	function imageCopyExact($dest, $source, $x, $y, $w, $h){
		imagecopyresampled($dest, $source, $x, $y, $x, $y, $w, $h, $w, $h);
	}
