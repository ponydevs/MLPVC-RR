<?php

namespace App;

use App\Models\Appearance;
use App\Models\Color;
use App\Models\ColorGroup;
use App\Models\Logs\MajorChange;

class CGUtils {
	const GROUP_TAG_IDS_ASSOC = [
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
	];

	/**
	 * Response creator for typeahead.js
	 *
	 * @param string $str
	 */
	public static function autocompleteRespond($str){
		header('Content-Type: application/json');
		if (is_array($str))
			$str = JSON::encode($str);
		die($str);
	}

	/**
	 * Returns HTML for the full list
	 *
	 * @param Appearance[] $Appearances
	 * @param bool         $GuideOrder
	 * @param bool         $wrap
	 *
	 * @return string
	 */
	public static function getFullListHTML(array $Appearances, $GuideOrder, $wrap = WRAP){
		$HTML = '';
		if (!empty($Appearances)){
			$previews = !empty(UserPrefs::get('cg_fulllstprev'));
			if (!$GuideOrder){
				$PrevFirstLetter = '';
				foreach ($Appearances as $p){
					$FirstLetter = strtoupper($p->label[0]);
					if (!is_numeric($FirstLetter) ? ($FirstLetter !== $PrevFirstLetter) : !is_numeric($PrevFirstLetter)){
						if ($PrevFirstLetter !== ''){
							$HTML .= '</ul></section>';
						}
						$PrevFirstLetter = $FirstLetter;
						$HTML .= "<section><h2>$PrevFirstLetter</h2><ul>";
					}
					self::_processFullListLink($p, $HTML, $previews);
				}
			}
			else {
				$Sorted = Appearances::sort($Appearances);
				foreach (CGUtils::GROUP_TAG_IDS_ASSOC as $Category => $CategoryName){
					if (empty($Sorted[$Category]))
						continue;

					$HTML .= "<section><h2>$CategoryName<button class='sort-alpha blue typcn typcn-sort-alphabetically' style='display:none' title='Sort this section alphabetically'></button></h2><ul>";
					/** @var $Sorted Appearance[][] */
					foreach ($Sorted[$Category] as $p)
						self::_processFullListLink($p, $HTML, $previews);
					$HTML .= '</ul></section>';
				}
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
		$url = "/cg/v/{$appearance->id}-".$appearance->getSafeLabel();
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
		$label = $appearance->processLabel();

		if ($previews){
			$preview = $appearance->getSpriteURL(Appearance::SPRITE_SIZES['SOURCE'], $appearance->getPreviewURL());
			$preview = "<img data-src='$preview' src='/img/blank-pixel.png' alt=''>";
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

		$HTML .= "<li><a href='$url'>$preview<span class='name'>$sprite$label</span>$aka</a></li>";
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
	 * @param array      $min
	 * @param array      $max
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

		if (!isset($_FILES[$key]))
			return self::grabImage($path,$allowedMimeTypes,$min,$max);
		$file = $_FILES[$key];
		$tmp = $file['tmp_name'];
		if (CoreUtils::length($tmp) < 1) Response::fail('File upload failed; Reason unknown');

		list($width, $height) = Image::checkType($tmp, $allowedMimeTypes);
		CoreUtils::createUploadFolder($path);

		if (!move_uploaded_file($tmp, $path)){
			@unlink($tmp);
			Response::fail('File upload failed; Writing image file was unsuccessful');
		}

		Image::checkSize($path, $width, $height, $min, $max);
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

		$remoteFile = @file_get_contents($Image->fullsize);
		if (empty($remoteFile))
			Response::fail('Remote file could not be found');
		if (!file_put_contents($path, $remoteFile))
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
	public static function checkEpisodeTagName(string $tag){
		global $EPISODE_ID_REGEX, $MOVIE_ID_REGEX;

		$_match = [];
		if (preg_match($EPISODE_ID_REGEX,$tag,$_match)){
			$season = intval($_match[1], 10);
			if ($season === 0)
				return false;
			$episode = intval($_match[2], 10);
			$name = 's'.CoreUtils::pad($season).'e'.CoreUtils::pad($episode);
			$episodeIsRange = !empty($_match[3]);
			if ($episodeIsRange){
				$episodeTo = intval($_match[3], 10);
				if ($episodeTo-1 !== $episode)
					return false;

				$name .= '-'.CoreUtils::pad($episodeTo);
			}

			return $name;
		}
		if (preg_match($MOVIE_ID_REGEX,$tag,$_match)){
			$movie = intval($_match[1], 10);
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
	public static function checkEpisodeTagType(string $name):string {
		global $EPISODE_ID_REGEX, $MOVIE_ID_REGEX;

		if (preg_match($EPISODE_ID_REGEX,$name,$_match))
			return 'episode';
		if (preg_match($MOVIE_ID_REGEX,$name,$_match))
			return 'movie';
		return null;
	}

	const CHANGES_SECTION = <<<HTML
<section>
	<h2><span class='typcn typcn-warning'></span>List of major changes</h2>
	@
</section>
HTML;

	/**
	 * Renders HTML of the list of changes
	 *
	 * @param MajorChange[] $Changes
	 * @param bool          $wrap
	 * @param bool          $showAppearance
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function getChangesHTML(?array $Changes, bool $wrap = WRAP, bool $showAppearance = false):string {
		$seeInitiator = Permission::sufficient('staff');
		/** @var $PonyCache Appearance[] */
		$HTML = '';
		if (is_array($Changes))
			foreach ($Changes as $c){
				$initiator = $appearance = '';
				if ($seeInitiator){
					$main = $c->log;
					$initiator = ' by '.$main->actor->getProfileLink();
				}
				if ($showAppearance)
					$appearance = $c->appearance->getLinkWithLabelHTML().': ';
				$HTML .= "<li>$appearance{$c->reason} - ".Time::tag($c->log->timestamp)."$initiator</li>";
			};
		return $wrap ? "<ul id='changes'>$HTML</ul>" : $HTML;
	}

	const
		CLEAR_PREVIEW = 'preview.svg',
		CLEAR_PALETTE = 'palette.png',
		CLEAR_CMDIR_LEFT = 'cmdir-left.svg',
		CLEAR_CMDIR_RIGHT = 'cmdir-right.svg',
		CLEAR_SPRITE = 'sprite.png',
		CLEAR_SPRITE_SVG = 'sprite.svg',
		CLEAR_SPRITE_MAP = 'linedata.json.gz';

	const CLEAR_BY_DEFAULT = [
		self::CLEAR_PREVIEW,
		self::CLEAR_PALETTE,
		self::CLEAR_CMDIR_LEFT,
		self::CLEAR_CMDIR_RIGHT,
		self::CLEAR_SPRITE,
		self::CLEAR_SPRITE_SVG,
		self::CLEAR_SPRITE_MAP,
	];

	/**
	 * Deletes rendered images of an appearance (forcing its re-generation)
	 *
	 * @param int   $AppearanceID
	 * @param array $which
	 *
	 * @return bool
	 */
	public static function clearRenderedImages(int $AppearanceID, array $which = self::CLEAR_BY_DEFAULT):bool {
		$RenderedPath = FSPATH."cg_render/$AppearanceID";
		$success = [];
		foreach ($which as $suffix){
			if (file_exists("$RenderedPath-$suffix"))
				$success[] = unlink("$RenderedPath-$suffix");
		}
		return !in_array(false, $success);
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
		$OutputPath = FSPATH."cg_render/{$Appearance->id}-palette.png";
		$FileRelPath = "$CGPath/v/{$Appearance->id}p.png";
		CoreUtils::fixPath($FileRelPath);
		if (file_exists($OutputPath))
			Image::outputPNG(null,$OutputPath,$FileRelPath);

		$OutWidth = 0;
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
			throw new \Exception('Font file missing');
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
			/** @var $SpriteSize int[] */
			$SpriteSize = getimagesize($SpritePath);
			if ($SpriteSize === false)
				throw new \RuntimeException("The sprite image located at $SpritePath could not be loaded by getimagesize");

			$Sprite = imagecreatefrompng($SpritePath);
			/** @var $SpriteSize array */
			$SpriteHeight = $SpriteSize[HEIGHT];
			$SpriteWidth = $SpriteSize[WIDTH];
			$SpriteRealWidth = $SpriteWidth + $SpriteRightMargin;

			$OutWidth = $SpriteRealWidth;
			$OutHeight = $SpriteHeight;
		}
		else $SpriteRealWidth = 0;
		$origin = [
			'x' => $SpriteExists ? $SpriteRealWidth : $TextMargin,
			'y' => 0,
		];

		// Get color groups & calculate the space they take up
		$ColorGroups = $Appearance->color_groups;
		$CGCount = count($ColorGroups);
		$CGFontSize = round($NameFontSize/1.25);
		$CGVerticalMargin = $NameVerticalMargin;
		$GroupLabelBox = Image::saneGetTTFBox($CGFontSize, $FontFile, 'ABCDEFGIJKLMOPQRSTUVWQYZabcdefghijklmnopqrstuvwxyz');
		$ColorNameBox = Image::saneGetTTFBox($ColorNameFontSize, $PixelatedFontFile, 'AGIJKFagijkf');
		$CGsHeight = $CGCount*($GroupLabelBox['height'] + ($CGVerticalMargin*2) + $ColorCircleSize);

		// Get export time & size
		$ExportTS = 'Generated at: '.Time::format(time(), Time::FORMAT_FULL);
		$ExportFontSize = round($CGFontSize/1.5);
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
			$LargestLabel = '';
			$AllColors = self::getColorsForEach($ColorGroups);
			foreach ($ColorGroups as $cg){
				$CGLabelBox = Image::saneGetTTFBox($CGFontSize, $FontFile, $cg->label);
				Image::calcRedraw($OutWidth, $OutHeight, $CGLabelBox['width']+$TextMargin, $GroupLabelBox['height']+$NameVerticalMargin+$CGVerticalMargin, $BaseImage, $origin);
				Image::writeOn($BaseImage, $cg->label, $origin['x'], $CGFontSize, $BLACK, $origin, $FontFile, $GroupLabelBox);
				$origin['y'] += $GroupLabelBox['height']+$CGVerticalMargin;

				if ($CGLabelBox['width'] > $LargestX){
					$LargestX = $CGLabelBox['width'];
					$LargestLabel = $cg->label;
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
							$LargestLabel = $c->label;
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

		if (!CoreUtils::createUploadFolder($OutputPath))
			Response::fail('Failed to create render directory');
		Image::outputPNG($FinalBase, $OutputPath, $FileRelPath);
	}

	const CMDIR_SVG_PATH = FSPATH.'cg_render/#-cmdir-@.svg';

	const DEFAULT_COLOR_MAPPING = [
		'Coat Outline' => '#0D0D0D',
		'Coat Shadow Outline' => '#000000',
		'Coat Fill' => '#2B2B2B',
		'Coat Shadow Fill' => '#171717',
		'Mane & Tail Outline' => '#333333',
		'Mane & Tail Fill' => '#5E5E5E',
	];

	public static function getColorMapping($AppearanceID, $DefaultColorMapping){
		$Colors = DB::$instance->query(
			'SELECT cg.label as cglabel, c.label as clabel, c.hex
			FROM color_groups cg
			LEFT JOIN colors c on c.group_id = cg.id
			WHERE cg.appearance_id = ?
			ORDER BY cg.label ASC, c.label ASC', [$AppearanceID]);

		$ColorMapping = [];
		foreach ($Colors as $row){
			$cglabel = preg_replace(new RegExp('^(Costume|Dress)$'),'Coat',$row['cglabel']);
			$colorlabel = preg_replace(new RegExp('^(?:(?:(?:Purple|Yellow|Red)\s)?(?:Main|First|Normal|Gradient(?:\s(?:Light))?)\s)?(.+?)(?:\s\d+)?(?:/.*)?$'),'$1', $row['clabel']);
			$label = "$cglabel $colorlabel";
			if (isset($DefaultColorMapping[$label]) && !isset($ColorMapping[$label]))
				$ColorMapping[$label] = $row['hex'];
		}
		if (!isset($ColorMapping['Coat Shadow Outline']) && isset($ColorMapping['Coat Outline']))
			$ColorMapping['Coat Shadow Outline'] = $ColorMapping['Coat Outline'];
		if (!isset($ColorMapping['Coat Shadow Fill']) && isset($ColorMapping['Coat Fill']))
			$ColorMapping['Coat Shadow Fill'] = $ColorMapping['Coat Fill'];

		return $ColorMapping;
	}

	// Generate CM preview image
	public static function renderCMDirectionSVG($CGPath, $AppearanceID){
		if (empty($_GET['facing']))
			$Facing = 'left';
		else {
			$Facing = $_GET['facing'];
			if (!in_array($Facing, Cutiemarks::VALID_FACING_VALUES, true))
				Response::fail('Invalid facing value specified!');
		}

		$OutputPath = str_replace(['@','#'],[$Facing,$AppearanceID],self::CMDIR_SVG_PATH);
		$FileRelPath = "$CGPath/v/{$AppearanceID}d.svg?facing=$Facing";
		if (file_exists($OutputPath))
			Image::outputSVG(null,$OutputPath,$FileRelPath);

		$ColorMapping = self::getColorMapping($AppearanceID, self::DEFAULT_COLOR_MAPPING);

		$img = file_get_contents(APPATH.'img/cm_facing/'.($Facing===CM_FACING_RIGHT?'right':'left').'.svg');
		foreach (self::DEFAULT_COLOR_MAPPING as $label => $defhex)
			$img = str_replace($label, $ColorMapping[$label] ?? $defhex, $img);

		Image::outputSVG($img,$OutputPath,$FileRelPath);
	}

	public static function int2Hex(int $int){
		return '#'.strtoupper(CoreUtils::pad(dechex($int), 6));
	}

	public static function getSpriteImageMap($AppearanceID){
		$PNGPath = SPRITE_PATH."$AppearanceID.png";
		$MapPath = FSPATH."cg_render/$AppearanceID-linedata.json.gz";
		if (file_exists($MapPath) && filemtime($MapPath) >= filemtime($PNGPath))
			$Map = JSON::decode(gzuncompress(file_get_contents($MapPath)));
		else {
			if (!file_exists($PNGPath))
				return null;

			list($PNGWidth, $PNGHeight) = getimagesize($PNGPath);
			$PNG = imagecreatefrompng($PNGPath);
			imagesavealpha($PNG, true);

			$allcolors = [];
			function coords($w, $h){
				for ($y = 0; $y < $h; $y++){
					for ($x = 0; $x < $w; $x++)
						yield [$x, $y];
				}
			}
			foreach (coords($PNGWidth,$PNGHeight) as $pos){
				list($x, $y) = $pos;
				$rgb = imagecolorat($PNG, $x, $y);
				$colors = imagecolorsforindex($PNG, $rgb);
				$hex = strtoupper('#'.CoreUtils::pad(dechex($colors['red'])).CoreUtils::pad(dechex($colors['green'])).CoreUtils::pad(dechex($colors['blue'])));
				$opacity = $colors['alpha'] ?? 0;
				if ($opacity === 127)
					continue;
				$allcolors[$hex][$opacity][] = [$x, $y];
			}

			$mapping = 0;

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
						list($x, $y) = $pos;

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
			if (isset($currLine))
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
			file_put_contents($MapPath, gzcompress(JSON::encode($Output), 9));
		}
		return $Map;
	}

	/**
	 * @param string   $CGPath
	 * @param int      $AppearanceID
	 * @param int|null $size
	 */
	public static function renderSpritePNG($CGPath, $AppearanceID, ?int $size = null){
		if (!in_array($size, Appearance::SPRITE_SIZES, true))
			$size = 600;
		$outsize = $size === Appearance::SPRITE_SIZES['REGULAR'] ? '' : "-$size";

		$OutputPath = FSPATH."cg_render/{$AppearanceID}-sprite$outsize.png";
		$FileRelPath = "$CGPath/v/{$AppearanceID}s.png";
		CoreUtils::fixPath($FileRelPath);
		if (file_exists($OutputPath))
			Image::outputPNG(null,$OutputPath,$FileRelPath);

		$Map = self::getSpriteImageMap($AppearanceID);

		$SizeFactor = round($size/300);
		$PNG = Image::createTransparent($Map['width']*$SizeFactor, $Map['height']*$SizeFactor);
		foreach ($Map['linedata'] as $line){
			$rgb = CoreUtils::hex2Rgb($Map['colors'][$line['colorid']]);
			$color = imagecolorallocatealpha($PNG, $rgb[0], $rgb[1], $rgb[2], $line['opacity']);
			Image::drawSquare($PNG, $line['x']*$SizeFactor, $line['y']*$SizeFactor, [$line['width']*$SizeFactor, $SizeFactor], $color, null);
		}

		Image::outputPNG($PNG, $OutputPath, $FileRelPath);
	}

	public static function renderSpriteSVG($CGPath, $AppearanceID){
		$Map = self::getSpriteImageMap($AppearanceID);
		if (empty($Map))
			CoreUtils::notFound();

		$OutputPath = FSPATH."cg_render/{$AppearanceID}-sprite.svg";
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

	const PREVIEW_SVG_PATH = FSPATH.'cg_render/#-preview.svg';

	public static function renderPreviewSVG($CGPath, Appearance $Appearance){
		$OutputPath = str_replace('#',$Appearance->id,self::PREVIEW_SVG_PATH);
		$FileRelPath = "$CGPath/v/{$Appearance->id}p.svg";
		if (file_exists($OutputPath))
			Image::outputSVG(null,$OutputPath,$FileRelPath);

		$SVG = '';
		$PreviewColors = $Appearance->preview_colors;

		switch (count($PreviewColors)){
			case 1:
				$SVG .= /** @lang XML */
					"<rect x='0' y='0' width='2' height='2' fill='{$PreviewColors[0]['hex']}'/>";
			break;
			case 3:
				$SVG .= <<<XML
<rect x='0' y='0' width='2' height='2' fill='{$PreviewColors[0]['hex']}'/>
<rect x='0' y='1' width='1' height='1' fill='{$PreviewColors[1]['hex']}'/>
<rect x='1' y='1' width='1' height='1' fill='{$PreviewColors[2]['hex']}'/>
XML;
			break;
			case 0:
				$SVG .= '<rect fill="#FFFFFF" width="2" height="2"/><rect fill="#EFEFEF" width="1" height="1"/><rect fill="#EFEFEF" width="1" height="1" x="1" y="1"/>';
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


		$SVG = /** @lang XML */
			"<svg version='1.1' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 2 2' enable-background='new 0 0 2 2' xml:space='preserve' preserveAspectRatio='xMidYMid slice'>$SVG</svg>";

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
		$Colors = self::getColorsForEach($CGs);
		foreach ($CGs as $cg){
			$JSON[$label][$cg->label] = [];
			foreach ($Colors[$cg->id] as $c)
				$JSON[$label][$cg->label][$c->label] = $c->hex;
		}

		CoreUtils::downloadFile(JSON::encode($JSON), "$label.json");
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
		$Colors = self::getColorsForEach($CGs);
		foreach ($CGs as $cg){
			foreach ($Colors[$cg->id] as $c){
				if (empty($c->hex))
					continue;
				$rgb = CoreUtils::hex2Rgb($c->hex);
				$File .= CoreUtils::pad($rgb[0],3,' ').' '.CoreUtils::pad($rgb[1],3,' ').' '.CoreUtils::pad($rgb[2],3,' ').' '.$cg->label.' | '.$c->label.PHP_EOL;
			}
		}

		CoreUtils::downloadFile(rtrim($File), "$label.gpl");
	}

	public static function validateTagName($key){
		$name = strtolower((new Input($key,function($value, $range){
			if (Input::checkStringLength($value,$range,$code))
				return $code;
			if ($value[0] === '-')
				return 'dash';
			$sanitized_name = preg_replace(new RegExp('[^a-z\d]'),'',$value);
			if (preg_match(new RegExp('^(b+[a4]+w*d+|g+[uo0]+d+|(?:b+[ae3]+|w+[o0u]+r+)[s5]+[t7]+)(e+r+|e+s+t+)?p+[o0]+[wh]*n+[ye3]*'),$sanitized_name))
				return 'opinionbased';
		}, [
			Input::IN_RANGE => [3,30],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Tag name cannot be empty',
				Input::ERROR_RANGE => 'Tag name must be between @min and @max characters',
				'dash' => 'Tag name cannot start with a dash',
				'opinionbased' => 'Highly opinion-based tags are not allowed',
			]
		]))->out());
		CoreUtils::checkStringValidity($name,'Tag name',INVERSE_TAG_NAME_PATTERN);
		return $name;
	}

	public static $CM_DIR = [
		CM_FACING_LEFT => 'Head-tail',
		CM_FACING_RIGHT => 'Tail-head',
	];

	public static function getElasticUnavailableNotice(bool $EQG):string {
		return CoreUtils::notice('warn','<span class="typcn typcn-warning"></span> <strong>ElasticSearch server is down!</strong> Please <a class="send-feedback">let us know</a>, and in the meantime, use the <a class="btn link typcn typcn-th-menu" href="/cg'.($EQG?'/eqg':'').'/full">Full List</a> to find appearances faster. Sorry for the inconvenience.',true);
	}

	const ELASTIC_BASE = [
		'index' => 'appearances',
	];

	/**
	 * Performs an ElasticSearch search operation
	 *
	 * @param array       $body
	 * @param $Pagination $Pagination
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
	 *
	 * @return Color[][]
	 */
	public static function getColorsForEach($Groups){
		if (empty($Groups)){
			return null;
		}

		$GroupIDs = [];
		foreach ($Groups as $g){
			$GroupIDs[] = $g->id;
		}

		$colors = Color::find('all', [
			'conditions' => [
				'group_id IN (?)',
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
	 * @param Color[] $colors
	 *
	 * @return string|null
	 */
	public static function stringifyColors(?array $colors):?string {
		if (empty($colors))
			return null;

		$return = [];
		foreach ($colors as $c)
			$return[] = "{$c->hex} {$c->label}";

		return implode("\n", $return);
	}

	/**
	 * @param \App\Models\ColorGroup[] $cgs
	 *
	 * @return string
	 */
	public static function stringifyColorGroups($cgs): string{
		if (empty($cgs)){
			return '';
		}

		$return = [];
		foreach ($cgs as $i => $c){
			$return[] = $c['label'];
		}

		return implode("\n", $return);
	}
}
