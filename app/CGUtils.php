<?php

namespace App;

use App\Models\Appearance;
use App\Models\Color;
use App\Models\ColorGroup;
use App\Models\Cutiemark;
use App\Models\Logs\MajorChange;
use App\Models\PCGSlotGift;
use App\Models\PCGSlotHistory;
use App\Models\Post;
use SeinopSys\RGBAColor;

class CGUtils {
	public const GROUP_TAG_IDS_ASSOC = [
		'pony' => [
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
			437 => 'Non-pony Characters',
			96 => 'Outfits & Clothing',
			// add other tags here
			64 => 'Objects',
			-1 => 'Other',
		],
		'eqg' => [
			76 => 'Humans',
			-1 => 'Other',
		],
	];

	public const GUIDE_MAP = [
		'eqg' => 'EQG',
		'pony' => 'Pony',
	];

	/**
	 * Response creator for typeahead.js
	 *
	 * @param string|array $str
	 */
	public static function autocompleteRespond($str){
		header('Content-Type: application/json');
		if (\is_array($str))
			$str = JSON::encode($str);
		die($str);
	}

	/**
	 * Returns HTML for the full list
	 *
	 * @param Appearance[] $appearances
	 * @param string       $order_by
	 * @param bool         $eqg
	 * @param bool         $wrap
	 *
	 * @return string
	 */
	public static function getFullListHTML(array $appearances, $order_by, bool $eqg, $wrap = WRAP){
		$HTML = '';
		if (!empty($appearances)){
			$previews = !empty(UserPrefs::get('cg_fulllstprev'));
			switch ($order_by){
				case 'label':
					$PrevFirstLetter = '';
					$upcaseAZ = (string)new RegExp('^[A-Z]$');
					foreach ($appearances as $p){
						$FirstLetter = strtoupper($p->label[0]);
						if (!preg_match($upcaseAZ, $FirstLetter))
							$FirstLetter = '#';
						if (!is_numeric($FirstLetter) ? ($FirstLetter !== $PrevFirstLetter) : !is_numeric($PrevFirstLetter)){
							if ($PrevFirstLetter !== ''){
								$HTML .= '</ul></section>';
							}
							$PrevFirstLetter = $FirstLetter;
							$HTML .= "<section><h2>$PrevFirstLetter</h2><ul>";
						}
						self::_processFullListLink($p, $HTML, $previews);
					}
				break;
				case 'relevance':
					$Sorted = Appearances::sort($appearances, $eqg);
					foreach (CGUtils::GROUP_TAG_IDS_ASSOC[$eqg?'eqg':'pony'] as $Category => $CategoryName){
						if (empty($Sorted[$Category]))
							continue;

						$HTML .= "<section><h2>$CategoryName<button class='sort-alpha blue typcn typcn-sort-alphabetically hidden' title='Sort this section alphabetically'></button></h2><ul>";
						/** @var $Sorted Appearance[][] */
						foreach ($Sorted[$Category] as $p)
							self::_processFullListLink($p, $HTML, $previews);
						$HTML .= '</ul></section>';
					}
				break;
				case 'added':
					$HTML .= "<section><ul class='justify'>";
					/** @var $Sorted Appearance[][] */
					foreach ($appearances as $p)
						self::_processFullListLink($p, $HTML, $previews);
					$HTML .= '</ul></section>';
				break;
			}
		}
		return $wrap ? "<div id='full-list'>$HTML</div>" : $HTML;
	}

