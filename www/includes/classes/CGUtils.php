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
		static function TypeaheadRespond($str){
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
						$HTML .= "<a href='/cg/v/{$p['id']}'>{$p['label']}</a>, ";
					}
					$HTML = rtrim($HTML, ', ');
				}
				else {
					$Sorted = \CG\Appearances::Sort($Appearances);
					foreach (CGUtils::$GroupTagIDs_Assoc as $Category => $CategoryName){
						if (empty($Sorted[$Category]))
							continue;

						$HTML .= "<section><h2>$CategoryName</h2><div>";
						foreach ($Sorted[$Category] as $p)
							$HTML .= "<a href='/cg/v/{$p['id']}'>{$p['label']}</a>, ";
						$HTML = rtrim($HTML, ', ')."</div></section>";
					}
				}
			}
			return $HTML.($wrap?"</div>":'');
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
			if (strlen($tmp) < 1) CoreUtils::Respond('File upload failed; Reason unknown');

			list($width, $height) = Image::CheckType($tmp, $allowedMimeTypes);
			CoreUtils::CreateUploadFolder($path);

			if (!move_uploaded_file($tmp, $path)){
				@unlink($tmp);
				CoreUtils::Respond('File upload failed; Writing image file was unsuccessful');
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
			if (empty($_POST['image_url']))
				CoreUtils::Respond("Please provide an image URL");

			try {
				$Image = new ImageProvider($_POST['image_url']);
			}
			catch (Exception $e){ CoreUtils::Respond($e->getMessage()); }

			if ($Image->fullsize === false)
				CoreUtils::Respond('Image could not be retrieved from external provider');

			$remoteFile = @file_get_contents($Image->fullsize);
			if (empty($remoteFile))
				CoreUtils::Respond('Remote file could not be found');
			if (!file_put_contents($path, $remoteFile))
				CoreUtils::Respond('Writing local image file was unsuccessful');

			list($width, $height) = Image::CheckType($path, $allowedMimeTypes);
			Image::CheckSize($path, $width, $height, $minwidth, $minheight);
		}

		/**
		 * Checks and shortens episode tags
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
				return 's'.intval($_match[1], 10).'e'.intval($_match[2], 10).(!empty($_match[3]) ? '-'.intval($_match[3], 10) : '');
			}
			else return false;
		}

		// Renders HTML of the list of changes
		static function GetChangesHTML($Changes, $wrap = true, $showAppearance = false){
			$seeInitiator = Permission::Sufficient('inspector');
			$PonyCache = array();
			$HTML = $wrap ? '<ul id="changes">' : '';
			foreach ($Changes as $c){
				$initiator = $appearance = '';
				if ($seeInitiator)
					$initiator = " by ".User::GetProfileLink(User::Get($c['initiator']));
				if ($showAppearance){
					global $CGDb, $color;

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

			$tokens = array_map('trim',explode(',',strtolower($q)));
			if (count($tokens) > 6)
				throw new Exception('You may only search for up to 6 tags/labels');
			$SearchTagIDs = array();
			$SearchLabelLIKEs = array();

			foreach ($tokens as $token){
				// Search for a tag
				if (regex_match($TAG_NAME_REGEX, $token)){
					$err = CoreUtils::CheckStringValidity($token, "Tag name", INVERSE_TAG_NAME_PATTERN, true);
					if (is_string($err))
						throw new Exception($err);
					$Tag = \CG\Tags::GetActual($token, 'name');
					if (empty($Tag))
						throw new Exception('Tag (<code>'.htmlspecialchars($token).'</code>) does not exist');

					$SearchTagIDs[] = $Tag['tid'];
				}
				// Search for a lebel
				else {
					$err = CoreUtils::CheckStringValidity($token, "Name wildcard", INVERSE_PRINTABLE_ASCII_REGEX, true);
					if (is_string($err))
						throw new Exception($err);

					$like = CoreUtils::EscapeLikeValue(regex_replace(new RegExp('\*+'),'*',$token));
					$like = str_replace('*','%',$like);
					$like = str_replace('?','_',$like);
					$SearchLabelLIKEs[] = $like;
				}
			}

			return array(
				'tid' => $SearchTagIDs,
				'label' => $SearchLabelLIKEs,
			);
		}

		/**
		 * Deletes a rendered image (effectively forcing its re-generation on next visit)
		 *
		 * @param int  $AppearanceID
		 * @param bool $cm_dir_only  Only clear out CM direction preview
		 *
		 * @return bool
		 */
		static function ClearRenderedImage($AppearanceID, $cm_dir_only = false){
			$RenderedPath = APPATH."img/cg_render/$AppearanceID";
			$success = array();
			if ($cm_dir_only !== CM_DIR_ONLY && file_exists("$RenderedPath.png"))
				$success[] = unlink("$RenderedPath.png");
			if (file_exists("$RenderedPath.svg"))
				$success[] = unlink("$RenderedPath.svg");

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

			$OutputPath = APPATH."img/cg_render/{$Appearance['id']}.png";
			$FileRelPath = "$CGPath/v/{$Appearance['id']}.png";
			if (file_exists($OutputPath))
				Image::OutputPNG(null,$OutputPath,$FileRelPath);

			$SpriteRelPath = "img/cg/{$Appearance['id']}.png";

			$OutWidth = 0;
			$OutHeight = 0;
			$SpriteWidth = $SpriteHeight = 0;
			$SpriteRightMargin = 10;
			$ColorSquareSize = 25;
			$FontFile = APPATH.'font/Celestia Medium Redux.ttf';
			if (!file_exists($FontFile))
				throw new Exception('Font file missing');
			$Name = $Appearance['label'];
			$NameVerticalMargin = 5;
			$NameFontSize = 22;
			$TextMargin = 10;

			// Detect if sprite exists and adjust image size & define starting positions
			$SpritePath = APPATH.$SpriteRelPath;
			$SpriteExists = file_exists($SpritePath);
			if ($SpriteExists){
				$SpriteSize = getimagesize($SpritePath);
				$Sprite = Image::PreserveAlpha(imagecreatefrompng($SpritePath));
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
			$ColorGroups = \CG\ColorGroups::Get($Appearance['id']);
			$CGCount = count($ColorGroups);
			$CGFontSize = $NameFontSize/1.5;
			$CGVerticalMargin = $NameVerticalMargin*1.5;
			$GroupLabelBox = Image::SaneGetTTFBox($CGFontSize, $FontFile, 'AGIJKFagijkf');
			$CGsHeight = $CGCount*($GroupLabelBox['height'] + ($CGVerticalMargin*2) + $ColorSquareSize);

			// Get export time & size
			$ExportTS = "Image last updated: ".Time::Format(time(), FORMAT_FULL);
			$ExportFontSize = $CGFontSize/1.5;
			$ExportBox = Image::SaneGetTTFBox($ExportFontSize, $FontFile, $ExportTS);

			// Check how long & tall appearance name is, and set image width
			$NameBox = Image::SaneGetTTFBox($NameFontSize, $FontFile, $Name);
			$OutWidth = $origin['x'] + max($NameBox['width'], $ExportBox['width']) + $TextMargin;

			// Set image height
			$OutHeight = $origin['y'] + (($NameVerticalMargin*3) + $NameBox['height'] + $ExportBox['height']) + $CGsHeight;

			// Create base image
			$BaseImage = Image::CreateTransparent($OutWidth, $OutHeight);
			$BLACK = imagecolorallocate($BaseImage, 0, 0, 0);

			// If sprite exists, output it on base image
			if ($SpriteExists)
				Image::CopyExact($BaseImage, $Sprite, 0, 0, $SpriteWidth, $SpriteHeight);

			// Output appearance name
			$origin['y'] += $NameVerticalMargin;
			Image::Write($BaseImage, $Name, $origin['x'], $NameFontSize, $BLACK, $origin, $FontFile);
			$origin['y'] += $NameVerticalMargin;

			// Output generation time
			Image::Write($BaseImage, $ExportTS, $origin['x'], $ExportFontSize, $BLACK, $origin, $FontFile);
			$origin['y'] += $NameVerticalMargin;

			if (!empty($ColorGroups))
				foreach ($ColorGroups as $cg){
					Image::Write($BaseImage, $cg['label'], $origin['x'], $CGFontSize , $BLACK, $origin, $FontFile);
					$origin['y'] += $CGVerticalMargin;

					$Colors = \CG\Colors::Get($cg['groupid']);
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
								$NewBaseImage = Image::CreateTransparent($OutWidth, $OutHeight + $SizeIncrease);
								Image::CopyExact($NewBaseImage, $BaseImage, 0, 0, $OutWidth, $OutHeight);
								imagedestroy($BaseImage);
								$BaseImage = $NewBaseImage;
								$OutHeight += $SizeIncrease;
							}

							Image::DrawSquare($BaseImage, $x, $origin['y'], $ColorSquareSize, $c['hex'], $BLACK);
							$part++;
						}

						$origin['y'] += $ColorSquareSize + $CGVerticalMargin;
					}
				};

			$FinalBase = Image::CreateWhiteBG($OutWidth, $OutHeight);
			Image::DrawSquare($FinalBase, 0, 0, array($OutWidth, $OutHeight), null, $BLACK);
			Image::CopyExact($FinalBase, $BaseImage, 0, 0, $OutWidth, $OutHeight);

			if (!CoreUtils::CreateUploadFolder($OutputPath))
				CoreUtils::Respond('Failed to create render directory');
			Image::OutputPNG($FinalBase, $OutputPath, $FileRelPath);
		}

		// Generate CM preview image
		static function RenderCMDirectionSVG($AppearanceID, $dir){
			global $CGDb, $CGPath;

			$OutputPath = APPATH."img/cg_render/$AppearanceID.svg";
			$FileRelPath = "$CGPath/v/$AppearanceID.svg";
			if (file_exists($OutputPath))
				Image::OutputSVG(null,$OutputPath,$FileRelPath);

			if (is_null($dir))
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
				$label = $row['cglabel'].' '.regex_replace(new RegExp('^(?:(?:Main|First|Normal)\s)?(.+?)(?:\s\d+)?(?:/.*)?$'),'$1', $row['label']);
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

			Image::OutputSVG($img,$OutputPath,$FileRelPath);
		}
	}