<?php

	use ONGR\ElasticsearchDSL;
	use ONGR\ElasticsearchDSL\Query\BoolQuery;
	use ONGR\ElasticsearchDSL\Query\TermQuery;

	if (POST_REQUEST || (isset($_GET['s']) && $data === "gettags")){
		if (!Permission::Sufficient('staff')) Response::Fail();
		if (POST_REQUEST) CSRFProtection::Protect();

		$EQG = isset($_REQUEST['eqg']) ? 1 : 0;
		$AppearancePage = isset($_POST['APPEARANCE_PAGE']);

		switch ($data){
			case 'gettags':
				$not_tid = (new Input('not','int',array(Input::IS_OPTIONAL => true)))->out();
				if ((new Input('action','string',array(Input::IS_OPTIONAL => true)))->out() === 'synon'){
					if (isset($not_tid))
						$CGDb->where('tid',$not_tid);
					$Tag = $CGDb->where('"synonym_of" IS NOT NULL')->getOne('tags');
					if (!empty($Tag)){
						$Syn = \CG\Tags::GetSynonymOf($Tag,'name');
						Response::Fail("This tag is already a synonym of <strong>{$Syn['name']}</strong>.<br>Would you like to remove the synonym?",array('undo' => true));
					}
				}

				$viaAutocomplete = !empty($_GET['s']);
				$limit = null;
				$cols = "tid, name, type";
				if ($viaAutocomplete){
					if (!regex_match($TAG_NAME_REGEX, $_GET['s']))
						CGUtils::AutocompleteRespond('[]');

					$query = CoreUtils::Trim(strtolower($_GET['s']));
					$TagCheck = CGUtils::CheckEpisodeTagName($query);
					if ($TagCheck !== false)
						$query = $TagCheck;
					$CGDb->where('name',"%$query%",'LIKE');
					$limit = 5;
					$cols = "tid, name, 'typ-'||type as type";
					$CGDb->orderBy('uses','DESC');
				}
				else $CGDb->orderBy('type','ASC')->where('"synonym_of" IS NULL');

				if (isset($not_tid))
					$CGDb->where('tid',$not_tid,'!=');
				$Tags = $CGDb->orderBy('name','ASC')->get('tags',$limit,"$cols, uses, synonym_of");
				if ($viaAutocomplete)
					foreach ($Tags as &$t){
						if (empty($t['synonym_of']))
							continue;
						$Syn = $CGDb->where('tid', $t['synonym_of'])->getOne('tags','name');
						if (!empty($Syn))
							$t['synonym_target'] = $Syn['name'];
					};

				CGUtils::AutocompleteRespond(empty($Tags) ? '[]' : $Tags);
			break;
			case 'full':
				if (!isset($_REQUEST['reorder']))
					CoreUtils::NotFound();

				if (!Permission::Sufficient('staff'))
					Response::Fail();

				\CG\Appearances::Reorder((new Input('list','int[]',array(
					Input::CUSTOM_ERROR_MESSAGES => array(
						Input::ERROR_MISSING => 'The list of IDs is missing',
						Input::ERROR_INVALID => 'The list of IDs is not formatted properly',
					)
				)))->out());

				Response::Done(array('html' => CGUtils::GetFullListHTML(\CG\Appearances::Get($EQG,null,'id,label'), true, NOWRAP)));
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

					$AppendAppearance['notes'] = CoreUtils::TrimMultiline($AppendAppearance['notes']);

					$AppendAppearance['ColorGroups'] = array();
					if (empty($AppendAppearance['private'])){
						$ColorGroups = \CG\ColorGroups::Get($p['id']);
						if (!empty($ColorGroups)){
							$AllColors = \CG\ColorGroups::GetColorsForEach($ColorGroups);
							foreach ($ColorGroups as $cg){
								$AppendColorGroup = $cg;
								unset($AppendColorGroup['ponyid']);

								$AppendColorGroup['Colors'] = array();
								if (!empty($AllColors[$cg['groupid']]))
									foreach ($AllColors[$cg['groupid']] as $c){
										unset($c['groupid']);
										$AppendColorGroup['Colors'][] = $c;
									};

								$AppendAppearance['ColorGroups'][$cg['groupid']] = $AppendColorGroup;
							}
						}
					}
					else $AppendAppearance['ColorGroups']['_hidden'] = true;

					$AppendAppearance['TagIDs'] = array();
					$TagIDs = \CG\Tags::GetFor($p['id'],null,null,true);
					if (!empty($TagIDs))
						foreach ($TagIDs as $t)
							$AppendAppearance['TagIDs'][] = $t['tid'];

					$AppendAppearance['RelatedAppearances'] = array();
					$RelatedIDs = \CG\Appearances::GetRelated($p['id']);
					if (!empty($RelatedIDs))
						foreach ($RelatedIDs as $rel)
							$AppendAppearance['RelatedAppearances'][] = $rel['id'];

					$JSON['Appearances'][$p['id']] = $AppendAppearance;
				}

				$data = JSON::Encode($JSON, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
				$data = preg_replace_callback('/^\s+/m', function($match){
					return str_pad('',strlen($match[0])/4,"\t", STR_PAD_LEFT);
				}, $data);

				CoreUtils::DownloadFile($data, 'mlpvc-colorguide.json');
			break;
			case "reindex":
				if (Permission::Insufficient('developer'))
					Response::Fail();
				\CG\Appearances::Reindex();
			break;
		}

		$_match = array();
		// Appearance actions
		if (regex_match(new RegExp('^(rename|delete|make|(?:[gs]et|del)(?:sprite|cgs|relations)?|tag|untag|clearrendercache|applytemplate)(?:/(\d+))?$'), $data, $_match)){
			$action = $_match[1];
			$creating = $action === 'make';

			if (!$creating){
				$AppearanceID = intval($_match[2], 10);
				if (strlen($_match[2]) === 0)
					Response::Fail('Missing appearance ID');
				$Appearance = $CGDb->where('id', $AppearanceID)->where('ishuman', $EQG)->getOne('appearances');
				if (empty($Appearance))
					Response::Fail("The specified appearance does not exist");
			}
			else $Appearance = array('id' => null);

			switch ($action){
				case "get":
					Response::Done(array(
						'label' => $Appearance['label'],
						'notes' => $Appearance['notes'],
						'cm_favme' => !empty($Appearance['cm_favme']) ? "http://fav.me/{$Appearance['cm_favme']}" : null,
						'cm_preview' => $Appearance['cm_preview'],
						'cm_dir' => (
							isset($Appearance['cm_dir'])
							? ($Appearance['cm_dir'] === CM_DIR_HEAD_TO_TAIL ? 'ht' : 'th')
							: null
						),
						'private' => $Appearance['private'],
					));
				break;
				case "set":
				case "make":
					$data = array(
						'ishuman' => $EQG,
					    'cm_favme' => null,
					);

					$label = (new Input('label','string',array(
						Input::IN_RANGE => [4,70],
						Input::CUSTOM_ERROR_MESSAGES => array(
							Input::ERROR_MISSING => 'Appearance name is missing',
							Input::ERROR_RANGE => 'Appearance name must be beetween @min and @max characters long',
						)
					)))->out();
					CoreUtils::CheckStringValidity($label, "Appearance name", INVERSE_PRINTABLE_ASCII_PATTERN);
					if (!$creating)
						$CGDb->where('id', $Appearance['id'], '!=');
					$dupe = $CGDb->where('ishuman', $data['ishuman'])->where('label', $label)->getOne('appearances');
					if (!empty($dupe)){
						$eqg_url = $EQG ? '/eqg':'';
						Response::Fail("An appearance <a href='/cg$eqg_url/v/{$dupe['id']}' target='_blank'>already esists</a> in the ".($EQG?'EQG':'Pony').' guide with this exact name. Consider adding an identifier in backets or choosing a different name.');
					}
					$data['label'] = $label;

					$notes = (new Input('notes','text',array(
						Input::IS_OPTIONAL => true,
						Input::IN_RANGE => $creating || $Appearance['id'] !== 0 ? [null,1000] : null,
						Input::CUSTOM_ERROR_MESSAGES => array(
							Input::ERROR_RANGE => 'Appearance notes cannot be longer than @max characters',
						)
					)))->out();
					if (isset($notes)){
						CoreUtils::CheckStringValidity($notes, "Appearance notes", INVERSE_PRINTABLE_ASCII_PATTERN);
						$notes = CoreUtils::SanitizeHtml($notes);
						if ($creating || $notes !== $Appearance['notes'])
							$data['notes'] = $notes;
					}
					else $data['notes'] = null;

					$cm_favme = (new Input('cm_favme','string',array(Input::IS_OPTIONAL => true)))->out();
					if (isset($cm_favme)){
						try {
							$Image = new ImageProvider($cm_favme, array('fav.me', 'dA'));
							CoreUtils::CheckDeviationInClub($Image->id, true);
							$data['cm_favme'] = $Image->id;
						}
						catch (MismatchedProviderException $e){
							Response::Fail('The vector must be on DeviantArt, '.$e->getActualProvider().' links are not allowed');
						}
						catch (Exception $e){ Response::Fail("Cutie Mark link issue: ".$e->getMessage()); }

						$cm_dir = (new Input('cm_dir',function($value){
							if ($value !== 'th' && $value !== 'ht')
								return Input::ERROR_INVALID;
						},array(
							Input::CUSTOM_ERROR_MESSAGES => array(
								Input::ERROR_MISSING => 'Cutie mark orientation must be set if a link is provided',
								Input::ERROR_INVALID => 'Cutie mark orientation (@value) is invalid',
							)
						)))->out();
						$cm_dir = $cm_dir === 'ht' ? CM_DIR_HEAD_TO_TAIL : CM_DIR_TAIL_TO_HEAD;
						if ($creating || $Appearance['cm_dir'] !== $cm_dir)
							$data['cm_dir'] = $cm_dir;

						$cm_preview = (new Input('cm_preview','string',array(Input::IS_OPTIONAL => true)))->out();
						if (empty($cm_preview))
							$data['cm_preview'] = null;
						else if ($creating || $cm_preview !== $Appearance['cm_preview']){
							try {
								$Image = new ImageProvider($cm_preview);
								$data['cm_preview'] = $Image->preview;
							}
							catch (Exception $e){ Response::Fail("Cutie Mark preview issue: ".$e->getMessage()); }
						}
					}
					else {
						$data['cm_dir'] = null;
						$data['cm_preview'] = null;
					}

					$data['private'] = isset($_POST['private']);

					$query = $creating
						? $CGDb->insert('appearances', $data, 'id')
						: $CGDb->where('id', $Appearance['id'])->update('appearances', $data);
					if (!$query)
						Response::DBError();

					$EditedAppearance = $CGDb->where('id', $creating ? $query : $Appearance['id'])->getOne('appearances', \CG\Appearances::ELASTIC_COLUMNS);
					CoreUtils::ElasticClient()->index(\CG\Appearances::ToElasticArray($EditedAppearance));

					if ($creating){
						$data['id'] = $query;
						$response = array(
							'message' => 'Appearance added successfully',
							'id' => $query,
						);
						$usetemplate = isset($_POST['template']);
						if ($usetemplate){
							try {
								\CG\Appearances::ApplyTemplate($query, $EQG);
							}
							catch (Exception $e){
								$response['message'] .= ", but applying the template failed";
								$response['info'] = "The common color groups could not be added.<br>Reason: ".$e->getMessage();
								$usetemplate = false;
							}
						}

						Log::Action('appearances',array(
							'action' => 'add',
						    'id' => $data['id'],
						    'order' => $data['order'] ?? null,
						    'label' => $data['label'],
						    'notes' => $data['notes'],
						    'cm_favme' => $data['cm_favme'] ?? null,
						    'ishuman' => $data['ishuman'],
						    'cm_preview' => $data['cm_preview'],
						    'cm_dir' => $data['cm_dir'],
							'usetemplate' => $usetemplate ? 1 : 0,
							'private' => $data['private'] ? 1 : 0,
						));
						Response::Done($response);
					}

					CGUtils::ClearRenderedImages($Appearance['id'], array(CGUtils::CLEAR_PALETTE, CGUtils::CLEAR_PREVIEW));

					$response = array();
					$diff = array();
					foreach (array('label','notes','cm_favme','cm_dir','cm_preview','private') as $key){
						if ($EditedAppearance[$key] !== $Appearance[$key]){
							$diff["old$key"] = $Appearance[$key];
							$diff["new$key"] = $EditedAppearance[$key];
						}
					}
					if (!empty($diff)) Log::Action('appearance_modify',array(
						'ponyid' => $Appearance['id'],
						'changes' => JSON::Encode($diff),
					));

					if (!$AppearancePage){
						$response['label'] = $EditedAppearance['label'];
						if ($data['label'] !== $Appearance['label'])
							$response['newurl'] = $Appearance['id'].'-'.\CG\Appearances::GetSafeLabel($EditedAppearance);
						$response['notes'] = \CG\Appearances::GetNotesHTML($EditedAppearance, NOWRAP);
					}

					Response::Done($response);
				break;
				case "delete":
					if ($Appearance['id'] === 0)
						Response::Fail('This appearance cannot be deleted');

					$Tagged = \CG\Tags::GetFor($Appearance['id'], null, true, false);

					if (!$CGDb->where('id', $Appearance['id'])->delete('appearances'))
						Response::DBError();

					try {
						CoreUtils::ElasticClient()->delete(\CG\Appearances::ToElasticArray($Appearance, true));
					}
					catch (Elasticsearch\Common\Exceptions\Missing404Exception $e){
						$message = JSON::Decode($e->getMessage());

						// Eat error if appearance was not indexed
						if ($message['found'] !== false)
							throw $e;
					}

					if (!empty($Tagged))
						foreach($Tagged as $tag){
							\CG\Tags::UpdateUses($tag['tid']);
						};

					$fpath = APPATH."img/cg/{$Appearance['id']}.png";
					if (file_exists($fpath))
						unlink($fpath);

					CGUtils::ClearRenderedImages($Appearance['id']);

					Log::Action('appearances',array(
						'action' => 'del',
					    'id' => $Appearance['id'],
					    'order' => $Appearance['order'],
					    'label' => $Appearance['label'],
					    'notes' => $Appearance['notes'],
					    'cm_favme' => $Appearance['cm_favme'],
					    'ishuman' => $Appearance['ishuman'],
					    'added' => $Appearance['added'],
					    'cm_preview' => $Appearance['cm_preview'],
					    'cm_dir' => $Appearance['cm_dir'],
					    'private' => $Appearance['private'],
					));

					Response::Success('Appearance removed');
				break;
				case "getcgs":
					$cgs = \CG\ColorGroups::Get($Appearance['id'],'groupid, label');
					if (empty($cgs))
						Response::Fail('This appearance does not have any color groups');
					Response::Done(array('cgs' => $cgs));
				break;
				case "setcgs":
					$order = (new Input('cgs','int[]',array(
						Input::CUSTOM_ERROR_MESSAGES => array(
							Input::ERROR_MISSING => "$Color group order data missing"
						)
					)))->out();
					$oldCGs = \CG\ColorGroups::Get($Appearance['id']);
					$possibleIDs = array();
					foreach ($oldCGs as $cg)
						$possibleIDs[$cg['groupid']] = true;
					foreach ($order as $i => $GroupID){
						if (empty($possibleIDs[$GroupID]))
							Response::Fail("There's no group with the ID of $GroupID on this appearance");

						$CGDb->where('groupid', $GroupID)->update('colorgroups',array('order' => $i));
					}
					$newCGs = \CG\ColorGroups::Get($Appearance['id']);

					CGUtils::ClearRenderedImages($Appearance['id'], array(CGUtils::CLEAR_PALETTE, CGUtils::CLEAR_PREVIEW));

					$oldCGs = \CG\ColorGroups::Stringify($oldCGs);
					$newCGs = \CG\ColorGroups::Stringify($newCGs);
					if ($oldCGs !== $newCGs) Log::Action('cg_order',array(
						'ponyid' => $Appearance['id'],
						'oldgroups' => $oldCGs,
						'newgroups' => $newCGs,
					));

					Response::Done(array('cgs' => \CG\Appearances::GetColorsHTML($Appearance, NOWRAP, !$AppearancePage, $AppearancePage)));
				break;
				case "delsprite":
				case "getsprite":
				case "setsprite":
					$fname = $Appearance['id'].'.png';
					$finalpath = SPRITE_PATH.$fname;

					switch ($action){
						case "setsprite":
							CGUtils::ProcessUploadedImage('sprite', $finalpath, array('image/png'), 100);
							CGUtils::ClearRenderedImages($Appearance['id']);
						break;
						case "delsprite":
							if (empty(\CG\Appearances::GetSpriteURL($Appearance['id'])))
								Response::Fail('No sprite file found');

							if (!unlink($finalpath))
								Response::Fail('File could not be deleted');
							CGUtils::ClearRenderedImages($Appearance['id']);

							Response::Done(array('sprite' => DEFAULT_SPRITE));
						break;
					}

					Response::Done(array("path" => "/cg/v/{$Appearance['id']}s.png?t=".filemtime($finalpath)));
				break;
				case "getrelations":
					$CheckTag = array();

					$RelatedAppearances = \CG\Appearances::GetRelated($Appearance['id']);
					$RelatedAppearanceIDs = array();
					foreach ($RelatedAppearances as $p)
						$RelatedAppearanceIDs[$p['id']] = $p['mutual'];

					$Appearances = $CGDb->where('ishuman', $EQG)->where('"id" NOT IN (0,'.$Appearance['id'].')')->orderBy('label','ASC')->get('appearances',null,'id,label');

					$Sorted = array(
						'unlinked' => array(),
						'linked' => array(),
					);
					foreach ($Appearances as $a){
						$linked = isset($RelatedAppearanceIDs[$a['id']]);
						if ($linked)
							$a['mutual'] = $RelatedAppearanceIDs[$a['id']];
						$Sorted[$linked ? 'linked' : 'unlinked'][] = $a;
					}

					Response::Done($Sorted);
				break;
				case "setrelations":
					$AppearanceIDs = (new Input('ids','int[]',array(
						Input::IS_OPTIONAL => true,
						Input::CUSTOM_ERROR_MESSAGES => array(
							Input::ERROR_INVALID => 'Appearance ID list is invalid',
						)
					)))->out();
					$MutualIDs = (new Input('mutuals','int[]',array(
						Input::IS_OPTIONAL => true,
						Input::CUSTOM_ERROR_MESSAGES => array(
							Input::ERROR_INVALID => 'Mutial relation ID list is invalid',
						)
					)))->out();

					$appearances = [];
					if (!empty($AppearanceIDs))
						foreach ($AppearanceIDs as $id){
							$appearances[$id] = true;
						};

					$mutuals = array();
					if (!empty($MutualIDs))
						foreach ($MutualIDs as $id){
							$mutuals[$id] = true;
							unset($appearances[$id]);
						};

					$CGDb->where('source', $Appearance['id'])->delete('appearance_relations');
					if (!empty($appearances))
						foreach ($appearances as $id => $_){
							@$CGDb->insert('appearance_relations', array(
								'source' => $Appearance['id'],
								'target' => $id,
								'mutual' => isset($mutuals[$id]),
							));
						};
					$CGDb->where('target', $Appearance['id'])->where('mutual', true)->delete('appearance_relations');
					if (!empty($mutuals))
						foreach ($MutualIDs as $id){
							@$CGDb->insert('appearance_relations', array(
								'source' => $id,
								'target' => $Appearance['id'],
								'mutual' => true,
							));
						};

					Response::Done(array('section' => \CG\Appearances::GetRelatedHTML(\CG\Appearances::GetRelated($Appearance['id']))));
				break;
				case "clearrendercache":
					if (!CGUtils::ClearRenderedImages($Appearance['id']))
						Response::Fail('Cache could not be purged');

					Response::Success('Cached images have been removed, they will be re-generated on the next request');
				break;
				case "tag":
				case "untag":
					if ($Appearance['id'] === 0)
						Response::Fail("This appearance cannot be tagged");

					switch ($action){
						case "tag":
							$tag_name = CGUtils::ValidateTagName('tag_name');

							$TagCheck = CGUtils::CheckEpisodeTagName($tag_name);
							if ($TagCheck !== false)
								$tag_name = $TagCheck;

							$Tag = \CG\Tags::GetActual($tag_name, 'name');
							if (empty($Tag))
								Response::Fail("The tag $tag_name does not exist.<br>Would you like to create it?",array(
									'cancreate' => $tag_name,
									'typehint' => $TagCheck !== false ? 'ep' : null,
								));

							if ($CGDb->where('ponyid', $Appearance['id'])->where('tid', $Tag['tid'])->has('tagged'))
								Response::Fail('This appearance already has this tag');

							if (!$CGDb->insert('tagged',array(
								'ponyid' => $Appearance['id'],
								'tid' => $Tag['tid'],
							))) Response::DBError();
						break;
						case "untag":
							$tag_id = (new Input('tag','int',array(
								Input::CUSTOM_ERROR_MESSAGES => array (
									Input::ERROR_MISSING => 'Tag ID is missing',
									Input::ERROR_INVALID => 'Tag ID (@value) is invalid',
								)
							)))->out();
							$Tag = $CGDb->where('tid',$tag_id)->getOne('tags');
							if (empty($Tag))
								Response::Fail('This tag does not exist');
							if (!empty($Tag['synonym_of'])){
								$Syn = \CG\Tags::GetSynonymOf($Tag,'name');
								Response::Fail('Synonym tags cannot be removed from appearances directly. '.
								        "If you want to remove this tag you must remove <strong>{$Syn['name']}</strong> or the synonymization.");
							}

							if ($CGDb->where('ponyid', $Appearance['id'])->where('tid', $Tag['tid'])->has('tagged')){
								if (!$CGDb->where('ponyid', $Appearance['id'])->where('tid', $Tag['tid'])->delete('tagged'))
									Response::DBError();
							}
						break;
					}

					CoreUtils::ElasticClient()->index(\CG\Appearances::ToElasticArray($Appearance));

					\CG\Tags::UpdateUses($Tag['tid']);
					if (isset(CGUtils::$GroupTagIDs_Assoc[$Tag['tid']]))
						\CG\Appearances::GetSortReorder($EQG);

					$response = array('tags' => \CG\Appearances::GetTagsHTML($Appearance['id'], NOWRAP));
					if ($AppearancePage && $Tag['type'] === 'ep'){
						$response['needupdate'] = true;
						$response['eps'] = \CG\Appearances::GetRelatedEpisodesHTML($Appearance, $EQG);
					}
					Response::Done($response);
				break;
				case "applytemplate":
					try {
						\CG\Appearances::ApplyTemplate($Appearance['id'], $EQG);
					}
					catch (Exception $e){
						Response::Fail("Applying the template failed. Reason: ".$e->getMessage());
					}

					Response::Done(array('cgs' => \CG\Appearances::GetColorsHTML($Appearance, NOWRAP, !$AppearancePage, $AppearancePage)));
				break;
				default: CoreUtils::NotFound();
			}
		}
		// Tag actions
		else if (regex_match(new RegExp('^([gs]et|make|del|merge|recount|(?:un)?synon)tag(?:/(\d+))?$'), $data, $_match)){
			$action = $_match[1];

			if ($action === 'recount'){
				$tagIDs = (new Input('tagids','int[]',array(
					Input::CUSTOM_ERROR_MESSAGES => array(
						Input::ERROR_MISSING => 'Missing list of tags to update',
						Input::ERROR_INVALID => 'List of tags is invalid',
					)
				)))->out();
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

				Response::Success(
					(
						!$updates
						? 'There was no change in the tag usage counts'
						: "$updates tag".($updates!==1?"s'":"'s").' use count'.($updates!==1?'s were':' was').' updated'
					),
					array('counts' => $counts)
				);
			}

			$adding = $action === 'make';

			if (!$adding){
				if (!isset($_match[2]))
					Response::Fail('Missing tag ID');
				$TagID = intval($_match[2], 10);
				$Tag = $CGDb->where('tid', $TagID)->getOne('tags',isset($query) ? 'tid, name, type':'*');
				if (empty($Tag))
					Response::Fail("This tag does not exist");
			}
			$data = array();

			switch ($action){
				case 'get':
					Response::Done($Tag);
				case 'del':
					$AppearanceID = \CG\Appearances::ValidateAppearancePageID();

					if (!isset($_POST['sanitycheck'])){
						$tid = !empty($Tag['synonym_of']) ? $Tag['synonym_of'] : $Tag['tid'];
						$Uses = $CGDb->where('tid',$tid)->count('tagged');
						if ($Uses > 0)
							Response::Fail('<p>This tag is currently used on '.CoreUtils::MakePlural('appearance',$Uses,PREPEND_NUMBER).'</p><p>Deleting will <strong class="color-red">permanently remove</strong> the tag from those appearances!</p><p>Are you <em class="color-red">REALLY</em> sure about this?</p>',array('confirm' => true));
					}

					if (!$CGDb->where('tid', $Tag['tid'])->delete('tags'))
						Response::DBError();

					if (isset(CGUtils::$GroupTagIDs_Assoc[$Tag['tid']]))
						\CG\Appearances::GetSortReorder($EQG);

					Response::Success('Tag deleted successfully', isset($AppearanceID) && $Tag['type'] === 'ep' ? array(
						'needupdate' => true,
						'eps' => \CG\Appearances::GetRelatedEpisodesHTML($AppearanceID, $EQG),
					) : null);
				break;
				case 'unsynon':
					if (empty($Tag['synonym_of']))
						Response::Done();

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
								))) Response::Fail("Tag synonym removal process failed, please re-try.<br>Technical details: ponyid={$tg['ponyid']} tid={$Tag['tid']}");
								$uses++;
							}
						}
						else $keep_tagged = false;
					}

					if (!$CGDb->where('tid', $Tag['tid'])->update('tags', array('synonym_of' => null, 'uses' => $uses)))
						Response::DBError();

					Response::Done(array('keep_tagged' => $keep_tagged));
				break;
				case 'make':
				case 'set':
					$data['name'] = CGUtils::ValidateTagName('name');

					$epTagName = CGUtils::CheckEpisodeTagName($data['name']);
					$surelyAnEpisodeTag = $epTagName !== false;
					$type = (new Input('type',function($value){
						if (!isset(\CG\Tags::$TAG_TYPES_ASSOC[$value]))
							return Input::ERROR_INVALID;
					},array(
						Input::IS_OPTIONAL => true,
						Input::CUSTOM_ERROR_MESSAGES => array(
							Input::ERROR_INVALID => 'Invalid tag type: @value',
						)
					)))->out();
					if (empty($type)){
						if ($surelyAnEpisodeTag)
							$data['name'] = $epTagName;
						$data['type'] = $epTagName === false ? null : 'ep';
					}
					else {
						if ($type == 'ep'){
							if (!$surelyAnEpisodeTag){
								$errmsg = <<<HTML
Episode tags must be in one of the following formats:
<ol>
	<li>
		<code>s<var>S</var>e<var>E<sub>1</sub></var>[-<var>E<sub>2</sub></var>]</code> where
		<ul>
			<li><var>S</var> ∈ <var>{1, 2, 3, &hellip; 8}</var></li>
			<li><var>E<sub>1</sub></var>, <var>E<sub>2</sub></var> ∈ <var>{1, 2, 3, &hellip; 26}</var></li>
			<li>if specified: <var>E<sub>1</sub></var>+1 = <var>E<sub>2</sub></var></li>
		</ul>
	</li>
	<li>
		<code>movie#<var>M</var></code> where <var>M</var> ∈ <var>&#x2124;<sup>+</sup></var>
	</li>
</ol>
HTML;

								Response::Fail($errmsg);
							}
							$data['name'] = $epTagName;
						}
						else if ($surelyAnEpisodeTag)
							$type = $ep;
						$data['type'] = $type;
					}

					if (!$adding) $CGDb->where('tid', $Tag['tid'],'!=');
					if ($CGDb->where('name', $data['name'])->where('type', $data['type'])->has('tags') || $data['name'] === 'wrong cutie mark')
						Response::Fail("A tag with the same name and type already exists");

					$data['title'] = (new Input('title','string',array(
						Input::IS_OPTIONAL => true,
						Input::IN_RANGE => [null,255],
						Input::CUSTOM_ERROR_MESSAGES => array(
							Input::ERROR_RANGE => 'Tag title cannot be longer than @max characters'
						)
					)))->out();

					if ($adding){
						$TagID = $CGDb->insert('tags', $data, 'tid');
						if (!$TagID)
							Response::DBError();
						$data['tid'] = $TagID;

						$AppearanceID = (new Input('addto','int',array(Input::IS_OPTIONAL => true)))->out();
						if (isset($AppearanceID)){
							if ($AppearanceID === 0)
								Response::Success("The tag was created, <strong>but</strong> it could not be added to the appearance because it can't be tagged.");

							$Appearance = $CGDb->where('id', $AppearanceID)->getOne('appearances');
							if (empty($Appearance))
								Response::Success("The tag was created, <strong>but</strong> it could not be added to the appearance (<a href='/cg/v/$AppearanceID'>#$AppearanceID</a>) because it doesn't seem to exist. Please try adding the tag manually.");

							if (!$CGDb->insert('tagged',array(
								'tid' => $data['tid'],
								'ponyid' => $Appearance['id']
							))) Response::DBError();
							\CG\Tags::UpdateUses($data['tid']);
							$r = array('tags' => \CG\Appearances::GetTagsHTML($Appearance['id'], NOWRAP));
							if ($AppearancePage){
								$r['needupdate'] = true;
								$r['eps'] = \CG\Appearances::GetRelatedEpisodesHTML($Appearance, $EQG);
							}
							Response::Done($r);
						}
					}
					else {
						$CGDb->where('tid', $Tag['tid'])->update('tags', $data);
						$data = array_merge($Tag, $data);
						if ($AppearancePage){
							$ponyid = intval($_POST['APPEARANCE_PAGE'],10);
							if ($CGDb->where('ponyid', $ponyid)->where('tid', $Tag['tid'])->has('tagged')){
								$data['needupdate'] = true;
								$Appearance = $CGDb->where('id', $ponyid)->getOne('appearances');
								$data['eps'] = \CG\Appearances::GetRelatedEpisodesHTML($Appearance, $EQG);
							}
						}
					}

					Response::Done($data);
				break;
			}

			// TODO Untangle spaghetti
			$merging = $action === 'merge';
			$synoning = $action === 'synon';
			if ($merging || $synoning){
				if ($synoning && !empty($Tag['synonym_of']))
					Response::Fail('This tag is already synonymized with a different tag');

				$targetid = (new Input('targetid','int',array(
					Input::CUSTOM_ERROR_MESSAGES => array(
						Input::ERROR_MISSING => 'Missing target tag ID',
					)
				)))->out();
				$Target = $CGDb->where('tid', $targetid)->getOne('tags');
				if (empty($Target))
					Response::Fail('Target tag does not exist');
				if (!empty($Target['synonym_of']))
					Response::Fail('Synonym tags cannot be synonymization targets');

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
					))) Response::Fail('Tag '.($merging?'merging':'synonimizing')." failed, please re-try.<br>Technical details: ponyid={$tg['ponyid']} tid={$Target['tid']}");
				}
				if ($merging)
					// No need to delete "tagged" table entries, constraints do it for us
					$CGDb->where('tid', $Tag['tid'])->delete('tags');
				else {
					$CGDb->where('tid', $Tag['tid'])->delete('tagged');
					$CGDb->where('tid', $Tag['tid'])->update('tags', array('synonym_of' => $Target['tid'], 'uses' => 0));
				}

				\CG\Tags::UpdateUses($Target['tid']);
				Response::Success('Tags successfully '.($merging?'merged':'synonymized'), $synoning || $merging ? array('target' => $Target) : null);
			}
		}
		// Color group actions
		else if (regex_match(new RegExp('^([gs]et|make|del)cg(?:/(\d+))?$'), $data, $_match)){
			$action = $_match[1];
			$adding = $action === 'make';

			if (!$adding){
				if (empty($_match[2]))
					Response::Fail('Missing color group ID');
				$GroupID = intval($_match[2], 10);
				$Group = $CGDb->where('groupid', $GroupID)->getOne('colorgroups');
				if (empty($GroupID))
					Response::Fail("There's no $color group with the ID of $GroupID");

				if ($action === 'get'){
					$Group['Colors'] = \CG\ColorGroups::GetColors($Group['groupid']);
					Response::Done($Group);
				}

				if ($action === 'del'){
					if (!$CGDb->where('groupid', $Group['groupid'])->delete('colorgroups'))
						Response::DBError();

					Log::Action('cgs',array(
						'action' => 'del',
						'groupid' => $Group['groupid'],
						'ponyid' => $Group['ponyid'],
						'label' => $Group['label'],
						'order' => $Group['order'] ?? null,
					));

					Response::Success("$Color group deleted successfully");
				}
			}
			$data = array();

			$data['label'] = (new Input('label','string',array(
				Input::IN_RANGE => [2,30],
				Input::CUSTOM_ERROR_MESSAGES => array(
					Input::ERROR_MISSING => 'Please specify a group name',
					Input::ERROR_RANGE => 'The group name must be between @min and @max characters in length',
				)
			)))->out();
			CoreUtils::CheckStringValidity($data['label'], "$Color group name", INVERSE_PRINTABLE_ASCII_PATTERN, true);

			$major = isset($_POST['major']);
			if ($major){
				$reason = (new Input('reason','string',array(
					Input::IN_RANGE => [null,255],
					Input::CUSTOM_ERROR_MESSAGES => array(
						Input::ERROR_MISSING => 'Please specify a reason for the changes',
						Input::ERROR_RANGE => 'The reason cannot be longer than @max characters',
					),
				)))->out();
				CoreUtils::CheckStringValidity($reason, "Change reason", INVERSE_PRINTABLE_ASCII_PATTERN);
			}

			if ($adding){
				$AppearanceID = (new Input('ponyid','int',array(
					Input::CUSTOM_ERROR_MESSAGES => array(
						Input::ERROR_MISSING => 'Missing appearance ID',
					)
				)))->out();
				$Appearance = $CGDb->where('id', $AppearanceID)->where('ishuman', $EQG)->getOne('appearances');
				if (empty($Appearance))
					Response::Fail('The specified appearance odes not exist');
				$data['ponyid'] = $AppearanceID;

				// Attempt to get order number of last color group for the appearance
				$LastGroup = \CG\ColorGroups::Get($AppearanceID, '"order"', 'DESC', 1);
				$data['order'] =  !empty($LastGroup['order']) ? $LastGroup['order']+1 : 1;

				$GroupID = $CGDb->insert('colorgroups', $data, 'groupid');
				if (!$GroupID)
					Response::DBError();
				$Group = array('groupid' => $GroupID);
			}
			else $CGDb->where('groupid', $Group['groupid'])->update('colorgroups', $data);

			$origColors = $adding ? null : \CG\ColorGroups::GetColors($Group['groupid']);

			$recvColors = (new Input('Colors','json',array(
				Input::CUSTOM_ERROR_MESSAGES => array(
					Input::ERROR_MISSING => "Missing list of {$color}s",
					Input::ERROR_INVALID => "List of {$color}s is invalid",
				)
			)))->out();
			$colors = array();
			foreach ($recvColors as $part => $c){
				$append = array('order' => $part);
				$index = "(index: $part)";

				if (empty($c['label']))
					Response::Fail("You must specify a $color name $index");
				$label = CoreUtils::Trim($c['label']);
				CoreUtils::CheckStringValidity($label, "$Color $index name", INVERSE_PRINTABLE_ASCII_PATTERN);
				$ll = strlen($label);
				if ($ll < 3 || $ll > 30)
					Response::Fail("The $color name must be between 3 and 30 characters in length $index");
				$append['label'] = $label;

				if (empty($c['hex']))
					Response::Fail("You must specify a $color code $index");
				$hex = CoreUtils::Trim($c['hex']);
				if (!$HEX_COLOR_REGEX->match($hex, $_match))
					Response::Fail("HEX $color is in an invalid format $index");
				$append['hex'] = '#'.strtoupper($_match[1]);

				$colors[] = $append;
			}
			if (!$adding)
				$CGDb->where('groupid', $Group['groupid'])->delete('colors');
			$colorError = false;
			foreach ($colors as $c){
				$c['groupid'] = $Group['groupid'];
				if (!$CGDb->insert('colors', $c)){
					$colorError = true;
					error_log("Database error triggered by user {$currentUser->id} ({$currentUser->name}) while saving colors: ".$CGDb->getLastError());
				}
			}
			if ($colorError)
				Response::Fail("There were some issues while saving some of the colors. Please let the developer know about this error, so he can look into why this might've happened.");

			$colon = !$AppearancePage;
			$outputNames = $AppearancePage;

			if ($adding) $response = array('cgs' => \CG\Appearances::GetColorsHTML($Appearance, NOWRAP, $colon, $outputNames));
			else $response = array('cg' => \CG\ColorGroups::GetHTML($Group['groupid'], null, NOWRAP, $colon, $outputNames));

			$AppearanceID = $adding ? $Appearance['id'] : $Group['ponyid'];
			if ($major){
				Log::Action('color_modify',array(
					'ponyid' => $AppearanceID,
					'reason' => $reason,
				));
				if ($AppearancePage){
					$FullChangesSection = isset($_POST['FULL_CHANGES_SECTION']);
					$response['changes'] = CGUtils::GetChangesHTML(\CG\Updates::Get($AppearanceID), $FullChangesSection);
					if ($FullChangesSection)
						$response['changes'] = str_replace('@',$response['changes'],CGUtils::CHANGES_SECTION);
				}
				else $response['update'] = \CG\Appearances::GetUpdatesHTML($AppearanceID);
			}
			CGUtils::ClearRenderedImages($AppearanceID, array(CGUtils::CLEAR_PALETTE, CGUtils::CLEAR_PREVIEW));

			if (isset($_POST['APPEARANCE_PAGE']))
				$response['cm_img'] = "/cg/v/$AppearanceID.svg?t=".time();
			else $response['notes'] = \CG\Appearances::GetNotesHTML($CGDb->where('id', $AppearanceID)->getOne('appearances'),  NOWRAP);

			$logdata = array();
			if ($adding) Log::Action('cgs',array(
				'action' => 'add',
				'groupid' => $Group['groupid'],
				'ponyid' => $AppearanceID,
				'label' => $data['label'],
				'order' => $data['order'] ?? null,
			));
			else if ($data['label'] !== $Group['label']){
				$logdata['oldlabel'] = $Group['label'];
				$logdata['newlabel'] = $data['label'];
			}

			$origColors = \CG\ColorGroups::StringifyColors($origColors);
			$recvColors = \CG\ColorGroups::StringifyColors($recvColors);
			$colorsChanged = $origColors !== $recvColors;
			if ($colorsChanged){
				$logdata['oldcolors'] = $origColors;
				$logdata['newcolors'] = $recvColors;
			}
			if (!empty($logdata)){
				$logdata['groupid'] = $Group['groupid'];
				$logdata['ponyid'] = $AppearanceID;
				Log::Action('cg_modify', $logdata);
			}

			Response::Done($response);
		}
		else CoreUtils::NotFound();
	}

	// Tag list
	if (regex_match(new RegExp('^tags'),$data)){
		$Pagination = new Pagination("cg/tags", 20, $CGDb->count('tags'));

		CoreUtils::FixPath("/cg/tags/{$Pagination->page}");
		$heading = "Tags";
		$title = "Page $Pagination->page - $heading - $Color Guide";

		$Tags = \CG\Tags::GetFor(null,$Pagination->GetLimit(), true);

		if (isset($_GET['js']))
			$Pagination->Respond(\CG\Tags::GetTagListHTML($Tags, NOWRAP), '#tags tbody');

		$js = array('paginate');
		if (Permission::Sufficient('staff'))
			$js[] = "$do-tags";

		CoreUtils::LoadPage(array(
			'title' => $title,
			'heading' => $heading,
			'view' => "$do-tags",
			'css' => "$do-tags",
			'js' => $js,
		));
	}

	// Change list
	if (regex_match(new RegExp('^changes'),$data)){
		$Pagination = new Pagination("cg/changes", 50, $Database->count('log__color_modify'));

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
		'jquery.autocomplete',
		'handlebars-v3.0.3',
		'Sortable',
		"$do-tags",
		"$do-manage",
	);
	$GUIDE_MANAGE_CSS = array(
		"$do-manage",
	);
	// Appearance pages
	//                                                  [111]             [22]    [3333333333333333]
	if (regex_match(new RegExp('^(?:appearance|v)/(?:.*?(\d+)(?:-.*)?)(?:([sp])?\.(png|svg|json|gpl))?'),$data,$_match)){
		$asFile = !empty($_match[3]);
		$AppearanceID = intval($_match[1], 10);
		$Appearance = $CGDb->where('id', $AppearanceID)->getOne('appearances', $asFile ? 'id,label,cm_dir,ishuman' : null);
		if (empty($Appearance))
			CoreUtils::NotFound();

		if ($Appearance['ishuman'] && !$EQG){
			$EQG = 1;
			$CGPath = '/cg/eqg';
		}
		else if (!$Appearance['ishuman'] && $EQG){
			$EQG = 0;
			$CGPath = '/cg';
		}

		if ($asFile){
			switch ($_match[3]){
				case 'png':
					if (!empty($_match[2])) switch ($_match[2]){
						case "s": CGUtils::RenderSpritePNG($Appearance['id']);
						default: CoreUtils::NotFound();
					}
					CGUtils::RenderAppearancePNG($Appearance);
				case 'svg':
					if (!empty($_match[2])) switch ($_match[2]){
						case "s": CGUtils::RenderSpriteSVG($Appearance['id']);
						case "p": CGUtils::RenderPreviewSVG($Appearance['id']);
						default: CoreUtils::NotFound();
					}
					CGUtils::RenderCMDirectionSVG($Appearance['id'], $Appearance['cm_dir']);
				case 'json': CGUtils::GetSwatchesAI($Appearance); 
				case 'gpl': CGUtils::GetSwatchesInkscape($Appearance); 
			}
			# rendering functions internally call die(), so execution stops above #
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
		if (Permission::Sufficient('staff')){
			$settings['css'] = array_merge($settings['css'], $GUIDE_MANAGE_CSS);
			$settings['js'] = array_merge($settings['js'],$GUIDE_MANAGE_JS);
		}
		CoreUtils::LoadPage($settings);
	}
	// Sprite color inspector
	else if (regex_match(new RegExp('^sprite(?:-colou?rs)?/(\d+)(?:-.*)?$'),$data,$_match)){
		if (!Permission::Sufficient('staff'))
			CoreUtils::NotFound();

		$Appearance = $CGDb->where('id', intval($_match[1], 10))->getOne('appearances', 'id,label');
		if (empty($Appearance))
			CoreUtils::NotFound();

		$Map = CGUtils::GetSpriteImageMap($Appearance['id']);
		if (empty($Map))
			CoreUtils::NotFound();

		$Colors = array();

		foreach (array(0, $Appearance['id']) as $AppearanceID){
			$ColorGroups = \CG\ColorGroups::Get($AppearanceID);
			$SortedColorGroups = array();
			foreach ($ColorGroups as $cg)
				$SortedColorGroups[$cg['groupid']] = $cg;

			$AllColors = \CG\ColorGroups::GetColorsForEach($ColorGroups);
			foreach ($AllColors as $cg){
				foreach ($cg as $c)
					$Colors[] = array(
						'hex' => $c['hex'],
						'label' => $SortedColorGroups[$c['groupid']]['label'].' | '.$c['label'],
					);
			}
		}
		$Colors = array_merge($Colors,
			array(
				array(
					'hex' => '#D8D8D8',
					'label' => 'Mannequin | Outline',
				),
				array(
	                'hex' => '#E6E6E6',
	                'label' => 'Mannequin | Fill',
				),
				array(
	                'hex' => '#BFBFBF',
	                'label' => 'Mannequin | Shadow Outline',
				),
				array(
	                'hex' => '#CCCCCC',
	                'label' => 'Mannequin | Shdow Fill',
				)
			)
		);

		$SafeLabel = \CG\Appearances::GetSafeLabel($Appearance);
		CoreUtils::FixPath("$CGPath/sprite/{$Appearance['id']}-$SafeLabel");

		CoreUtils::LoadPage(array(
			'view' => "$do-sprite",
			'title' => "Sprite of {$Appearance['label']}",
			'css' => "$do-sprite",
			'js' => "$do-sprite",
		));
	}
	// Full guide
	else if ($data === 'full'){
		$GuideOrder = !isset($_REQUEST['alphabetically']) && !$EQG;
		if (!$GuideOrder)
			$CGDb->orderBy('label','ASC');
		$Appearances = \CG\Appearances::Get($EQG,null,'id,label,private');

		if (isset($_REQUEST['ajax']))
			Response::Done(array('html' => CGUtils::GetFullListHTML($Appearances, $GuideOrder, NOWRAP)));

		$js = array();
		if (Permission::Sufficient('staff'))
			$js[] = 'Sortable';
		$js[] = "$do-full";

		CoreUtils::LoadPage(array(
			'title' => "Full List - $Color Guide",
			'view' => "$do-full",
			'css' => "$do-full",
			'js' => $js,
		));
	}

	// Guide page output & display
	$title = '';
	$AppearancesPerPage = UserPrefs::Get('cg_itemsperpage');
	$Ponies = [];
	try {
		$elasticAvail = CoreUtils::ElasticClient()->ping();
	}
	catch (Elasticsearch\Common\Exceptions\NoNodesAvailableException $e){
		$elasticAvail = false;
	}
	if ($elasticAvail){
		$search = new ElasticsearchDSL\Search();

		// Search query exists
		if (!empty($_GET['q']) && mb_strlen(trim($_GET['q'])) > 0){
			$SearchQuery = regex_replace(new RegExp('[^\w\d\s]'),'',trim($_GET['q']));
			$title .= "$SearchQuery - ";

			$multiMatch = new ElasticsearchDSL\Query\MultiMatchQuery(
				['body.tags','body.label^20'],
				$SearchQuery,
				[ 'type' => 'cross_fields' ]
			);
			$search->addQuery($multiMatch);
		}

		$boolquery = new BoolQuery();
		if (Permission::Insufficient('staff'))
			$boolquery->add(new TermQuery('body.private', false), BoolQuery::MUST_NOT);
		$boolquery->add(new TermQuery('body.ishuman', $EQG), BoolQuery::MUST);
		$boolquery->add(new TermQuery('id', 0), BoolQuery::MUST_NOT);
		$search->addQuery($boolquery);

	    $Pagination = new Pagination('cg', $AppearancesPerPage);
		$search = $search->toArray();
		$aearch['query']['function_score'] = [
			'functions' => [
				[
					'field_value_factor' => [
						'field' => 'body.order',
                        'factor' => 1.2,
                        'missing' => 1,
					],
				],
			],
		];
		$search['sort'][] = ['body.order' => 'asc'];
		$search['_source'] = false;
		$search = CGUtils::SearchElastic($search, $Pagination);
		$Pagination->calcMaxPages($search['hits']['total']);

		if (!empty($search['hits']['hits'])){
			$ids = [];
			foreach($search['hits']['hits'] as $hit)
				$ids[] = $hit['_id'];

			$Ponies = $CGDb->where('id IN ('.implode(',', $ids).')')->orderBy('order','ASC')->get('appearances');
		}
	}
	if (!$elasticAvail) {
        $_EntryCount = $CGDb->where('ishuman',$EQG)->where('id != 0')->count('appearances');

        $Pagination = new Pagination('cg', $AppearancesPerPage, $_EntryCount);
        $Ponies = \CG\Appearances::Get($EQG, $Pagination->GetLimit());
	}

	if (isset($_REQUEST['GOFAST'])){
		if (empty($Ponies[0]['id']))
			Response::Fail('The search returned no results.');
		Response::Done(array('goto' => "/cg/v/{$Ponies[0]['id']}-".\CG\Appearances::GetSafeLabel($Ponies[0])));
	}

	CoreUtils::FixPath("$CGPath/{$Pagination->page}".(!empty($Restrictions)?"?q=$SearchQuery":''));
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
	if (Permission::Sufficient('staff')){
		$settings['css'] = array_merge($settings['css'], $GUIDE_MANAGE_CSS);
		$settings['js'] = array_merge($settings['js'], $GUIDE_MANAGE_JS);
	}
	CoreUtils::LoadPage($settings);