	/**
	 * @param Appearance $appearance
	 * @param string     $HTML
	 * @param bool       $previews
	 */
	private static function _processFullListLink(Appearance $appearance, &$HTML, bool $previews){
		$sprite = '';
		$url = "/cg/v/{$appearance->id}-".$appearance->getURLSafeLabel();
		if (Permission::sufficient('staff')){
			$SpriteURL = $appearance->getSpriteURL();
			if (!empty($SpriteURL)){
				if (!$previews)
					$sprite = "<span class='typcn typcn-image' title='Has a sprite'></span>&nbsp;";
				$class = 'color-green';
			}
			if (!empty($appearance->private))
				$class = 'color-orange';
			if (!empty($class))
				$url .= "' class='$class";
		}

		if ($previews){
			$preview_url = $appearance->getPreviewURL();
			$preview = $appearance->getSpriteURL(Appearance::SPRITE_SIZES['SOURCE'], $preview_url);
			$class = $preview_url === $preview ? 'class="border"' : '';
			$preview = "<img data-src='$preview' src='/img/loading-wedges.svg' alt='' $class>";
			$charTags = DB::$instance->query(
				"SELECT t.name FROM tags t
				LEFT JOIN tagged tg ON tg.tag_id = t.id OR tg.tag_id = t.synonym_of
				WHERE tg.appearance_id = ? AND t.type = 'char'", [$appearance->id]);
			if (!empty($charTags)){
				$aka = [];
				foreach ($charTags as $t){
					if (stripos($appearance->label, $t['name']) !== false)
						continue;

					$aka[] = $t['name'];
				}
				if (!empty($aka))
					$aka = '<span class="aka"><abbr title="Also known as">AKA</abbr> '.implode(', ', $aka).'</span>';
			}
		}
		else $preview = '';
		if (empty($aka))
			$aka = '';

		$HTML .= "<li><a href='$url'>$preview<span class='name'>$sprite{$appearance->label}</span>$aka</a></li>";
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
	 * @param string     $key
	 * @param string     $path
	 * @param array|null $allowedMimeTypes
	 * @param int[]      $min
	 * @param int[]      $max
	 *
	 * @return null
	 */
	public static function processUploadedImage($key, $path, $allowedMimeTypes, $min = null, $max = null){
		$minwidth = $min[0] ?? 1;
		$minheight = $min[1] ?? $minwidth;
		$maxwidth = $max[0] ?? 1000;
		$maxheight = $max[1] ?? $maxwidth;
		$min = [$minwidth,$minheight];
		$max = [$maxwidth,$maxheight];

		if (!isset($_FILES[$key])){
			self::grabImage($path,$allowedMimeTypes,$min,$max);
			return;
		}
		$file = $_FILES[$key];
		$tmp = $file['tmp_name'];
		if (empty($tmp))
			Response::fail('File upload failed; Reason unknown');

		[$width, $height] = Image::checkType($tmp, $allowedMimeTypes);
		Image::checkSize($tmp, $width, $height, $min, $max);
		CoreUtils::createFoldersFor($path);

		if (!move_uploaded_file($tmp, $path)){
			CoreUtils::deleteFile($tmp);
			Response::fail('File upload failed; Writing image file was unsuccessful');
		}
	}

	/**
	 * Gets the uploaded image for process_uploaded_image
	 *
	 * @param string     $path
	 * @param array|null $allowedMimeTypes
	 * @param array      $min
	 * @param array      $max
	 */
	public static function grabImage(string $path, $allowedMimeTypes, array $min, array $max){
		try {
			$Image = new ImageProvider(Posts::validateImageURL());
		}
		catch (\Exception $e){ Response::fail($e->getMessage()); }

		if ($Image->fullsize === false)
			Response::fail('Image could not be retrieved from external provider');

		$remoteFile = @File::get($Image->fullsize);
		if (empty($remoteFile))
			Response::fail('Remote file could not be found');
		if (File::put($path, $remoteFile) === false)
			Response::fail('Writing local image file was unsuccessful');

		list($width, $height) = Image::checkType($path, $allowedMimeTypes);
		Image::checkSize($path, $width, $height, $min, $max);
	}

	/**
	 * Checks and normalizes episode tag names
	 *
	 * @param string $tag
	 *
	 * @return string|false
	 */
	public static function normalizeEpisodeTagName(string $tag){
		global $EPISODE_ID_REGEX, $MOVIE_ID_REGEX;

		$_match = [];
		if (preg_match($EPISODE_ID_REGEX,$tag,$_match)){
			$season = \intval($_match[1], 10);
			if ($season === 0)
				return false;
			$episode = \intval($_match[2], 10);
			$name = 's'.CoreUtils::pad($season).'e'.CoreUtils::pad($episode);
			$episodeIsRange = !empty($_match[3]);
			if ($episodeIsRange){
				$episodeTo = \intval($_match[3], 10);
				if ($episodeTo-1 !== $episode)
					return false;

				$name .= '-'.CoreUtils::pad($episodeTo);
			}

			return $name;
		}
		if (preg_match($MOVIE_ID_REGEX,$tag,$_match)){
			$movie = \intval($_match[1], 10);
			if ($movie <= 0)
				return false;
			return "movie$movie";
		}
		else return false;
	}

	/**
	 * Checks the type of an episode tag name
	 *
	 * @param string $name
	 *
	 * @return string|false
	 */
	public static function checkEpisodeTagType(string $name):?string {
		global $EPISODE_ID_REGEX, $MOVIE_ID_REGEX;

		if (preg_match($EPISODE_ID_REGEX,$name,$_match))
			return 'episode';
		if (preg_match($MOVIE_ID_REGEX,$name,$_match))
			return 'movie';
		return null;
	}

	public const CHANGES_SECTION = <<<HTML
<section>
	<h2><span class='typcn typcn-warning'></span>List of major changes</h2>
	@
</section>
HTML;

	/**
	 * Renders HTML of the list of changes
	 *
	 * @param MajorChange[] $changes
	 * @param bool          $wrap
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function getMajorChangesHTML(?array $changes, bool $wrap = WRAP):string {
		$seeInitiator = Permission::sufficient('staff');
		/** @var $PonyCache Appearance[] */
		$HTML = '';
		if (\is_array($changes))
			foreach ($changes as $c){
				$initiator = $seeInitiator ? "<div class='by'><span class='typcn typcn-user'></span> {$c->log->actor->toAnchor()}</div>" : '';
				$appearance = $c->appearance->toAnchorWithPreview();
				$when = Time::tag($c->log->timestamp);
			}

		return Twig::$env->render('colorguide/_major_changes.html.twig', [
			'changes' => $changes,
		]);
	}

	public static function processPCGSlotHistoryData(string $type, ?string $data):?string {
		if ($data === null){
			if ($type === 'free_trial')
				return "This one's on the house";
			return '&mdash;';
		}

		$data = JSON::decode($data);
		switch ($type){
			case 'post_approved':
			case 'post_unapproved':
				/** @var $post Post|null */
				$post = Post::find($data['id']);
				$label = "Post #{$data['id']}";
				if (!empty($post))
					return $post->toAnchor($label);

				return "$label <span class='color-red typcn typcn-trash' title='Deleted'></span>";
			case 'appearance_add':
			case 'appearance_del':
				/** @var $appearance Appearance|null */
				$appearance = Appearance::find($data['id']);
				$label = "{$data['label']} (#{$data['id']})";
				if (!empty($appearance))
					return $appearance->toAnchorWithPreview();

				return "$label <span class='color-red typcn typcn-trash' title='Deleted'></span>";
			case 'gift_sent':
			case 'gift_accepted':
			case 'gift_rejected':
			case 'gift_refunded':
				$gift = PCGSlotGift::find($data['gift_id']);

				switch (explode('_', $type)[1]){
					case 'sent':
						return empty($gift) ? 'Unknown recipient' : 'To '.$gift->receiver->toAnchor();
					case 'accepted':
						return empty($gift) ? 'Unknown sender' : 'From '.$gift->sender->toAnchor();
					case 'rejected':
						return empty($gift) ? 'Unknown recipient' : 'By '.$gift->receiver->toAnchor();
					case 'refunded':
						return (empty($gift) ? 'The receiver' : $gift->receiver->toAnchor()).' did not claim this gift'.
							'<br>Refunded by '.(Permission::sufficient('staff') ? $gift->refunder->toAnchor() : 'a staff member');
				}
			break;
			case 'manual_give':
			case 'manual_take':
				$by = Users::get($data['by']);
				$link = empty($by) ? 'an unknown user' : $by->toAnchor();
				return 'By '.(Permission::sufficient('staff') ? $link : 'a staff member').
					(!empty($data['comment'])?'<br><q>'.CoreUtils::escapeHTML($data['comment']).'</q>' : '');
			default:
				return '<pre>'.htmlspecialchars(JSON::encode($data, JSON_PRETTY_PRINT)).'</pre>';
		}
	}

	/**
	 * Renders HTML of a user's slot history
	 *
	 * @param PCGSlotHistory[] $Entries
	 * @param bool             $wrap
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function getPCGSlotHistoryHTML(?array $Entries, bool $wrap = WRAP):string {
		$HTML = '';
		if (\is_array($Entries))
			foreach ($Entries as $entry){
				$type = PCGSlotHistory::CHANGE_DESC[$entry->change_type];
				$data = self::processPCGSlotHistoryData($entry->change_type, $entry->change_data);
				$when = Time::tag($entry->created);
				$dir = $entry->change_amount > 0 ? 'pos' : 'neg';
				$amount = ($entry->change_amount > 0 ? "\u{2B}$entry->change_amount" : "\u{2212}".(-$entry->change_amount));

				$HTML .= <<<HTML
	<tr class="change-$dir">
		<td>$type</td>
		<td>$data</td>
		<td>$amount</td>
		<td><span class="typcn typcn-time"></span> $when</td>
HTML;
			}
		if (!$wrap)
			return $HTML;

		return <<<HTML
<div class="responsive-table">
<table id='history-entries'>
	<thead>
		<th>Reason</th>
		<th>Details</th>
		<th>Amount</th>
		<th>When</th>
	</thead>
	<tbody>$HTML</tbody>
</table>
</div>
HTML;
	}

	/**
	 * Render appearance PNG image
	 *
	 * @param string     $CGPath
	 * @param Appearance $Appearance
	 *
	 * @throws \Exception
	 */
	public static function renderAppearancePNG($CGPath, $Appearance){
		$OutputPath = $Appearance->getPaletteFilePath();
		$FileRelPath = "$CGPath/v/{$Appearance->id}p.png";
		CoreUtils::fixPath($FileRelPath);
		if (file_exists($OutputPath))
			Image::outputPNG(null,$OutputPath,$FileRelPath);

		$OutHeight = 0;
		$SpriteWidth = $SpriteHeight = 0;
		$SpriteRightMargin = 10;
		$ColorCircleSize = 17;
		$ColorCircleRMargin = 5;
		$ColorNameFontSize = 12;
		$FontFile = APPATH.'font/Celestia Medium Redux.ttf';
		//$PixelatedFontFile = APPATH.'font/Volter (Goldfish).ttf';
		$PixelatedFontFile = $FontFile;
		if (!file_exists($FontFile))
			throw new \RuntimeException('Font file missing');
		$Name = $Appearance->label;
		$NameVerticalMargin = 5;
		$NameFontSize = 22;
		$TextMargin = 10;
		$ColorsOutputted = 0;
		$SplitTreshold = 12;
		$ColumnRightMargin = 20;

		// Detect if sprite exists and adjust image size & define starting positions
		$SpritePath = SPRITE_PATH."{$Appearance->id}.png";
		$SpriteExists = file_exists($SpritePath);
		if ($SpriteExists){
			/** @var $SpriteSize int[]|false */
			$SpriteSize = getimagesize($SpritePath);
			if ($SpriteSize === false)
				throw new \RuntimeException("The sprite image located at $SpritePath could not be loaded by getimagesize");

			$Sprite = imagecreatefrompng($SpritePath);
			/** @var $SpriteSize array */
			$SpriteHeight = $SpriteSize[HEIGHT];
			$SpriteWidth = $SpriteSize[WIDTH];
			$SpriteRealWidth = $SpriteWidth + $SpriteRightMargin;

			$OutHeight = $SpriteHeight;
		}
		else $SpriteRealWidth = 0;
		$origin = [
			'x' => $SpriteExists ? $SpriteRealWidth : $TextMargin,
			'y' => 0,
		];

		// Get color groups & calculate the space they take up
		$ColorGroups = $Appearance->color_groups;
		$CGFontSize = (int)round($NameFontSize/1.25);
		$CGVerticalMargin = $NameVerticalMargin;
		$GroupLabelBox = Image::saneGetTTFBox($CGFontSize, $FontFile, 'ABCDEFGIJKLMOPQRSTUVWQYZabcdefghijklmnopqrstuvwxyz');
		$ColorNameBox = Image::saneGetTTFBox($ColorNameFontSize, $PixelatedFontFile, 'AGIJKFagijkf');

		// Get export time & size
		$ExportTS = 'Generated at: '.Time::format(time(), Time::FORMAT_FULL);
		$ExportFontSize = (int)round($CGFontSize/1.5);
		$ExportBox = Image::saneGetTTFBox($ExportFontSize, $FontFile, $ExportTS);

		// Check how long & tall appearance name is, and set image width
		$NameBox = Image::saneGetTTFBox($NameFontSize, $FontFile, $Name);
		$OutWidth = $origin['x'] + max($NameBox['width'], $ExportBox['width']) + $TextMargin;

		// Set image height
		$OutHeight = max($origin['y'] + (($NameVerticalMargin*4) + $NameBox['height'] + $ExportBox['height']), $OutHeight);

		// Create base image
		$BaseImage = Image::createTransparent($OutWidth, $OutHeight);
		$BLACK = imagecolorallocate($BaseImage, 0, 0, 0);

		// If sprite exists, output it on base image
		if ($SpriteExists)
			Image::copyExact($BaseImage, $Sprite, 0, 0, $SpriteWidth, $SpriteHeight);

		// Output appearance name
		$origin['y'] += $NameVerticalMargin*2;
		Image::writeOn($BaseImage, $Name, $origin['x'], $NameFontSize, $BLACK, $origin, $FontFile);
		$origin['y'] += $NameVerticalMargin;

		// Output generation time
		Image::writeOn($BaseImage, $ExportTS, $origin['x'], $ExportFontSize, $BLACK, $origin, $FontFile);
		$origin['y'] += $NameVerticalMargin;

		if (!empty($ColorGroups)){
			$LargestX = 0;
			$AllColors = self::getColorsForEach($ColorGroups);
			foreach ($ColorGroups as $cg){
				$CGLabelBox = Image::saneGetTTFBox($CGFontSize, $FontFile, $cg->label);
				Image::calcRedraw($OutWidth, $OutHeight, $CGLabelBox['width']+$TextMargin, $GroupLabelBox['height']+$NameVerticalMargin+$CGVerticalMargin, $BaseImage, $origin);
				Image::writeOn($BaseImage, $cg->label, $origin['x'], $CGFontSize, $BLACK, $origin, $FontFile, $GroupLabelBox);
				$origin['y'] += $GroupLabelBox['height']+$CGVerticalMargin;

				if ($CGLabelBox['width'] > $LargestX){
					$LargestX = $CGLabelBox['width'];
				}

				if (!empty($AllColors[$cg->id]))
					foreach ($AllColors[$cg->id] as $c){
						$ColorNameLeftOffset = $ColorCircleSize + $ColorCircleRMargin;
						$CNBox = Image::saneGetTTFBox($ColorNameFontSize, $PixelatedFontFile, $c->label);

						$WidthIncrease = $ColorNameLeftOffset + $CNBox['width'] + $TextMargin;
						$HeightIncrease = max($ColorCircleSize, $CNBox['height']) + $CGVerticalMargin;
						Image::calcRedraw($OutWidth, $OutHeight, $WidthIncrease, $HeightIncrease, $BaseImage, $origin);

						Image::drawCircle($BaseImage, $origin['x'], $origin['y'], [$ColorCircleSize,$ColorCircleSize], $c->hex, $BLACK);

						$yOffset = 2;
						Image::writeOn($BaseImage, $c->label, $origin['x'] + $ColorNameLeftOffset, $ColorNameFontSize, $BLACK, $origin, $PixelatedFontFile, $ColorNameBox, $yOffset);
						$origin['y'] += $HeightIncrease;

						$ColorsOutputted++;

						$TotalWidth = $ColorNameLeftOffset+$CNBox['width'];
						if ($TotalWidth > $LargestX){
							$LargestX = $TotalWidth;
						}
					};

				if ($ColorsOutputted > $SplitTreshold){
					Image::calcRedraw($OutWidth, $OutHeight, 0, $NameVerticalMargin, $BaseImage, $origin);
					$origin['y'] =
						($NameVerticalMargin * 4)
						+ Image::saneGetTTFBox($NameFontSize, $FontFile, $Name)['height']
						+ Image::saneGetTTFBox($ExportFontSize, $FontFile, $ExportTS)['height'];

					$origin['x'] += $LargestX+$ColumnRightMargin;
					$ColorsOutputted = 0;
					$LargestX = 0;
				}
				else $origin['y'] += $NameVerticalMargin;
			};
		}

		$FinalBase = Image::createWhiteBG($OutWidth, $OutHeight);
		Image::drawSquare($FinalBase, 0, 0, [$OutWidth, $OutHeight], null, $BLACK);
		Image::copyExact($FinalBase, $BaseImage, 0, 0, $OutWidth, $OutHeight);

		if (!CoreUtils::createFoldersFor($OutputPath))
			Response::fail('Failed to create render directory');
		Image::outputPNG($FinalBase, $OutputPath, $FileRelPath);
	}

	public const CMDIR_SVG_PATH = FSPATH.'cg_render/appearance/#/cmdir-@.svg';

	// Generate appearance facing image (CM background)
	public static function renderCMFacingSVG($CGPath, Appearance $appearance){
		$facing = $_GET['facing'] ?? 'left';
		if (!\in_array($facing, Cutiemarks::VALID_FACING_VALUES, true))
			Response::fail('Invalid facing value specified!');

		$OutputPath = str_replace(['#','@'],[$appearance->id,$facing],self::CMDIR_SVG_PATH);
		$FileRelPath = $appearance->getFacingSVGURL($facing, false);
		if (file_exists($OutputPath))
			Image::outputSVG(null,$OutputPath,$FileRelPath);

		$ColorMapping = $appearance->getColorMapping(Appearance::DEFAULT_COLOR_MAPPING);

		$img = File::get(APPATH.'img/cm_facing/'.($facing===CM_FACING_RIGHT?'right':'left').'.svg');
		foreach (Appearance::DEFAULT_COLOR_MAPPING as $label => $defhex)
			$img = str_replace($label, $ColorMapping[$label] ?? $defhex, $img);

		Image::outputSVG($img,$OutputPath,$FileRelPath);
	}

	public static function renderCMSVG(Cutiemark $CutieMark, bool $output = true){
		if (empty($CutieMark))
			CoreUtils::notFound();

		$OutputPath = $CutieMark->getRenderedFilePath();
		$FileRelPath = $CutieMark->getRenderedRelativeURL();
		if (file_exists($OutputPath))
			Image::outputSVG(null,$OutputPath,$FileRelPath);

		$tokenized = $CutieMark->getTokenizedFile();
		if ($tokenized === null)
			CoreUtils::notFound();
		$img = self::untokenizeSvg($tokenized, $CutieMark->appearance_id);
		if (!$output){
			File::put($OutputPath, $img);
			return;
		}
		Image::outputSVG($img,$OutputPath,$FileRelPath);
	}

	public static function int2Hex(int $int){
		return '#'.strtoupper(CoreUtils::pad(dechex($int), 6));
	}

	private static function _coordGenerator($w, $h){
		for ($y = 0; $y < $h; $y++){
			for ($x = 0; $x < $w; $x++)
				yield [$x, $y];
		}
	}

	public static function getSpriteImageMap($AppearanceID){
		$PNGPath = SPRITE_PATH."$AppearanceID.png";
		$MapFile = new CachedFile(FSPATH."cg_render/appearance/$AppearanceID/linedata.json.gz", function($path) use($PNGPath) {
			return !file_exists($path) || filemtime($path) < filemtime($PNGPath);
		});
		if (!$MapFile->expired())
			$Map = $MapFile->read();
		else {
			if (!file_exists($PNGPath))
				Response::fail("There's no sprite image for appearance #$AppearanceID");

			list($PNGWidth, $PNGHeight) = getimagesize($PNGPath);
			$PNG = imagecreatefrompng($PNGPath);
			imagesavealpha($PNG, true);

			$allcolors = [];
			foreach (self::_coordGenerator($PNGWidth,$PNGHeight) as $pos){
				[$x, $y] = $pos;
				$rgb = imagecolorat($PNG, $x, $y);
				$colors = imagecolorsforindex($PNG, $rgb);
				$hex = strtoupper('#'.CoreUtils::pad(dechex($colors['red'])).CoreUtils::pad(dechex($colors['green'])).CoreUtils::pad(dechex($colors['blue'])));
				$opacity = $colors['alpha'] ?? 0;
				if ($opacity === 127)
					continue;
				$allcolors[$hex][$opacity][] = [$x, $y];
			}

			$currLine = null;
			$lines = [];
			$lastx = -2;
			$lasty = -2;
			$_colorsAssoc = [];
			$colorno = 0;
			foreach ($allcolors as $hex => $opacities){
				if (!isset($_colorsAssoc[$hex])){
					$_colorsAssoc[$hex] = $colorno;
					$colorno++;
				}
				foreach ($opacities as $opacity => $coords){
					foreach ($coords as $pos){
						[$x, $y] = $pos;

						if ($x-1 !== $lastx || $y !== $lasty){
							if ($currLine !== null)
								$lines[] = $currLine;
							$currLine = [
								'x' => $x,
								'y' => $y,
								'width' => 1,
								'colorid' => $_colorsAssoc[$hex],
								'opacity' => $opacity,
							];
						}
						else $currLine['width']++;

						$lastx = $x;
						$lasty = $y;
					}
				}
			}
			if ($currLine !== null)
				$lines[] = $currLine;

			$Output = [
				'width' => $PNGWidth,
				'height' => $PNGHeight,
				'linedata' => [],
				'colors' => array_flip($_colorsAssoc),
			];
			foreach ($lines as $line)
				$Output['linedata'][] = $line;

			$Map = $Output;
			$MapFile->update($Output);
		}
		return $Map;
	}

	/**
	 * @param string   $CGPath
	 * @param int      $AppearanceID
	 * @param string|null $size
	 */
	public static function renderSpritePNG($CGPath, $AppearanceID, $size = null){
		if ($size !== null)
			$size = \intval($size, 10);
		if (!\in_array($size, Appearance::SPRITE_SIZES, true))
			$size = 600;
		$outsize = $size === Appearance::SPRITE_SIZES['REGULAR'] ? '' : "-$size";

		$OutputPath = FSPATH."cg_render/appearance/{$AppearanceID}/sprite$outsize.png";
		$FileRelPath = "$CGPath/v/{$AppearanceID}s.png";
		CoreUtils::fixPath($FileRelPath);
		if (file_exists($OutputPath))
			Image::outputPNG(null,$OutputPath,$FileRelPath);

		$Map = self::getSpriteImageMap($AppearanceID);

		$SizeFactor = (int)round($size/300);
		$PNG = Image::createTransparent($Map['width']*$SizeFactor, $Map['height']*$SizeFactor);
		foreach ($Map['linedata'] as $line){
			$rgb = RGBAColor::parse($Map['colors'][$line['colorid']]);
			$color = imagecolorallocatealpha($PNG, $rgb->red, $rgb->green, $rgb->blue, $line['opacity']);
			Image::drawSquare($PNG, $line['x']*$SizeFactor, $line['y']*$SizeFactor, [$line['width']*$SizeFactor, $SizeFactor], $color, null);
		}

		Image::outputPNG($PNG, $OutputPath, $FileRelPath);
	}

	public static function renderSpriteSVG($CGPath, $AppearanceID){
		$Map = self::getSpriteImageMap($AppearanceID);
		if (empty($Map))
			CoreUtils::notFound();

		$OutputPath = FSPATH."cg_render/appearance/{$AppearanceID}/sprite.svg";
		$FileRelPath = "$CGPath/v/{$AppearanceID}s.svg";
		if (file_exists($OutputPath))
			Image::outputSVG(null,$OutputPath,$FileRelPath);

		$IMGWidth = $Map['width'];
		$IMGHeight = $Map['height'];
		$strokes = [];
		foreach ($Map['linedata'] as $line){
			$hex = $Map['colors'][$line['colorid']];
			if ($line['opacity'] !== 0){
				$opacity = (float) number_format((127- $line['opacity'])/127, 2, '.', '');
				$hex .= "' opacity='{$opacity}";
			}
			$strokes[$hex][] = "M{$line['x']} {$line['y']} l{$line['width']} 0Z";
		}
		$SVG = <<<XML
<svg version='1.1' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 $IMGWidth $IMGHeight' enable-background='new 0 0 $IMGWidth $IMGHeight' xml:space='preserve'>
XML;
		foreach ($strokes as $hex => $defs){
			$d = '';
			foreach ($defs as $def)
				$d .= "$def ";
			$d = rtrim($d);
			$SVG .= /** @lang XML */
				"<path stroke='$hex' d='$d'/>";
		}
		$SVG .= '</svg>';

		Image::outputSVG($SVG, $OutputPath, $FileRelPath);
	}

	public const PREVIEW_SVG_PATH = FSPATH.'cg_render/appearance/#/preview.svg';

	public static function renderPreviewSVG($CGPath, Appearance $Appearance){
		$OutputPath = str_replace('#',$Appearance->id,self::PREVIEW_SVG_PATH);
		$FileRelPath = "$CGPath/v/{$Appearance->id}p.svg";
		if (file_exists($OutputPath))
			Image::outputSVG(null,$OutputPath,$FileRelPath);

		$SVG = '';
		$PreviewColors = $Appearance->preview_colors;
		$colorCount = \count($PreviewColors);
		switch ($colorCount){
			case 0:
				$SVG .= '<rect fill="#FFFFFF" width="2" height="2"/><rect fill="#EFEFEF" width="1" height="1"/><rect fill="#EFEFEF" width="1" height="1" x="1" y="1"/>';
			break;
			case 1:
				$SVG .= /** @lang XML */
					"<rect x='0' y='0' width='2' height='2' fill='{$PreviewColors[0]->hex}'/>";
			break;
			case 3:
				$SVG .= <<<XML
<rect x='0' y='0' width='2' height='2' fill='{$PreviewColors[0]['hex']}'/>
<rect x='0' y='1' width='1' height='1' fill='{$PreviewColors[1]['hex']}'/>
<rect x='1' y='1' width='1' height='1' fill='{$PreviewColors[2]['hex']}'/>
XML;
			break;
			case 2:
			case 4:
				$x = 0;
				$y = 0;
				foreach ($PreviewColors as $c){
					$w = $x % 2 === 0 ? 2 : 1;
					$h = $y % 2 === 0 ? 2 : 1;
					$SVG .= "<rect x='$x' y='$y' width='$w' height='$h' fill='{$c->hex}'/>";
					$x++;
					if ($x > 1){
						$x = 0;
						$y = 1;
					}
				}
			break;
		}

		// Only apply blur if we have colors
		if ($colorCount > 0)
			$SVG = "<defs><filter id='b' x='0' y='0'><feGaussianBlur in='SourceGraphic' stdDeviation='0.4' /></filter></defs><g filter='url(#b)'>$SVG</g>";

		$SVG = /** @lang XML */
			"<svg version='1.1' xmlns='http://www.w3.org/2000/svg' viewBox='.5 .5 1 1' enable-background='new 0 0 2 2' xml:space='preserve' preserveAspectRatio='xMidYMid slice'>$SVG</svg>";

		Image::outputSVG($SVG, $OutputPath, $FileRelPath);
	}

	/**
	 * @param Appearance $Appearance
	 */
	public static function getSwatchesAI(Appearance $Appearance){
		$label = $Appearance->label;
		$JSON = [
			'Exported at' => gmdate('Y-m-d H:i:s \G\M\T'),
			'Version' => '1.4',
		];
		$JSON[$label] = [];

		$CGs = $Appearance->color_groups;
		$Colors = self::getColorsForEach($CGs, true);
		foreach ($CGs as $cg){
			$JSON[$label][$cg->label] = [];
			foreach ($Colors[$cg->id] as $c)
				$JSON[$label][$cg->label][$c->label] = $c->hex;
		}

		CoreUtils::downloadAsFile(JSON::encode($JSON), "$label.json");
	}
	/**
	 * @param Appearance $Appearance
	 */
	public static function getSwatchesInkscape(Appearance $Appearance){
		$label = $Appearance->label;
		$exportts = gmdate('Y-m-d H:i:s T');
		$File = <<<GPL
GIMP Palette
Name: $label
Columns: 6
#
# Exported at: $exportts
#

GPL;

		$CGs = $Appearance->color_groups;
		$Colors = self::getColorsForEach($CGs, true);
		foreach ($CGs as $cg){
			foreach ($Colors[$cg->id] as $c){
				if (empty($c->hex))
					continue;
				$rgb = RGBAColor::parse($c->hex);
				$File .= CoreUtils::pad($rgb->red,3,' ').' '.CoreUtils::pad($rgb->green,3,' ').' '.CoreUtils::pad($rgb->blue,3,' ').' '.$cg->label.' | '.$c->label.PHP_EOL;
			}
		}

		CoreUtils::downloadAsFile(rtrim($File), "$label.gpl");
	}

	/**
	 * Detect all colors inside the SVG file & replace with a mapping to guide colors
	 *
	 * @param string $svg Image data
	 * @param int $appearance_id
	 *
	 * @return string Tokenized SVG file
	 */
	public static function tokenizeSvg(string $svg, int $appearance_id):string {
		/** @var $CMColorGroup ColorGroup */
		$CMColorGroup = DB::$instance->where('label', 'Cutie Mark')->where('appearance_id', $appearance_id)->getOne(ColorGroup::$table_name);
		if (empty($CMColorGroup))
			return $svg;

		RGBAColor::forEachColorIn($svg, function(RGBAColor $color) use ($CMColorGroup){
			/** @var $dbcolor Color[] */
			$dbcolor = DB::$instance->where('hex', $color->toHex())->where('group_id', $CMColorGroup->id)->get(Color::$table_name);

			if (empty($dbcolor))
				return sprintf('<!--#/%s-->', mb_substr($color->toHexa(), 1));

			$id = '@'.$dbcolor[0]->id;
			if ($color->isTransparent())
				$id .= ','.$color->alpha;
			return "<!--$id-->";
		});

		return $svg;
	}

	/**
	 * Detect tokenized colors inside SVG file & replace with colors from guide
	 *
	 * @param string $svg Image data
	 * @param int $appearance_id
	 *
	 * @return string Un-tokenized SVG file
	 */
	public static function untokenizeSvg(string $svg, int $appearance_id):string {
		/** @var $CMColorGroup ColorGroup */
		$CMColorGroup = DB::$instance->where('label', 'Cutie Mark')->where('appearance_id', $appearance_id)->getOne(ColorGroup::$table_name);
		if (empty($CMColorGroup))
			return $svg;

		$svg = preg_replace_callback(new RegExp('<!--@(\d+)(?:,([\d.]+))?-->'), function($match){
			/** @var $dbcolor Color */
			$dbcolor = Color::find($match[1]);

			if (empty($dbcolor))
				return $match;

			$color = RGBAColor::parse($dbcolor->hex);
			$color->alpha = (float) (!empty($match[2]) ? $match[2] : 1);
			return (string)$color;
		}, $svg);
		$svg = preg_replace_callback(new RegExp('<!--#/([A-F\d]{8})-->'), function($match){
			$color = RGBAColor::parse("#{$match[1]}");
			// This would restore the original color
			//return (string) $color;
			// Hopefully an inverted, transparent color will stand out
			return (string) $color->setAlpha(.75)->invert();
		}, $svg);

		return $svg;
	}

	public static function validateTagName($key){
		$name = strtolower((new Input($key,function($value, $range){
			if (Input::checkStringLength($value,$range,$code))
				return $code;
			if (strpos($value, ',') !== false)
				return 'comma';
			if ($value[0] === '-')
				return 'dash';
		}, [
			Input::IN_RANGE => [2,64],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Tag name cannot be empty',
				Input::ERROR_INVALID => 'Tag name (@value) cannot be empty',
				Input::ERROR_RANGE => 'Tag name must be between @min and @max characters',
				'dash' => 'Tag name (@value) cannot start with a dash',
				'comma' => 'Tag name (@value) cannot contain commas',
			]
		]))->out());
		CoreUtils::checkStringValidity($name,'Tag name',INVERSE_TAG_NAME_PATTERN);
		return $name;
	}

