<?php

	class CGUtils {
		static $GroupTagIDs_Assoc = array(
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

		/**
		 * Response creator for typeahead.js
		 *
		 * @param string $str
		 */
		static function AutocompleteRespond($str){
			header('Content-Type: application/json');
			if (is_array($str))
				$str = JSON::Encode($str);
			die($str);
		}

		/**
		 * Returns HTML for the full list
		 *
		 * @param array $Appearances
		 * @param bool  $GuideOrder
		 * @param bool  $wrap
		 *
		 * @return string
		 */
		static function GetFullListHTML($Appearances, $GuideOrder, $wrap = WRAP){
			$HTML = $wrap ? "<div id='full-list'>" : '';
			if (!empty($Appearances)){
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
						self::_processFullListLink($p, $HTML);
					}
					$HTML = rtrim($HTML, ', ');
				}
				else {
					$Sorted = \CG\Appearances::Sort($Appearances);
					foreach (CGUtils::$GroupTagIDs_Assoc as $Category => $CategoryName){
						if (empty($Sorted[$Category]))
							continue;

						$HTML .= "<section><h2>$CategoryName</h2><div>";
						foreach ($Sorted[$Category] as $p){
							self::_processFullListLink($p, $HTML);
						}
						$HTML = rtrim($HTML, ', ')."</div></section>";
					}
				}
			}
			return $HTML.($wrap?"</div>":'');
		}

		static private function _processFullListLink($p, &$HTML){
			$sprite = '';
			$url = "/cg/v/{$p['id']}-".\CG\Appearances::GetSafeLabel($p);
			if (Permission::Sufficient('staff')){
				$SpriteURL = \CG\Appearances::GetSpriteURL($p['id']);
				if (!empty($SpriteURL)){
					$sprite = "<span class='typcn typcn-image' title='Has a sprite'></span>&nbsp;";
					$url .= "' class='color-green";
				}
			}
			$HTML .= "<a href='$url'>$sprite{$p['label']}</a>, ";
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
		static function ProcessUploadedImage($key,$path,$allowedMimeTypes,$minwidth,$minheight = null){
			if (!isset($minheight)) $minheight = $minwidth;
			if (!isset($_FILES[$key]))
				return self::GrabImage($path,$allowedMimeTypes,$minwidth,$minheight);
			$file = $_FILES[$key];
			$tmp = $file['tmp_name'];
			if (strlen($tmp) < 1) Response::Fail('File upload failed; Reason unknown');

			list($width, $height) = Image::CheckType($tmp, $allowedMimeTypes);
			CoreUtils::CreateUploadFolder($path);

			if (!move_uploaded_file($tmp, $path)){
				@unlink($tmp);
				Response::Fail('File upload failed; Writing image file was unsuccessful');
			}

			Image::CheckSize($path, $width, $height, $minwidth, $minheight);
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
		static function GrabImage($path,$allowedMimeTypes,$minwidth,$minheight){
			try {
				$Image = new ImageProvider(Posts::ValidateImageURL());
			}
			catch (Exception $e){ Response::Fail($e->getMessage()); }

			if ($Image->fullsize === false)
				Response::Fail('Image could not be retrieved from external provider');

			$remoteFile = @file_get_contents($Image->fullsize);
			if (empty($remoteFile))
				Response::Fail('Remote file could not be found');
			if (!file_put_contents($path, $remoteFile))
				Response::Fail('Writing local image file was unsuccessful');

			list($width, $height) = Image::CheckType($path, $allowedMimeTypes);
			Image::CheckSize($path, $width, $height, $minwidth, $minheight);
		}

		/**
		 * Checks and normalizes episode tag names
		 *
		 * @param string $tag
		 *
		 * @return string|false
		 */
		static function CheckEpisodeTagName($tag){
			global $EPISODE_ID_REGEX;

			$_match = array();
			if (regex_match($EPISODE_ID_REGEX,$tag,$_match)){
				$season = intval($_match[1], 10);
				if ($season == 0)
					return false;
				return 's'.CoreUtils::Pad(intval($_match[1], 10)).'e'.CoreUtils::Pad(intval($_match[2], 10)).(!empty($_match[3]) ? '-'.CoreUtils::Pad(intval($_match[3], 10)) : '');
			}
			else return false;
		}

		// Renders HTML of the list of changes
		static function GetChangesHTML($Changes, $wrap = true, $showAppearance = false){
			$seeInitiator = Permission::Sufficient('staff');
			$PonyCache = array();
			$HTML = $wrap ? '<ul id="changes">' : '';
			foreach ($Changes as $c){
				$initiator = $appearance = '';
				if ($seeInitiator)
					$initiator = " by ".User::GetProfileLink(User::Get($c['initiator']));
				if ($showAppearance){
					global $CGDb;

					$PonyID = $c['ponyid'];
					if (empty($PonyCache[$PonyID])){
						$PonyCache[$PonyID] = $CGDb->where('id', $PonyID)->getOne('appearances');
					}
					$Pony = $PonyCache[$PonyID];
					$appearance = "<a href='/cg/v/{$Pony['id']}'>{$Pony['label']}</a>: ";
				}
				$HTML .= "<li>$appearance{$c['reason']} - ".Time::Tag($c['timestamp'])."$initiator</li>";
			}
			return $HTML . ($wrap ? '</ul>' : '');
		}

		/**
		 * @param string $q Search query
		 *
		 * @return array
		 */
		static function ProcessSearch($q){
			global $CGDb, $TAG_NAME_REGEX;

			$tokens = explode(',',strtolower($q));
			if (count($tokens) > 6)
				throw new Exception('You may only search for up to 6 tags/labels');
			$SearchTagIDs = array();
			$OriginalTagIDs = array();
			$SearchLabelLIKEs = array();

			foreach ($tokens as $token){
				$token = CoreUtils::Trim($token);
				// Search for a tag
				if (regex_match($TAG_NAME_REGEX, $token)){
					$err = CoreUtils::CheckStringValidity($token, "Tag name", INVERSE_TAG_NAME_PATTERN, true);
					if (is_string($err))
						throw new Exception($err);
					$Tag = \CG\Tags::GetActual($token, 'name');
					if (empty($Tag))
						throw new Exception('Tag (<code>'.CoreUtils::EscapeHTML($token).'</code>) does not exist');
					
					if (!empty($Tag['Original']))
						$OriginalTagIDs[] = $Tag['Original']['tid']; 
					
					$SearchTagIDs[] = $Tag['tid'];
				}
				// Search for a lebel
				else {
					$err = CoreUtils::CheckStringValidity($token, "Name wildcard", INVERSE_PRINTABLE_ASCII_PATTERN, true);
					if (is_string($err))
						throw new Exception($err);

					$like = CoreUtils::EscapeLikeValue(regex_replace(new RegExp('\*+'),'*',$token));
					$like = str_replace('*','%',$like);
					$like = str_replace('?','_',$like);
					$SearchLabelLIKEs[] = $like;
				}
			}

			return array(
				'orig_tid' => $OriginalTagIDs,
				'tid' => $SearchTagIDs,
				'label' => $SearchLabelLIKEs,
			);
		}

		const
			CLEAR_PALETTE = 'palette.png',
			CLEAR_CMDIR = 'cmdir.svg',
			CLEAR_SPRITE = 'sprite.png',
			CLEAR_SPRITE_MAP = 'linedata.json.gz';

		/**
		 * Deletes rendered images of an appearance (forcing its re-generation)
		 *
		 * @param int   $AppearanceID
		 * @param array $which
		 *
		 * @return bool
		 */
		static function ClearRenderedImages(int $AppearanceID, array $which = array(self::CLEAR_PALETTE,self::CLEAR_CMDIR,self::CLEAR_SPRITE,self::CLEAR_SPRITE_MAP)):bool {
			$RenderedPath = APPATH."img/cg_render/$AppearanceID";
			$success = array();
			foreach ($which as $suffix){
				if (file_exists("$RenderedPath-$suffix"))
					$success[] = unlink("$RenderedPath-$suffix");
			}
			return !in_array(false, $success);
		}


		/**
		 * Render appearance PNG image
		 *
		 * @param array $Appearance
		 *
		 * @throws Exception
		 */
		static function RenderAppearancePNG($Appearance){
			global $CGPath;

			$OutputPath = APPATH."img/cg_render/{$Appearance['id']}-palette.png";
			$FileRelPath = "$CGPath/v/{$Appearance['id']}.png";
			if (file_exists($OutputPath))
				Image::OutputPNG(null,$OutputPath,$FileRelPath);

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
				throw new Exception('Font file missing');
			$Name = $Appearance['label'];
			$NameVerticalMargin = 5;
			$NameFontSize = 22;
			$TextMargin = 10;
			$ColorsOutputted = 0;
			$SplitTreshold = 12;
			$ColumnRightMargin = 20;

			// Detect if sprite exists and adjust image size & define starting positions
			$SpritePath = SPRITE_PATH."{$Appearance['id']}.png";
			$SpriteExists = file_exists($SpritePath);
			if ($SpriteExists){
				$SpriteSize = getimagesize($SpritePath);
				$Sprite = Image::PreserveAlpha(imagecreatefrompng($SpritePath));
				$SpriteHeight = $SpriteSize[HEIGHT];
				$SpriteWidth = $SpriteSize[WIDTH];
				$SpriteRealWidth = $SpriteWidth + $SpriteRightMargin;

				$OutWidth = $SpriteRealWidth;
				$OutHeight = $SpriteHeight;
			}
			else $SpriteRealWidth = 0;
			$origin = array(
				'x' => $SpriteExists ? $SpriteRealWidth : $TextMargin,
				'y' => 0,
			);

			// Get color groups & calculate the space they take up
			$ColorGroups = \CG\ColorGroups::Get($Appearance['id']);
			$CGCount = count($ColorGroups);
			$CGFontSize = round($NameFontSize/1.25);
			$CGVerticalMargin = $NameVerticalMargin;
			$GroupLabelBox = Image::SaneGetTTFBox($CGFontSize, $FontFile, 'ABCDEFGIJKLMOPQRSTUVWQYZabcdefghijklmnopqrstuvwxyz');
			$ColorNameBox = Image::SaneGetTTFBox($ColorNameFontSize, $PixelatedFontFile, 'AGIJKFagijkf');
			$CGsHeight = $CGCount*($GroupLabelBox['height'] + ($CGVerticalMargin*2) + $ColorCircleSize);

			// Get export time & size
			$ExportTS = "Generated at: ".Time::Format(time(), Time::FORMAT_FULL);
			$ExportFontSize = round($CGFontSize/1.5);
			$ExportBox = Image::SaneGetTTFBox($ExportFontSize, $FontFile, $ExportTS);

			// Check how long & tall appearance name is, and set image width
			$NameBox = Image::SaneGetTTFBox($NameFontSize, $FontFile, $Name);
			$OutWidth = $origin['x'] + max($NameBox['width'], $ExportBox['width']) + $TextMargin;

			// Set image height
			$OutHeight = max($origin['y'] + (($NameVerticalMargin*4) + $NameBox['height'] + $ExportBox['height'] + $CGsHeight), $OutHeight);

			// Create base image
			$BaseImage = Image::CreateTransparent($OutWidth, $OutHeight);
			$BLACK = imagecolorallocate($BaseImage, 0, 0, 0);

			// If sprite exists, output it on base image
			if ($SpriteExists)
				Image::CopyExact($BaseImage, $Sprite, 0, 0, $SpriteWidth, $SpriteHeight);

			// Output appearance name
			$origin['y'] += $NameVerticalMargin*2;
			Image::Write($BaseImage, $Name, $origin['x'], $NameFontSize, $BLACK, $origin, $FontFile);
			$origin['y'] += $NameVerticalMargin;

			// Output generation time
			Image::Write($BaseImage, $ExportTS, $origin['x'], $ExportFontSize, $BLACK, $origin, $FontFile);
			$origin['y'] += $NameVerticalMargin;

			if (!empty($ColorGroups)){
				$LargestX = 0;
				$LargestLabel = '';
				$AllColors = \CG\ColorGroups::GetColorsForEach($ColorGroups);
				foreach ($ColorGroups as $cg){
					$CGLabelBox = Image::SaneGetTTFBox($CGFontSize, $FontFile, $cg['label']);
					Image::CalcRedraw($OutWidth, $OutHeight, $CGLabelBox['width']+$TextMargin, $GroupLabelBox['height']+$NameVerticalMargin+$CGVerticalMargin, $BaseImage, $origin);
					Image::Write($BaseImage, $cg['label'], $origin['x'], $CGFontSize, $BLACK, $origin, $FontFile, $GroupLabelBox);
					$origin['y'] += $GroupLabelBox['height']+$CGVerticalMargin;

					if ($CGLabelBox['width'] > $LargestX){
						$LargestX = $CGLabelBox['width'];
						$LargestLabel = $cg['label'];
					}

					if (!empty($AllColors[$cg['groupid']]))
						foreach ($AllColors[$cg['groupid']] as $c){
							$ColorNameLeftOffset = $ColorCircleSize + $ColorCircleRMargin;
							$CNBox = Image::SaneGetTTFBox($ColorNameFontSize, $PixelatedFontFile, $c['label']);

							$WidthIncrease = $ColorNameLeftOffset + $CNBox['width'] + $TextMargin;
							$HeightIncrease = max($ColorCircleSize, $CNBox['height']) + $CGVerticalMargin;
							Image::CalcRedraw($OutWidth, $OutHeight, $WidthIncrease, $HeightIncrease, $BaseImage, $origin);

							Image::DrawCircle($BaseImage, $origin['x'], $origin['y'], $ColorCircleSize, $c['hex'], $BLACK);

							$yOffset = 2;
							Image::Write($BaseImage, $c['label'], $origin['x'] + $ColorNameLeftOffset, $ColorNameFontSize, $BLACK, $origin, $PixelatedFontFile, $ColorNameBox, $yOffset);
							$origin['y'] += $HeightIncrease;

							$ColorsOutputted++;

							$TotalWidth = $ColorNameLeftOffset+$CNBox['width'];
							if ($TotalWidth > $LargestX){
								$LargestX = $TotalWidth;
								$LargestLabel = $c['label'];
							}
						};

					if ($ColorsOutputted > $SplitTreshold){
						Image::CalcRedraw($OutWidth, $OutHeight, 0, $NameVerticalMargin, $BaseImage, $origin);
						$origin['y'] =
							($NameVerticalMargin * 4)
							+ Image::SaneGetTTFBox($NameFontSize, $FontFile, $Name)['height']
							+ Image::SaneGetTTFBox($ExportFontSize, $FontFile, $ExportTS)['height'];

						$origin['x'] += $LargestX+$ColumnRightMargin;
						$ColorsOutputted = 0;
						$LargestX = 0;
					}
					else $origin['y'] += $NameVerticalMargin;
				};
			}

			$FinalBase = Image::CreateWhiteBG($OutWidth, $OutHeight);
			Image::DrawSquare($FinalBase, 0, 0, array($OutWidth, $OutHeight), null, $BLACK);
			Image::CopyExact($FinalBase, $BaseImage, 0, 0, $OutWidth, $OutHeight);

			if (!CoreUtils::CreateUploadFolder($OutputPath))
				Response::Fail('Failed to create render directory');
			Image::OutputPNG($FinalBase, $OutputPath, $FileRelPath);
		}

		// Generate CM preview image
		static function RenderCMDirectionSVG($AppearanceID, $dir){
			global $CGDb, $CGPath;

			$OutputPath = APPATH."img/cg_render/$AppearanceID-cmdir.svg";
			$FileRelPath = "$CGPath/v/$AppearanceID.svg";
			if (file_exists($OutputPath))
				Image::OutputSVG(null,$OutputPath,$FileRelPath);

			if (!isset($dir))
				CoreUtils::NotFound();

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
				$label = regex_replace(new RegExp('^(Costume|Dress)$'),'Coat',$row['cglabel']).' '.regex_replace(new RegExp('^(?:(?:Main|First|Normal)\s)?(.+?)(?:\s\d+)?(?:/.*)?$'),'$1', $row['label']);
				if (isset($DefaultColorMapping[$label]) && !isset($ColorMapping[$label]))
					$ColorMapping[$label] = $row['hex'];
			}
			if (!isset($ColorMapping['Coat Shadow Outline']) && isset($ColorMapping['Coat Outline']))
				$ColorMapping['Coat Shadow Outline'] = $ColorMapping['Coat Outline'];
			if (!isset($ColorMapping['Coat Shadow Fill']) && isset($ColorMapping['Coat Fill']))
				$ColorMapping['Coat Shadow Fill'] = $ColorMapping['Coat Fill'];

			$img = file_get_contents(APPATH.'img/cm-direction-'.($dir===CM_DIR_HEAD_TO_TAIL?'ht':'th').'.svg');
			foreach ($DefaultColorMapping as $label => $defhex)
				$img = str_replace($label, $ColorMapping[$label] ?? $defhex, $img);

			Image::OutputSVG($img,$OutputPath,$FileRelPath);
		}

		static function Int2Hex(int $int){
			return '#'.strtoupper(CoreUtils::Pad(dechex($int), 6));
		}

		static function GetSpriteImageMap($AppearanceID){
			$MapPath = APPATH."img/cg_render/$AppearanceID-linedata.json.gz";
			if (!file_exists($MapPath))
				return null;
			return JSON::Decode(gzuncompress(file_get_contents($MapPath)));
		}

		static function RenderSpritePNG($AppearanceID, $output = true){
			global $CGPath, $Database;

			$OutputPath = APPATH."img/cg_render/{$AppearanceID}-sprite.png";
			$FileRelPath = "$CGPath/v/{$AppearanceID}s.png";
			if (file_exists($OutputPath))
				Image::OutputPNG(null,$OutputPath,$FileRelPath);

			$MapPath = APPATH."img/cg_render/$AppearanceID-linedata.json.gz";
			if (!file_exists($MapPath)){
				$PNGPath = SPRITE_PATH."$AppearanceID.png";
				if (!file_exists($PNGPath))
					CoreUtils::NotFound();

				list($PNGWidth, $PNGHeight) = getimagesize($PNGPath);
				$PNG = imagecreatefrompng($PNGPath);
				imagesavealpha($PNG, true);

				$allcolors = array();
				function coords($w, $h){
					for ($y = 0; $y < $h; $y++){
						for ($x = 0; $x < $w; $x++)
							yield array($x, $y);
					}
				}
				foreach (coords($PNGWidth,$PNGHeight) as $pos){
					list($x, $y) = $pos;
					$rgb = imagecolorat($PNG, $x, $y);
					$colors = imagecolorsforindex($PNG, $rgb);
					$hex = strtoupper('#'.CoreUtils::Pad(dechex($colors['red'])).CoreUtils::Pad(dechex($colors['green'])).CoreUtils::Pad(dechex($colors['blue'])));
					$opacity = floatval(number_format(1-($colors['alpha']/127), 2, '.', ''));
					if ($opacity == 0)
						continue;
					$allcolors[$hex][$opacity][] = array($x,$y);
				}

				$mapping = 0;

				$currLine = null;
				$lines = array();
				$lastx = -2;
				$lasty = -2;
				$_colorsAssoc = array();
				$colorno = 0;
				foreach ($allcolors as $hex => $opacities){
					if (!isset($_colorsAssoc[$hex])){
						$_colorsAssoc[$hex] = $colorno;
						$colorno++;
					}
					foreach ($opacities as $opacity => $coords){
						foreach ($coords as $pos){
							list($x, $y) = $pos;

							if ($x-1 != $lastx || $y != $lasty){
								if (isset($currLine))
									$lines[] = $currLine;
								$currLine = array(
									'x' => $x,
									'y' => $y,
									'width' => 1,
									'colorid' => $_colorsAssoc[$hex],
									'opacity' => $opacity,
								);
							}
							else $currLine['width']++;

							$lastx = $x;
							$lasty = $y;
						}
					}
				}
				if (isset($currLine))
					$lines[] = $currLine;

				$Output = array(
					'width' => $PNGWidth,
					'height' => $PNGHeight,
					'linedata' => array(),
					'colors' => array_flip($_colorsAssoc),
				);
				foreach ($lines as $line)
					$Output['linedata'][] = $line;

				$Map = $Output;
				file_put_contents($MapPath, gzcompress(JSON::Encode($Output), 9));
			}
			else if ($output)
				$Map = JSON::Decode(gzuncompress(file_get_contents($MapPath)));
			if (!$output)
				return;

			$SizeFactor = 2;
			$PNG = Image::CreateTransparent($Map['width']*$SizeFactor, $Map['height']*$SizeFactor);
			foreach ($Map['linedata'] as $line){
				$rgb = CoreUtils::Hex2Rgb($Map['colors'][$line['colorid']]);
				$color = imagecolorallocatealpha($PNG, $rgb[0], $rgb[1], $rgb[2], 127-(int)round($line['opacity']*127));
				Image::DrawSquare($PNG, $line['x']*$SizeFactor, $line['y']*$SizeFactor, array($line['width']*$SizeFactor, $SizeFactor), $color, null);
			}

			Image::OutputPNG($PNG, $OutputPath, $FileRelPath);
		}
		
		static function GetSwatchesAI($Appearance){
			$label = $Appearance['label'];
			$JSON = array('Exported at' => gmdate('Y-m-d H:i:s \G\M\T'));
			$JSON[$label] = array();
			
			$CGs = \CG\ColorGroups::Get($Appearance['id']);
			$Colors = \CG\ColorGroups::GetColorsForEach($CGs);
			foreach ($CGs as $cg){
				$JSON[$label][$cg['label']] = array();
				foreach ($Colors[$cg['groupid']] as $c)
					$JSON[$label][$cg['label']][$c['label']] = $c['hex'];
			}
			
			CoreUtils::DownloadFile(JSON::Encode($JSON), "$label.json");
		}
		static function GetSwatchesInkscape($Appearance){
			$label = $Appearance['label'];
			$exportts = gmdate('Y-m-d H:i:s \G\M\T');
			$File = <<<GPL
GIMP Palette
Name: $label
Columns: 6
#
# Exported at: $exportts
#

GPL;

			$CGs = \CG\ColorGroups::Get($Appearance['id']);
			$Colors = \CG\ColorGroups::GetColorsForEach($CGs);
			foreach ($CGs as $cg){
				foreach ($Colors[$cg['groupid']] as $c){
					$rgb = CoreUtils::Hex2Rgb($c['hex']);
					$File .= CoreUtils::Pad($rgb[0],3,' ').' '.CoreUtils::Pad($rgb[1],3,' ').' '.CoreUtils::Pad($rgb[2],3,' ').' '.$cg['label'].' | '.$c['label'].PHP_EOL;
				}
			}

			CoreUtils::DownloadFile(rtrim($File), "$label.gpl");
		}

		static function ValidateTagName($key){
			$name = strtolower((new Input($key,function($value, $range){
				if (Input::CheckStringLength($value,$range,$code))
					return $code;
				if ($value[0] === '-')
					return 'dash';
				$sanitized_name = regex_replace(new RegExp('[^a-z\d]'),'',$value);
				if (regex_match(new RegExp('^(b+[a4]+w*d+|g+[uo0]+d+|(?:b+[ae3]+|w+[o0u]+r+)[s5]+[t7]+)(e+r+|e+s+t+)?p+[o0]+[wh]*n+[ye3]*'),$sanitized_name))
					return 'opinionbased';
			},array(
				Input::IN_RANGE => [3,30],
				Input::CUSTOM_ERROR_MESSAGES => array(
					Input::ERROR_MISSING => 'Tag name cannot be empty',
					Input::ERROR_RANGE => 'Tag name must be between @min and @max characters',
					'dash' => 'Tag name cannot start with a dash',
					'opinionbased' => 'Highly opinion-based tags are not allowed',
				)
			)))->out());
			CoreUtils::CheckStringValidity($name,'Tag name',INVERSE_TAG_NAME_PATTERN);
			return $name;
		}
	}
