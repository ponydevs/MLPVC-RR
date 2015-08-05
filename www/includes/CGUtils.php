<?php
	##########################################################
	## Utility functions related to the color guide feature ##
	##########################################################

	// Constant to disable returning of wrapper element with markup generators
	define('NOWRAP', false);

	// Get the colors in a given color groups
	function get_colors($GroupID){
		global $CGDb;

		return $CGDb->where('groupid', $GroupID)->get('colors');
	}

	// Returns the markup of the color list for a specific pony \\
	function get_colors_html($PonyID, $wrap = true){
		global $CGDb;

		$ColorGroups = $CGDb->where('ponyid', $PonyID)->get('colorgroups');
		$HTML = '';
		if (!empty($ColorGroups)){
			$HTML = $wrap ? "<ul class=colors>" : '';
			foreach ($ColorGroups as $cg){
				$label = htmlspecialchars($cg['label']);
				$HTML .= "<li id=cg{$cg['groupid']}><span class=cat>$label: </span>";

				$Colors = get_colors($cg['groupid']);
				if (!empty($Colors))
					foreach ($Colors as $i => $c){
						$title = apos_encode($c['label']);
						$HTML .= "<span id=c{$c['colorid']} style=background-color:{$c['hex']} title='$title'>{$c['hex']}</span> ";
					};

				$HTML .= "</li>";
			}
			if ($wrap) $HTML .= "</ul>";
		}
		return $HTML;
	}

	// Return the markup of a set of tags belonging to a specific pony \\
	function get_tags_html($PonyID, $wrap = true){
		global $CGDb;

		$Tags = $CGDb->rawQuery(
			'SELECT cgt.*
			FROM tagged cgtg
			LEFT JOIN tags cgt ON cgtg.tid = cgt.tid
			WHERE cgtg.ponyid = ?
			ORDER BY cgt.type DESC, cgt.name',array($PonyID));
		$HTML = '';
		if (!empty($Tags)){
			$HTML = $wrap ? "<div class=tags>" : '';
			foreach ($Tags as $i => $t){
				$class = " class='tag id-{$t['tid']}".(!empty($t['type'])?' typ-'.$t['type']:'')."'";
				$title = !empty($t['title']) ? " title='".apos_encode($t['title'])."'" : '';
				$HTML .= "<span$class$title>{$t['name']}</span> ";
			}
			if ($wrap) $HTML .= "</div>";
		}
		return $HTML;
	}

	// List of available tag types
	$TAG_TYPES_ASSOC = array(
		'app' => 'Appearance',
		'cat' => 'Category',
		'ep' => 'Episode',
		'gen' => 'Gender',
		'spec' => 'Species',
	);
	$TAG_TYPES = array_keys($TAG_TYPES_ASSOC);
	define('TAG_NAME_PATTERN', '^[a-z\d ().-]{4,30}$');
	define('INVERSE_TAG_NAME_PATTERN', '[^a-z\d ().-]');

	// Returns the markup for the notes displayed under an appaerance
	function get_notes_html($p, $wrap = true){
		$notes = !empty($p['notes']) ? nl2br(htmlspecialchars($p['notes'])) : '';
		return $wrap ? "<div class='notes'>$notes</div>" : $notes;
	}

	// Returns the markup for an array of pony datase rows \\
	function get_ponies_html($Ponies, $wrap = true){
		global $CGDb;

		$HTML = $wrap ? '<ul id=list>' : '';
		if (!empty($Ponies)) foreach ($Ponies as $p){
			$p['label'] = htmlspecialchars($p['label']);
			$imgPth = "img/cg/{$p['id']}.png";
			if (!file_Exists(APPATH.$imgPth)) $imgPth = "img/blank-pixel.png";
			else $imgPth = $imgPth.'?'.filemtime(APPATH.$imgPth);
			$img = "<a href='/$imgPth' target=_blank title='Open image in new tab'><img src='/$imgPth' alt='".apos_encode($p['label'])."'></a>";
			if (PERM('inspector')) $img = "<div class='upload-wrap'>$img</div>";
			$img = "<div>$img</div>";

			$notes = get_notes_html($p);
			$tags = get_tags_html($p['id']);
			$colors = get_colors_html($p['id']);
			$editBtn = PERM('inspector') ? '<button class="edit typcn typcn-edit blue" title="Enable edit mode" disabled></button><button class="delete typcn typcn-trash red" title="Delete"></button>' : '';

			$HTML .= "<li id=p{$p['id']}>$img<div><strong>{$p['label']}$editBtn</strong>$notes$tags$colors</div></li>";
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

	/**
	 * Checks validity of a string based on regex
	 *  and responds if invalid chars are found
	 *
	 * @param string $string
	 * @param string $Thing
	 * @param string $pattern
	 *
	 * @return null
	 */
	function check_string_valid($string, $Thing, $pattern){
		$fails = array();
		if (preg_match("~$pattern~u", $string, $fails)){
			$invalid = array();
			foreach ($fails as $f)
				if (!in_array($f, $invalid))
					$invalid[] = $f;

			$s = count($invalid)!==1?'s':'';
			$the_following = count($invalid)!==1?' the following':'an';
			respond("$Thing contains $the_following invalid character$s: ".array_readable($invalid));
		}
	}