	public static $CM_DIR = [
		CM_FACING_LEFT => 'Head-tail',
		CM_FACING_RIGHT => 'Tail-head',
	];

	public const ELASTIC_BASE = [
		'index' => 'appearances',
	];

	/**
	 * Performs an ElasticSearch search operation
	 *
	 * @param array      $body
	 * @param Pagination $Pagination
	 *
	 * @return array
	 */
	public static function searchElastic(array $body, Pagination $Pagination){
		$params = array_merge(self::ELASTIC_BASE, $Pagination->toElastic(), [
			'type' => 'entry',
			'body' => $body,
		]);
		return CoreUtils::elasticClient()->search($params);
	}

	/**
	 * Get the colors belonging to a set of color groups
	 *
	 * @param ColorGroup[] $Groups
	 * @param bool         $skip_null Whether to include "empty" colors with null HEX value
	 *
	 * @return Color[][]
	 */
	public static function getColorsForEach($Groups, $skip_null = false):?array {
		if (empty($Groups)){
			return null;
		}

		$GroupIDs = [];
		foreach ($Groups as $g){
			$GroupIDs[] = $g->id;
		}

		$colors = Color::find('all', [
			'conditions' => [
				'group_id IN (?)'.($skip_null ? ' AND hex IS NOT NULL' : ''),
				$GroupIDs
			],
			'order' => 'group_id asc, "order" asc',
		]);
		if (empty($colors)){
			return null;
		}

		$sorted = [];
		foreach ($colors as $row){
			$sorted[$row->group_id][] = $row;
		}

		return $sorted;
	}

