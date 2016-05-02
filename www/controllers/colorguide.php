<?php

	$SpriteRelPath = '/img/cg/';
	$SpritePath = APPATH.substr($SpriteRelPath,1);

	if (POST_REQUEST || (isset($_GET['s']) && $data === "gettags")){
		if (!Permission::Sufficient('inspector')) CoreUtils::Respond();
		if (POST_REQUEST) CSRFProtection::Protect();

		$EQG = isset($_REQUEST['eqg']) ? 1 : 0;
		$AppearancePage = isset($_POST['APPEARANCE_PAGE']);

		switch ($data){
			case 'gettags':
				if (isset($_POST['not']) && is_numeric($_POST['not']))
					$not_tid = intval($_POST['not'], 10);
				if (!empty($_POST['action']) && $_POST['action'] === 'synon'){
					$Tag = $CGDb->where('tid',$not_tid)->where('"synonym_of" IS NOT NULL')->getOne('tags');
					if (!empty($Tag)){
						$Syn = \CG\Tags::GetSynonymOf($Tag,'name');
						CoreUtils::Respond("This tag is already a synonym of <strong>{$Syn['name']}</strong>.<br>Would you like to remove the synonym?",0,array('undo' => true));
					}
				}

				$viaTypeahead = !empty($_GET['s']);
				$limit = null;
				$cols = "tid, name, type";
				if ($viaTypeahead){
					if (!regex_match($TAG_NAME_REGEX, $_GET['s']))
						CGUtils::TypeaheadRespond('[]');

					$query = trim(strtolower($_GET['s']));
					$TagCheck = CGUtils::CheckEpisodeTagName($query);
					if ($TagCheck !== false)
						$query = $TagCheck;
					$CGDb->where('name',"%$query%",'LIKE');
					$limit = 5;
					$cols = "tid, name, CONCAT('typ-', type) as type";
				}
				else $CGDb->orderBy('type','ASC')->where('"synonym_of" IS NULL');

				if (isset($_POST['not']) && is_numeric($_POST['not']))
					$CGDb->where('tid',$not_tid,'!=');
				$Tags = $CGDb->orderBy('name','ASC')->get('tags',$limit,"$cols, uses, synonym_of");
				if ($viaTypeahead)
					foreach ($Tags as $i => $t){
						if (empty($t['synonym_of']))
							continue;
						$Syn = $CGDb->where('tid', $t['synonym_of'])->getOne('tags','name');
						if (!empty($Syn))
							$Tags[$i]['synonym_target'] = $Syn['name'];
					};

				CGUtils::TypeaheadRespond(empty($Tags) ? '[]' : $Tags);
			break;
			case 'full':
				if (!isset($_REQUEST['reorder']))
					CoreUtils::NotFound();

				if (!Permission::Sufficient('inspector'))
					CoreUtils::Respond();
				if (empty($_POST['list']))
					CoreUtils::Respond('The list of IDs is missing');

				$list = trim($_POST['list']);
				if (!regex_match(new RegExp('^\d+(?:,\d+)+$'), $list))
					CoreUtils::Respond('The list of IDs is not formatted properly');

				\CG\Appearances::Reorder($list);

				CoreUtils::Respond(array('html' => CGUtils::GetFullListHTML(\CG\Appearances::Get($EQG,null,'id,label'), true, NOWRAP)));
			break;
			case "export":
				if (!Permission::Sufficient('developer'))
					CoreUtils::NotFound();
				$JSON = array(
					'Appearances' => array(),
					'Tags' => array(),
				);

				$Tags = $CGDb->orderBy('tid','ASC')->get('tags');
				if (!empty($Tags)) foreach ($Tags as $t){
					$JSON['Tags'][$t['tid']] = $t;
				}

				$Appearances = \CG\Appearances::Get(null);
				if (!empty($Appearances)) foreach ($Appearances as $p){
					$AppendAppearance = $p;

					$AppendAppearance['notes'] = regex_replace(new RegExp('(\r\n|\r|\n)'),"\n",$AppendAppearance['notes']);

					$AppendAppearance['ColorGroups'] = array();
					$ColorGroups = \CG\ColorGroups::Get($p['id']);
					if (!empty($ColorGroups)) foreach ($ColorGroups as $cg){
						$AppendColorGroup = $cg;
						unset($AppendColorGroup['ponyid']);

						$AppendColorGroup['Colors'] = array();
						$Colors = \CG\Colors::Get($cg['groupid']);
						if (!empty($Colors)) foreach ($Colors as $c){
							unset($c['groupid']);
							$AppendColorGroup['Colors'][] = $c;
						}

						$AppendAppearance['ColorGroups'][$cg['groupid']] = $AppendColorGroup;
					}

					$AppendAppearance['TagIDs'] = array();
					$TagIDs = \CG\Tags::Get($p['id'],null,null,true);
					if (!empty($TagIDs))
						foreach ($TagIDs as $t)
							$AppendAppearance['TagIDs'][] = $t['tid'];

					$JSON['Appearances'][$p['id']] = $AppendAppearance;
				}

				$data = JSON::Encode($JSON, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
				$data = preg_replace_callback('/^\s+/m', function($match){
					return str_pad('',strlen($match[0])/4,"\t", STR_PAD_LEFT);
				}, $data);

				header('Content-Type: application/octet-stream');
				header('Content-Transfer-Encoding: Binary');
				header('Content-disposition: attachment; filename="mlpvc-colorguide.json"');
				die($data);
			break;
		}

		$_match = array();
		if (regex_match(new RegExp('^(rename|delete|make|(?:[gs]et|del)(?:sprite|cgs)?|tag|untag|clearrendercache|applytemplate)(?:/(\d+))?$'), $data, $_match)){
			$action = $_match[1];
			$creating = $action === 'make';

			if (!$creating){
				$AppearanceID = intval($_match[2], 10);
				if (strlen($_match[2]) === 0)
					CoreUtils::Respond('Missing appearance ID');
				$Appearance = $CGDb->where('id', $AppearanceID)->where('ishuman', $EQG)->getOne('appearances');
				if (empty($Appearance))
					CoreUtils::Respond("The specified appearance does not exist");
			}
			else $Appearance = array('id' => null);

			switch ($action){
				case "get":
					CoreUtils::Respond(array(
						'label' => $Appearance['label'],
						'notes' => $Appearance['notes'],
						'cm_favme' => !empty($Appearance['cm_favme']) ? "http://fav.me/{$Appearance['cm_favme']}" : null,
						'cm_preview' => $Appearance['cm_preview'],
						'cm_dir' => isset($Appearance['cm_dir'])
							? ($Appearance['cm_dir'] === CM_DIR_HEAD_TO_TAIL ? 'ht' : 'th')
							: null
					));
				break;
				case "set":
				case "make":
					$data = array(
						'ishuman' => $EQG,
					    'cm_favme' => null,
					);

					if (empty($_POST['label']))
						CoreUtils::Respond('Label is missing');
					$label = trim($_POST['label']);
					$ll = strlen($label);
					CoreUtils::CheckStringValidity($label, "Appearance name", INVERSE_PRINTABLE_ASCII_REGEX);
					if ($ll < 4 || $ll > 70)
						CoreUtils::Respond('Appearance name must be beetween 4 and 70 characters long');
					if ($creating && $CGDb->where('label', $label)->has('appearances'))
						CoreUtils::Respond('An appearance already esists with this name');
					$data['label'] = $label;

					if (!empty($_POST['notes'])){
						$notes = trim($_POST['notes']);
						CoreUtils::CheckStringValidity($label, "Appearance notes", INVERSE_PRINTABLE_ASCII_REGEX);
						if ($Appearance['id'] === 0)
							$notes = trim(CoreUtils::SanitizeHtml($notes));
						if (strlen($notes) > 1000 && ($creating || $Appearance['id'] !== 0))
							CoreUtils::Respond('Appearance notes cannot be longer than 1000 characters');
						if ($creating || $notes !== $Appearance['notes'])
							$data['notes'] = $notes;
					}
					else $data['notes'] = '';

					if (!empty($_POST['cm_favme'])){
						$cm_favme = trim($_POST['cm_favme']);
						try {
							$Image = new ImageProvider($cm_favme, array('fav.me', 'dA'));
							$data['cm_favme'] = $Image->id;
						}
						catch (MismatchedProviderException $e){
							CoreUtils::Respond('The vector must be on DeviantArt, '.$e->getActualProvider().' links are not allowed');
						}
						catch (Exception $e){ CoreUtils::Respond("Cutie Mark link issue: ".$e->getMessage()); }

						if (empty($_POST['cm_dir']))
							CoreUtils::Respond('Cutie mark orientation must be set if a link is provided');
						if ($_POST['cm_dir'] !== 'th' && $_POST['cm_dir'] !== 'ht')
							CoreUtils::Respond('Invalid cutie mark orientation');
						$cm_dir = $_POST['cm_dir'] === 'ht' ? CM_DIR_HEAD_TO_TAIL : CM_DIR_TAIL_TO_HEAD;
						if ($creating || $Appearance['cm_dir'] !== $cm_dir)
							$data['cm_dir'] = $cm_dir;

						$cm_preview = trim($_POST['cm_preview']);
						if (empty($cm_preview))
							$data['cm_preview'] = null;
						else if ($creating || $cm_preview !== $Appearance['cm_preview']){
							try {
								$Image = new ImageProvider($cm_preview);
								$data['cm_preview'] = $Image->preview;
							}
							catch (Exception $e){ CoreUtils::Respond("Cutie Mark preview issue: ".$e->getMessage()); }
						}
					}
					else {
						$data['cm_dir'] = null;
						$data['cm_preview'] = null;
					}

					$query = $creating
						? $CGDb->insert('appearances', $data, 'id')
						: $CGDb->where('id', $Appearance['id'])->update('appearances', $data);
					if (!$query)
						CoreUtils::Respond(ERR_DB_FAIL);

					if ($creating){
						$data['id'] = $query;
						$response = array(
							'message' => 'Appearance added successfully',
							'id' => $query,
						);
						if (isset($_POST['template'])){
							try {
								\CG\Appearances::ApplyTemplate($query, $EQG);
							}
							catch (Exception $e){
								$response['message'] .= ", but applying the template failed";
								$response['info'] = "The common color groups could not be added.<br>Reason: ".$e->getMessage();
								CoreUtils::Respond($response, 1);
							}
						}
						CoreUtils::Respond($response);
					}
					else {
						CGUtils::ClearRenderedImage($Appearance['id']);
						if ($AppearancePage)
							CoreUtils::Respond(true);
					}

					$Appearance = array_merge($Appearance, $data);
					CoreUtils::Respond(array(
						'label' => $Appearance['label'],
						'notes' => \CG\Appearances::GetNotesHTML($Appearance, NOWRAP),
					));
				break;
				case "delete":
					if ($Appearance['id'] === 0)
						CoreUtils::Respond('This appearance cannot be deleted');

					if (!$CGDb->where('id', $Appearance['id'])->delete('appearances'))
						CoreUtils::Respond(ERR_DB_FAIL);

					$fpath = APPATH."img/cg/{$Appearance['id']}.png";
					if (file_exists($fpath))
						unlink($fpath);

					CGUtils::ClearRenderedImage($Appearance['id']);

					CoreUtils::Respond('Appearance removed', 1);
				break;
				case "getcgs":
					$cgs = \CG\ColorGroups::Get($Appearance['id'],'groupid, label');
					if (empty($cgs))
						CoreUtils::Respond('This appearance does not have any color groups');
					CoreUtils::Respond(array('cgs' => $cgs));
				break;
				case "setcgs":
					if (empty($_POST['cgs']))
						CoreUtils::Respond("$Color group order data missing");

					$groups = array_unique(array_map('intval',explode(',',$_POST['cgs'])));
					foreach ($groups as $part => $GroupID){
						if (!$CGDb->where('groupid', $GroupID)->has('colorgroups'))
							CoreUtils::Respond("There's no group with the ID of  $GroupID");

						$CGDb->where('groupid', $GroupID)->update('colorgroups',array('order' => $part));
					}

					CGUtils::ClearRenderedImage($Appearance['id']);

					CoreUtils::Respond(array('cgs' => \CG\Appearances::GetColorsHTML($Appearance['id'], NOWRAP, !$AppearancePage, $AppearancePage)));
				break;
				case "delsprite":
				case "getsprite":
				case "setsprite":
					$fname = $Appearance['id'].'.png';
					$finalpath = $SpritePath.$fname;

					switch ($action){
						case "setsprite":
							CGUtils::ProcessUploadedImage('sprite', $finalpath, array('image/png'), 100);
							CGUtils::ClearRenderedImage($Appearance['id']);
						break;
						case "delsprite":
							if (!file_exists($finalpath))
								CoreUtils::Respond('No sprite file found');

							if (!unlink($finalpath))
								CoreUtils::Respond('File could not be deleted');

							CoreUtils::Respond(array('sprite' => \CG\Appearances::GetSpriteURL($Appearance['id'], DEFAULT_SPRITE)));
						break;
					}

					CoreUtils::Respond(array("path" => "$SpriteRelPath$fname?".filemtime($finalpath)));
				break;
				case "clearrendercache":
					if (!CGUtils::ClearRenderedImage($Appearance['id']))
						CoreUtils::Respond('Cache could not be cleared');

					CoreUtils::Respond('Cached image removed, the image will be re-generated on the next request', 1);
				break;
				case "tag":
				case "untag":
					if ($Appearance['id'] === 0)
						CoreUtils::Respond("This appearance cannot be tagged");

					switch ($action){
						case "tag":
							if (empty($_POST['tag_name']))
								CoreUtils::Respond('Tag name is not specified');
							$tag_name = strtolower(trim($_POST['tag_name']));
							if (!regex_match($TAG_NAME_REGEX,$tag_name))
								CoreUtils::Respond('Invalid tag name');

							$TagCheck = CGUtils::CheckEpisodeTagName($tag_name);
							if ($TagCheck !== false)
								$tag_name = $TagCheck;

							$Tag = $CGDb->where('name',$tag_name)->getOne('tags');
							if (empty($Tag))
								CoreUtils::Respond("The tag $tag_name does not exist.<br>Would you like to create it?",0,array(
									'cancreate' => $tag_name,
									'typehint' => $TagCheck !== false ? 'ep' : null,
								));

							if ($CGDb->where('ponyid', $Appearance['id'])->where('tid', $Tag['tid'])->has('tagged'))
								CoreUtils::Respond('This appearance already has this tag');

							if (!$CGDb->insert('tagged',array(
								'ponyid' => $Appearance['id'],
								'tid' => $Tag['tid'],
							))) CoreUtils::Respond(ERR_DB_FAIL);
						break;
						case "untag":
							if (!isset($_POST['tag']) || !is_numeric($_POST['tag']))
								CoreUtils::Respond('Tag ID is not specified');
							$Tag = $CGDb->where('tid',$_POST['tag'])->getOne('tags');
							if (empty($Tag))
								CoreUtils::Respond('This tag does not exist');
							if (!empty($Tag['synonym_of'])){
								$Syn = \CG\Tags::GetSynonymOf($Tag,'name');
								CoreUtils::Respond('Synonym tags cannot be removed from appearances directly. '.
								        "If you want to remove this tag you must remove <strong>{$Syn['name']}</strong> or the synonymization.");
							}

							if ($CGDb->where('ponyid', $Appearance['id'])->where('tid', $Tag['tid'])->has('tagged')){
								if (!$CGDb->where('ponyid', $Appearance['id'])->where('tid', $Tag['tid'])->delete('tagged'))
									CoreUtils::Respond(ERR_DB_FAIL);
							}
						break;
					}

					\CG\Tags::UpdateUses($Tag['tid']);
					if (isset(CGUtils::$GroupTagIDs_Assoc[$Tag['tid']]))
						\CG\Appearances::GetSortReorder($EQG);

					$response = array('tags' => \CG\Appearances::GetTagsHTML($Appearance['id'], NOWRAP));
					if ($AppearancePage && $Tag['type'] === 'ep'){
						$response['needupdate'] = true;
						$response['eps'] = \CG\Appearances::GetRelatedEpisodesHTML($Appearance['id'], NOWRAP);
					}
					CoreUtils::Respond($response);
				break;
				case "applytemplate":
					try {
						\CG\Appearances::ApplyTemplate($Appearance['id'], $EQG);
					}
					catch (Exception $e){
						CoreUtils::Respond("Applying the template failed. Reason: ".$e->getMessage());
					}

					CoreUtils::Respond(array('cgs' => \CG\Appearances::GetColorsHTML($Appearance['id'], NOWRAP, !$AppearancePage, $AppearancePage)));
				break;
				default: CoreUtils::StatusCode(404, AND_DIE);
			}
		}
		else if (regex_match(new RegExp('^([gs]et|make|del|merge|recount|(?:un)?synon)tag(?:/(\d+))?$'), $data, $_match)){
			$action = $_match[1];

			if ($action === 'recount'){
				if (empty($_POST['tagids']))
					CoreUtils::Respond('Missing list of tags to update');

				$tagIDs = array_map('intval', explode(',',trim($_POST['tagids'])));
				$counts = array();
				$updates = 0;
				foreach ($tagIDs as $tid){
					if (\CG\Tags::GetActual($tid,'tid',RETURN_AS_BOOL)){
						$result = \CG\Tags::UpdateUses($tid, true);
						if ($result['status'])
							$updates++;
						$counts[$tid] = $result['count'];
					}
				}

				CoreUtils::Respond(
					(
						!$updates
						? 'There was no change in the tag usage counts'
						: "$updates tag".($updates!==1?"s'":"'s").' use count'.($updates!==1?'s were':' was').' updated'
					),
					1,
					array('counts' => $counts)
				);
			}

			$getting = $action === 'get';
			$deleting = $action === 'del';
			$new = $action === 'make';
			$merging = $action === 'merge';
			$synoning = $action === 'synon';
			$unsynoning = $action === 'unsynon';

			if (!$new){
				if (!isset($_match[2]))
					CoreUtils::Respond('Missing tag ID');
				$TagID = intval($_match[2], 10);
				$Tag = $CGDb->where('tid', $TagID)->getOne('tags',isset($query) ? 'tid, name, type':'*');
				if (empty($Tag))
					CoreUtils::Respond("This tag does not exist");

				if ($getting) CoreUtils::Respond($Tag);

				if ($deleting){
					if (!isset($_POST['sanitycheck'])){
						$tid = !empty($Tag['synonym_of']) ? $Tag['synonym_of'] : $Tag['tid'];
						$Uses = $CGDb->where('tid',$tid)->count('tagged');
						if ($Uses > 0)
							CoreUtils::Respond('<p>This tag is currently used on '.CoreUtils::MakePlural('appearance',$Uses,PREPEND_NUMBER).'</p><p>Deleting will <strong class="color-red">permanently remove</strong> the tag from those appearances!</p><p>Are you <em class="color-red">REALLY</em> sure about this?</p>',0,array('confirm' => true));
					}

					if (!$CGDb->where('tid', $Tag['tid'])->delete('tags'))
						CoreUtils::Respond(ERR_DB_FAIL);

					if (isset(CGUtils::$GroupTagIDs_Assoc[$Tag['tid']]))
						\CG\Appearances::GetSortReorder($EQG);

					CoreUtils::Respond('Tag deleted successfully', 1, $AppearancePage && $Tag['type'] === 'ep' ? array(
						'needupdate' => true,
						'eps' => \CG\Appearances::GetRelatedEpisodesHTML($Appearance['id'], NOWRAP),
					) : null);
				}
			}
			$data = array();

			if ($merging || $synoning){
				if ($synoning && !empty($Tag['synonym_of']))
					CoreUtils::Respond('This tag is already synonymized with a different tag');

				if (empty($_POST['targetid']))
					CoreUtils::Respond('Missing target tag ID');
				$Target = $CGDb->where('tid', intval($_POST['targetid'], 10))->getOne('tags');
				if (empty($Target))
					CoreUtils::Respond('Target tag does not exist');
				if (!empty($Target['synonym_of']))
					CoreUtils::Respond('Synonym tags cannot be synonymization targets');

				$_TargetTagged = $CGDb->where('tid', $Target['tid'])->get('tagged',null,'ponyid');
				$TargetTagged = array();
				foreach ($_TargetTagged as $tg)
					$TargetTagged[] = $tg['ponyid'];

				$Tagged = $CGDb->where('tid', $Tag['tid'])->get('tagged',null,'ponyid');
				foreach ($Tagged as $tg){
					if (in_array($tg['ponyid'], $TargetTagged)) continue;

					if (!$CGDb->insert('tagged',array(
						'tid' => $Target['tid'],
						'ponyid' => $tg['ponyid']
					))) CoreUtils::Respond('Tag '.($merging?'merging':'synonimizing')." failed, please re-try.<br>Technical details: ponyid={$tg['ponyid']} tid={$Target['tid']}");
				}
				if ($merging)
					// No need to delete "tagged" table entries, constraints do it for us
					$CGDb->where('tid', $Tag['tid'])->delete('tags');
				else {
					$CGDb->where('tid', $Tag['tid'])->delete('tagged');
					$CGDb->where('tid', $Tag['tid'])->update('tags', array('synonym_of' => $Target['tid'], 'uses' => 0));
				}

				\CG\Tags::UpdateUses($Target['tid']);
				CoreUtils::Respond('Tags successfully '.($merging?'merged':'synonymized'), 1, $synoning ? array('target' => $Target) : null);
			}
			else if ($unsynoning){
				if (empty($Tag['synonym_of']))
					CoreUtils::Respond(true);

				$keep_tagged = isset($_POST['keep_tagged']);
				$uses = 0;
				if ($keep_tagged){
					$Target = $CGDb->where('tid', $Tag['synonym_of'])->getOne('tags','tid');
					if (!empty($Target)){
						$TargetTagged = $CGDb->where('tid', $Target['tid'])->get('tagged',null,'ponyid');
						foreach ($TargetTagged as $tg){
							if (!$CGDb->insert('tagged',array(
								'tid' => $Tag['tid'],
								'ponyid' => $tg['ponyid']
							))) CoreUtils::Respond("Tag synonym removal process failed, please re-try.<br>Technical details: ponyid={$tg['ponyid']} tid={$Tag['tid']}");
							$uses++;
						}
					}
					else $keep_tagged = false;
				}

				if (!$CGDb->where('tid', $Tag['tid'])->update('tags', array('synonym_of' => null, 'uses' => $uses)))
					CoreUtils::Respond(ERR_DB_FAIL);

				CoreUtils::Respond(array('keep_tagged' => $keep_tagged));
			}

			$name = isset($_POST['name']) ? strtolower(trim($_POST['name'])) : null;
			$nl = !empty($name) ? strlen($name) : 0;
			if ($nl < 3 || $nl > 30)
				CoreUtils::Respond("Tag name must be between 3 and 30 characters");
			if ($name[0] === '-')
				CoreUtils::Respond('Tag name cannot start with a dash');
			CoreUtils::CheckStringValidity($name,'Tag name',INVERSE_TAG_NAME_PATTERN);
			$sanitized_name = regex_replace(new RegExp('[^a-z\d]'),'',$name);
			if (regex_match(new RegExp('^(b+[a4]+w*d+|g+[uo0]+d+|(?:b+[ae3]+|w+[o0]+r+[s5]+[t7])[s5]+t+)(e+r+|e+s+t+)?p+[o0]+[wh]*n+[ye3]*$'),$sanitized_name))
				CoreUtils::Respond('Highly opinion-based tags are not allowed');
			$data['name'] = $name;

			$epTagName = CGUtils::CheckEpisodeTagName($data['name']);
			$surelyAnEpisodeTag = $epTagName !== false;
			if (empty($_POST['type'])){
				if ($surelyAnEpisodeTag)
					$data['name'] = $epTagName;
				$data['type'] = $epTagName === false ? null : 'ep';
			}
			else {
				$type = trim($_POST['type']);
				if (!isset(\CG\Tags::$TAG_TYPES_ASSOC[$type]))
					CoreUtils::Respond("Invalid tag type: $type");

				if ($type == 'ep'){
					if (!$surelyAnEpisodeTag)
						CoreUtils::Respond('Episode tags must be in the format of <strong>s##e##[-##]</strong> where # represents a number<br>Allowed seasons: 1-8, episodes: 1-26');
					$data['name'] = $epTagName;
				}
				else if ($surelyAnEpisodeTag)
					$type = $ep;
				$data['type'] = $type;
			}

			if (!$new) $CGDb->where('tid',$Tag['tid'],'!=');
			if ($CGDb->where('name', $data['name'])->where('type', $data['type'])->has('tags') || $data['name'] === 'wrong cutie mark')
				CoreUtils::Respond("A tag with the same name and type already exists");

			if (empty($_POST['title'])) $data['title'] = null;
			else {
				$title = trim($_POST['title']);
				$tl = strlen($title);
				if ($tl > 255)
					CoreUtils::Respond("Your title exceeds the 255 character limit by ".($tl-255)." characters.");
				$data['title'] = $title;
			}

			if ($new){
				$TagID = $CGDb->insert('tags', $data, 'tid');
				if (!$TagID) CoreUtils::Respond(ERR_DB_FAIL);
				$data['tid'] = $TagID;

				if (!empty($_POST['addto']) && is_numeric($_POST['addto'])){
					$AppearanceID = intval($_POST['addto'], 10);
					if ($AppearanceID === 0)
						CoreUtils::Respond("The tag was created, <strong>but</strong> it could not be added to the appearance because it can't be tagged.", 1);

					$Appearance = $CGDb->where('id', $AppearanceID)->getOne('appearances');
					if (empty($Appearance))
						CoreUtils::Respond("The tag was created, <strong>but</strong> it could not be added to the appearance (<a href='/cg/v/$AppearanceID'>#$AppearanceID</a>) because it doesn't seem to exist. Please try adding the tag manually.", 1);

					if (!$CGDb->insert('tagged',array(
						'tid' => $data['tid'],
						'ponyid' => $Appearance['id']
					))) CoreUtils::Respond(ERR_DB_FAIL);
					\CG\Tags::UpdateUses($data['tid']);
					CoreUtils::Respond(array('tags' => \CG\Appearances::GetTagsHTML($Appearance['id'], NOWRAP)));
				}
			}
			else {
				$CGDb->where('tid', $Tag['tid'])->update('tags', $data);
				$data = array_merge($Tag, $data);
			}

			CoreUtils::Respond($data);
		}
		else if (regex_match(new RegExp('^([gs]et|make|del)cg(?:/(\d+))?$'), $data, $_match)){
			$setting = $_match[1] === 'set';
			$getting = $_match[1] === 'get';
			$deleting = $_match[1] === 'del';
			$new = $_match[1] === 'make';

			if (!$new){
				if (empty($_match[2]))
					CoreUtils::Respond('Missing color group ID');
				$GroupID = intval($_match[2], 10);
				$Group = $CGDb->where('groupid', $GroupID)->getOne('colorgroups');
				if (empty($GroupID))
					CoreUtils::Respond("There's no $color group with the ID of $GroupID");

				if ($getting){
					$Group['Colors'] = \CG\Colors::Get($Group['groupid']);
					CoreUtils::Respond($Group);
				}

				if ($deleting){
					if (!$CGDb->where('groupid', $Group['groupid'])->delete('colorgroups'))
						CoreUtils::Respond(ERR_DB_FAIL);
					CoreUtils::Respond("$Color group deleted successfully", 1);
				}
			}
			$data = array();

			if (empty($_POST['label']))
				CoreUtils::Respond('Please specify a group name');
			$name = $_POST['label'];
			CoreUtils::CheckStringValidity($name, "$Color group name", INVERSE_PRINTABLE_ASCII_REGEX);
			$nl = strlen($name);
			if ($nl < 2 || $nl > 30)
				CoreUtils::Respond('The group name must be between 2 and 30 characters in length');
			$data['label'] = $name;

			if (!empty($_POST['major'])){
				$major = true;

				if (empty($_POST['reason']))
					CoreUtils::Respond('Please specify a reason');
				$reason = $_POST['reason'];
				CoreUtils::CheckStringValidity($reason, "Change reason", INVERSE_PRINTABLE_ASCII_REGEX);
				$rl = strlen($reason);
				if ($rl < 1 || $rl > 255)
					CoreUtils::Respond('The reason must be between 1 and 255 characters in length');
			}

			if ($new){
				if (!isset($_POST['ponyid']) || !is_numeric($_POST['ponyid']))
					CoreUtils::Respond('Missing appearance ID');
				$AppearanceID = intval($_POST['ponyid'], 10);
				$Appearance = $CGDb->where('id', $AppearanceID)->where('ishuman', $EQG)->getOne('appearances');
				if (empty($Appearance))
					CoreUtils::Respond('The specified appearance odes not exist');
				$data['ponyid'] = $AppearanceID;

				// Attempt to get order number of last color group for the appearance
				$LastGroup = \CG\ColorGroups::Get($AppearanceID, '"order"', 'DESC', 1);
				$data['order'] =  !empty($LastGroup['order']) ? $LastGroup['order']+1 : 1;

				$GroupID = $CGDb->insert('colorgroups', $data, 'groupid');
				if (!$GroupID)
					CoreUtils::Respond(ERR_DB_FAIL);
				$Group = array('groupid' => $GroupID);
			}
			else $CGDb->where('groupid', $Group['groupid'])->update('colorgroups', $data);


			if (empty($_POST['Colors']))
				CoreUtils::Respond("Missing list of {$color}s");
			$recvColors = JSON::Decode($_POST['Colors'], true);
			if (empty($recvColors))
				CoreUtils::Respond("Missing list of {$color}s");
			$colors = array();
			foreach ($recvColors as $part => $c){
				$append = array('order' => $part);
				$index = "(index: $part)";

				if (empty($c['label']))
					CoreUtils::Respond("You must specify a $color name $index");
				$label = trim($c['label']);
				CoreUtils::CheckStringValidity($label, "$Color $index name", INVERSE_PRINTABLE_ASCII_REGEX);
				$ll = strlen($label);
				if ($ll < 3 || $ll > 30)
					CoreUtils::Respond("The $color name must be between 3 and 30 characters in length $index");
				$append['label'] = $label;

				if (empty($c['hex']))
					CoreUtils::Respond("You must specify a $color code $index");
				$hex = trim($c['hex']);
				if (!$HEX_COLOR_PATTERN->match($hex, $_match))
					CoreUtils::Respond("HEX $color is in an invalid format $index");
				$append['hex'] = '#'.strtoupper($_match[1]);

				$colors[] = $append;
			}
			if (!$new)
				$CGDb->where('groupid', $Group['groupid'])->delete('colors');
			$colorError = false;
			foreach ($colors as $i => $c){
				$c['groupid'] = $Group['groupid'];
				if (!$CGDb->insert('colors', $c) && !$colorError)
					$colorError = true;
			}
			if ($colorError)
				CoreUtils::Respond("There were some issues while saving some of the colors. Please let the developer know about this error, so he can look into why this might've happened.");

			$colon = !$AppearancePage;
			$outputNames = $AppearancePage;

			if ($new) $response = array('cgs' => \CG\Appearances::GetColorsHTML($Appearance['id'], NOWRAP, $colon, $outputNames));
			else $response = array('cg' => \CG\ColorGroups::GetHTML($Group['groupid'], NOWRAP, $colon, $outputNames));

			$AppearanceID = $new ? $Appearance['id'] : $Group['ponyid'];
			if (isset($major)){
				Log::Action('color_modify',array(
					'ponyid' => $AppearanceID,
					'reason' => $reason,
				));
				$response['update'] = \CG\Appearances::GetUpdatesHTML($AppearanceID);
			}
			CGUtils::ClearRenderedImage($AppearanceID);

			if (isset($_POST['APPEARANCE_PAGE']))
				$response['cm_img'] = "/cg/v/$AppearanceID.svg?t=".time();
			else $response['notes'] = \CG\Appearances::GetNotesHTML($CGDb->where('id', $AppearanceID)->getOne('appearances'),  NOWRAP);

			CoreUtils::Respond($response);
		}
		else CoreUtils::NotFound();
	}

	if (regex_match(new RegExp('^tags'),$data)){
		$Pagination = new Pagination("{$color}guide/tags", 20, $CGDb->count('tags'));

		CoreUtils::FixPath("/cg/tags/{$Pagination->page}");
		$heading = "Tags";
		$title = "Page $Pagination->page - $heading - $Color Guide";

		$Tags = \CG\Tags::Get(null,$Pagination->GetLimit(), true);

		if (isset($_GET['js']))
			$Pagination->Respond(get_taglist_html($Tags, NOWRAP), '#tags tbody');

		$js = array('paginate');
		if (Permission::Sufficient('inspector'))
			$js[] = "$do-tags";

		CoreUtils::LoadPage(array(
			'title' => $title,
			'heading' => $heading,
			'view' => "$do-tags",
			'css' => "$do-tags",
			'js' => $js,
		));
	}

	if (regex_match(new RegExp('^changes'),$data)){
		$Pagination = new Pagination("{$color}guide/changes", 50, $Database->count('log__color_modify'));

		CoreUtils::FixPath("/cg/changes/{$Pagination->page}");
		$heading = "Major $Color Changes";
		$title = "Page $Pagination->page - $heading - $Color Guide";

		$Changes = \CG\Updates::Get(null, $Pagination->GetLimitString());

		if (isset($_GET['js']))
			$Pagination->Respond(CGUtils::GetChangesHTML($Changes, NOWRAP, SHOW_APPEARANCE_NAMES), '#changes');

		CoreUtils::LoadPage(array(
			'title' => $title,
			'heading' => $heading,
			'view' => "$do-changes",
			'css' => "$do-changes",
			'js' => 'paginate',
		));
	}

	$EQG = $EQG_URL_PATTERN->match($data) ? 1 : 0;
	if ($EQG)
		$data = $EQG_URL_PATTERN->replace('', $data);
	$CGPath = "/cg".($EQG?'/eqg':'');

	$GUIDE_MANAGE_JS = array(
		'jquery.uploadzone',
		'twitter-typeahead',
		'handlebars-v3.0.3',
		'Sortable',
		'ace',
		'ace-mode-colorguide',
		'ace-theme-colorguide',
		"$do-tags",
		"$do-manage",
	);
	$GUIDE_MANAGE_CSS = array(
		'ace-theme-colorguide',
		"$do-manage",
	);

	$_match = array();
	// Matching IDs:                                                    [-1-] [-2-]                               [---3---]
	if (regex_match(new RegExp('^(?:appearance|v)/(?:.*?(\d+)|(\d+)(?:-.*)?)(?:\.(png|svg))?'),$data,$_match)){
		$asFile = !empty($_match[3]);
		$Appearance = $CGDb->where('id', (int)($_match[1]??$_match[2]))->where('ishuman', $EQG)->getOne('appearances', $asFile ? 'id,label,cm_dir' : null);
		if (empty($Appearance))
			CoreUtils::NotFound();

		if ($asFile){
			switch ($_match[3]){
				case 'png': CGUtils::RenderAppearancePNG($Appearance);
				case 'svg': CGUtils::RenderCMDirectionSVG($Appearance['id'], $Appearance['cm_dir']);
			}
			# rendering functions internally call die(), so execution stops here #
		}

		$SafeLabel = \CG\Appearances::GetSafeLabel($Appearance);
		CoreUtils::FixPath("$CGPath/v/{$Appearance['id']}-$SafeLabel");
		$title = $heading = $Appearance['label'];
		if ($Appearance['id'] === 0 && $color !== 'color')
			$title = str_replace('color',$color,$title);

		$Changes = \CG\Updates::Get($Appearance['id']);

		$settings = array(
			'title' => "$title - $Color Guide",
			'heading' => $heading,
			'view' => "$do-single",
			'css' => array($do, "$do-single"),
			'js' => array('jquery.qtip', 'jquery.ctxmenu', $do, "$do-single"),
		);
		if (Permission::Sufficient('inspector')){
			$settings['css'] = array_merge($settings['css'], $GUIDE_MANAGE_CSS);
			$settings['js'] = array_merge($settings['js'],$GUIDE_MANAGE_JS);
		}
		CoreUtils::LoadPage($settings);
	}
	else if ($data === 'full'){
		$GuideOrder = !isset($_REQUEST['alphabetically']) && !$EQG;
		if (!$GuideOrder)
			$CGDb->orderBy('label','ASC');
		$Appearances = \CG\Appearances::Get($EQG,null,'id,label');


		if (isset($_REQUEST['ajax']))
			CoreUtils::Respond(array('html' => CGUtils::GetFullListHTML($Appearances, $GuideOrder, NOWRAP)));

		$js = array();
		if (Permission::Sufficient('inspector'))
			$js[] = 'Sortable';
		$js[] = "$do-full";

		CoreUtils::LoadPage(array(
			'title' => "Full List - $Color Guide",
			'view' => "$do-full",
			'css' => "$do-full",
			'js' => $js,
		));
	}

	$title = '';
	$AppearancesPerPage = 7;
	if (empty($_GET['q']) || regex_match(new RegExp('^\*+$'),$_GET['q'])){
		$_EntryCount = $CGDb->where('ishuman',$EQG)->where('id != 0')->count('appearances');

		$Pagination = new Pagination("{$color}guide", $AppearancesPerPage, $_EntryCount);
		$Ponies = \CG\Appearances::Get($EQG, $Pagination->GetLimit());
	}
	else {
		$SearchQuery = $_GET['q'];
		$Page = $MaxPages = 1;
		$Ponies = false;

		try {
			$Search = CGUtils::ProcessSearch($SearchQuery);
			$title .= "$SearchQuery - ";
			$IsHuman = $EQG ? 'true' : 'false';

			$Restrictions = array();
			$Params = array();
			if (!empty($Search['tid'])){
				$tc = count($Search['tid']);
				$Restrictions[] = 'p.id IN (
					--SELECT ponyid FROM (
						SELECT t.ponyid
						FROM tagged t
						WHERE t.tid IN ('.implode(',', $Search['tid']).")
						GROUP BY t.ponyid
						HAVING COUNT(t.tid) = $tc
					--) tg
				)";
				$Search['tid_assoc'] = array();
				foreach ($Search['tid'] as $tid)
					$Search['tid_assoc'][$tid] = true;
			}
			if (!empty($Search['label'])){
				$collect = array();
				foreach ($Search['label'] as $l){
					$collect[] = 'lower(p.label) LIKE ?';
					$Params[] = $l;
				}
				$Restrictions[] = implode(' AND ', $collect);
			}

			if (count($Restrictions)){
				$Params[] = $EQG;
				$Query = "SELECT @coloumn FROM appearances p WHERE ".implode(' AND ',$Restrictions)." AND p.ishuman = ? AND p.id != 0";
				$_EntryCount = $CGDb->rawQuerySingle(str_replace('@coloumn','COUNT(*) as count',$Query),$Params)['count'];
				$Pagination = new Pagination("{$color}guide", $AppearancesPerPage, $_EntryCount);

				$SearchQuery = str_replace('@coloumn','p.*',$Query);
				$SearchQuery .= " ORDER BY p.order ASC {$Pagination->GetLimitString()}";
				$Ponies = $CGDb->rawQuery($SearchQuery,$Params);
			}
		}
		catch (Exception $e){
			$_MSG = $e->getMessage();
			if (isset($_REQUEST['js']))
				CoreUtils::Respond($_MSG);
		}
	}

	CoreUtils::FixPath("$CGPath/{$Pagination->page}");
	$heading = ($EQG?'EQG ':'')."$Color Guide";
	$title .= "Page {$Pagination->page} - $heading";

	if (isset($_GET['js']))
		$Pagination->Respond(\CG\Appearances::GetHTML($Ponies, NOWRAP), '#list');

	$settings = array(
		'title' => $title,
		'heading' => $heading,
		'css' => array($do),
		'js' => array('jquery.qtip', 'jquery.ctxmenu', $do, 'paginate'),
	);
	if (Permission::Sufficient('inspector')){
		$settings['css'] = array_merge($settings['css'], $GUIDE_MANAGE_CSS);
		$settings['js'] = array_merge($settings['js'],$GUIDE_MANAGE_JS);
	}
	CoreUtils::LoadPage($settings);