	/**
	 * @param RGBAColor[] $colors
	 *
	 * @return string|null
	 */
	public static function stringifyColors(?array $colors):?string {
		if (empty($colors))
			return null;

		$return = [];
		foreach ($colors as $c){
			$return[] = ($c->linked_to !== null ? '@'.$c->linked_to : $c->hex).' '.$c->label;
		}

		return implode("\n", $return);
	}

	/**
	 * @param ColorGroup[] $cgs
	 *
	 * @return string
	 */
	public static function stringifyColorGroups($cgs):string{
		if (empty($cgs))
			return '';

		$return = [];
		foreach ($cgs as $i => $c)
			$return[] = $c->label;

		return implode("\n", $return);
	}

	public static function roundHex(string $hex):string {
		$color = RGBAColor::parse($hex);
		foreach (RGBAColor::COMPONENTS as $key){
			$value = &$color->{$key};
			if ($value <= 3)
				$value = 0;
			else if ($value >= 252)
				$value = 255;
		}
		return $color->toHex();
	}

	/**
	 * Turns tag names into more readable text, e.g. "sNeM" into "S0N E0M" and "movie#N" into "Movie #N"
	 *
	 * @param string $tagname
	 *
	 * @return string
	 */
	public static function expandEpisodeTagName(string $tagname):string {
		global $EPISODE_ID_REGEX, $MOVIE_ID_REGEX;

		if (preg_match($EPISODE_ID_REGEX, $tagname, $_match))
			return 'S'.CoreUtils::pad($_match[1]).' E'.CoreUtils::pad($_match[2]);
		if (preg_match($MOVIE_ID_REGEX, $tagname, $_match))
			return 'Movie #'.$_match[1];

		return $tagname;
	}

	/**
	 * @return int|null
	 */
	public static function validateAppearancePageID():?int {
		return (new Input('APPEARANCE_PAGE', 'int', [
			Input::IS_OPTIONAL => true,
			Input::IN_RANGE => [0, null],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_RANGE => 'Appearance ID must be greater than or equal to @min'
			]
		]))->out();
	}
}