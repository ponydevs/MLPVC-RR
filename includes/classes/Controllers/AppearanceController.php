<?php

namespace App\Controllers;
use ActiveRecord\Table;
use App\Auth;
use App\CGUtils;
use App\CoreUtils;
use App\CSRFProtection;
use App\Cutiemarks;
use App\DB;
use App\Exceptions\MismatchedProviderException;
use App\Exceptions\NoPCGSlotsException;
use App\File;
use App\ImageProvider;
use App\Input;
use App\JSON;
use App\Logs;
use App\Models\Appearance;
use App\Models\CachedDeviation;
use App\Models\Color;
use App\Models\ColorGroup;
use App\Models\Cutiemark;
use App\Models\Logs\MajorChange;
use App\Models\Notification;
use App\Models\PCGSlotHistory;
use App\Models\RelatedAppearance;
use App\Models\Tag;
use App\Models\TagChange;
use App\Models\Tagged;
use App\Models\User;
use App\Notifications;
use App\Pagination;
use App\Permission;
use App\RegExp;
use App\Response;
use App\UploadedFile;
use App\UserPrefs;
use App\Appearances;
use App\Tags;
use App\Users;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use Elasticsearch\Common\Exceptions\ServerErrorResponseException;
use ONGR\ElasticsearchDSL;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\Compound\FunctionScoreQuery;
use ONGR\ElasticsearchDSL\Query\MatchAllQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use Ramsey\Uuid\Uuid;

class AppearanceController extends ColorGuideController {
	public function view($params){
		if ($this->_ownedBy === null)
			$this->_initialize($params);
		$this->_getAppearance($params);
		if ($this->_ownedBy !== null && $this->_appearance->owner_id !== $this->_ownedBy->id){
			$this->_ownedBy = null;
			$this->_isOwnedByUser = false;
			$this->_initCGPath();
		}

		if ($this->_appearance->hidden())
			CoreUtils::noPerm();

		$SafeLabel = $this->_appearance->getURLSafeLabel();
		CoreUtils::fixPath("$this->_cgPath/v/{$this->_appearance->id}-$SafeLabel");
		$heading = $this->_appearance->label;

		$settings = [
			'title' => "$heading - Color Guide",
			'heading' => $heading,
			'css' => ['pages/colorguide/guide', true],
			'js' => ['jquery.ctxmenu', 'pages/colorguide/guide', true],
			'import' => [
				'Appearance' => $this->_appearance,
				'EQG' => $this->_EQG,
				'isOwner' => false,
			],
		];
		if (!empty($this->_appearance->owner_id)){
			$settings['import']['Owner'] = $this->_ownedBy;
			$settings['import']['isOwner'] = $this->_isOwnedByUser;
		}
		else $settings['import']['Changes'] = MajorChange::get($this->_appearance->id, null);
		if ($this->_isOwnedByUser || Permission::sufficient('staff')){
			$settings['css'] = array_merge($settings['css'], self::GUIDE_MANAGE_CSS);
			$settings['js'] = array_merge($settings['js'], self::GUIDE_MANAGE_JS);
		}
		CoreUtils::loadPage('ColorGuideController::appearance', $settings);
	}

	public function viewPersonal($params){
		$this->_initPersonal($params);

		$this->view($params);
	}

	public function tagChanges($params){
		// TODO Finish feature
		CoreUtils::notFound();

		if (Permission::insufficient('staff'))
			Response::fail();

		$this->_initialize($params);
		$this->_getAppearance($params);

		$totalChangeCount = TagChange::count(['appearance_id' => $this->_appearance->id]);
		$Pagination = new Pagination("{$this->_cgPath}/tag-changes/{$this->_appearance->getURLSafeLabel()}", 25, $totalChangeCount);
	}

	public function asFile($params, User $Owner = null){
		if ($Owner === null)
			$this->_initialize($params);
		$this->_getAppearance($params);

		if ($this->_appearance->hidden())
			CoreUtils::notFound();

		switch ($params['ext']){
			case 'png':
				switch ($params['type']){
					case 's': CGUtils::renderSpritePNG($this->_cgPath, $this->_appearance->id, $_GET['s'] ?? null);
					case 'p':
					default: CGUtils::renderAppearancePNG($this->_cgPath, $this->_appearance);
				}
			break;
			case 'svg':
				if (!empty($params['type'])) switch ($params['type']){
					case 's': CGUtils::renderSpriteSVG($this->_cgPath, $this->_appearance->id);
					case 'p': CGUtils::renderPreviewSVG($this->_cgPath, $this->_appearance);
					case 'f': CGUtils::renderCMFacingSVG($this->_cgPath, $this->_appearance);
					default: CoreUtils::notFound();
				}
			case 'json': CGUtils::getSwatchesAI($this->_appearance);
			case 'gpl': CGUtils::getSwatchesInkscape($this->_appearance);
		}
		# rendering functions internally call die(), so execution stops above #

		CoreUtils::notFound();
	}

	public function personalAsFile($params){
		$this->_initPersonal($params);

		$this->asFile($params, $this->_ownedBy);
	}

	public function action($params){
		$this->_initPersonal($params, false);

		if (!Auth::$signed_in)
			Response::fail();

		$action = $params['action'];
		$creating = $action === 'make';

		if ($creating){
			if (!$this->_personalGuide && Permission::insufficient('staff'))
				Response::fail('You don\'t have permission to add appearances to the official Color Guide');

			if ($this->_personalGuide){
				$availPoints = Auth::$user->getPCGAvailablePoints(false);
				if ($availPoints < 10){
					$remain = Users::calculatePersonalCGNextSlot(Auth::$user->getPCGAppearanceCount());
					Response::fail("You don\'t have enough slots to create another appearance. Delete other ones or finish $remain more ".CoreUtils::makePlural('request',$remain).'. Visit <a href="/u">your profile</a> and click the <strong class="color-darkblue"><span class="typcn typcn-info-large"></span> What?</strong> button next to the Personal Color Guide heading for more information.');
				}
				if (!UserPrefs::get('a_pcgmake'))
					Response::fail(Appearances::PCG_APPEARANCE_MAKE_DISABLED);
			}
		}
		else {
			$this->_getAppearance($params);

			if (!$this->_isOwnedByUser && Permission::insufficient('staff'))
				Response::fail();
		}

		$this->_execAction($action, $creating);
	}

	protected function _execAction($action, $creating = null, $noResponse = false){
		switch ($action){
			case 'get':
				Response::done([
					'label' => $this->_appearance->label,
					'notes' => $this->_appearance->notes_src,
					'private' => $this->_appearance->private,
				]);
			break;
			case 'set':
			case 'make':
				/** @var $data array */
				$data = [
					'ishuman' => $this->_personalGuide ? null : $this->_EQG,
				];

				$label = (new Input('label','string', [
					Input::IN_RANGE => [2,70],
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Appearance name is missing',
						Input::ERROR_RANGE => 'Appearance name must be beetween @min and @max characters long',
					]
				]))->out();
				CoreUtils::checkStringValidity($label, 'Appearance name', INVERSE_PRINTABLE_ASCII_PATTERN);
				$dupe = Appearance::find_dupe($creating, $this->_personalGuide, [
					'owner_id' => Auth::$user->id,
					'ishuman' => $data['ishuman'],
					'label' => $label,
					'id' => $creating ? null : $this->_appearance->id,
				]);
				if (!empty($dupe)){
					if ($this->_personalGuide)
						Response::fail('You already have an appearance with the same name in your Personal Color Guide');

					Response::fail("An appearance <a href='{$dupe->toURL()}' target='_blank'>already esists</a> in the ".($this->_EQG?'EQG':'Pony').' guide with this exact name. Consider adding an identifier in backets or choosing a different name.');
				}
				if ($creating || $label !== $this->_appearance->label)
					$data['label'] = $label;

				$notes = (new Input('notes','text', [
					Input::IS_OPTIONAL => true,
					Input::IN_RANGE => $creating || $this->_appearance->id !== 0 ? [null,1000] : null,
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_RANGE => 'Appearance notes cannot be longer than @max characters',
					]
				]))->out();
				if ($notes !== null){
					CoreUtils::checkStringValidity($notes, 'Appearance notes', INVERSE_PRINTABLE_ASCII_PATTERN);
					if ($creating || $notes !== $this->_appearance->notes_src)
						$data['notes_src'] = $notes;
				}
				else $data['notes_src'] = null;

				$data['private'] = isset($_POST['private']);

				if ($creating){
					if ($this->_personalGuide || Permission::insufficient('staff')){
						$data['owner_id'] = Auth::$user->id;
					}
					if (empty($data['owner_id'])){
						$biggestOrder = DB::$instance->disableAutoClass()->where('ishuman', $data['ishuman'])->getOne('appearances','MAX("order") as "order"');
						$data['order'] = ($biggestOrder['order'] ?? 0)+1;
					}
				}
				else if ($data['private']){
					$data['last_cleared'] = date('c');
				}

				/** @var $newAppearance Appearance */
				if ($creating){
					$newAppearance = Appearance::create($data);
					$newAppearance->reindex();
				}
				else {
					$olddata = $this->_appearance->to_array();
					$this->_appearance->update_attributes($data);
					$this->_appearance->reindex();
				}

				$EditedAppearance = $creating ? $newAppearance : $this->_appearance;

				if ($creating){
					$data['id'] = $newAppearance->id;
					$response = [
						'message' => 'Appearance added successfully',
						'goto' => $newAppearance->toURL(),
					];
					$usetemplate = isset($_POST['template']);
					if ($usetemplate){
						try {
							$newAppearance->applyTemplate();
						}
						catch (\Exception $e){
							$response['message'] .= ', but applying the template failed';
							$response['info'] = 'The common color groups could not be added.<br>Reason: '.$e->getMessage();
							$usetemplate = false;
						}
					}

					Logs::logAction('appearances', [
						'action' => 'add',
						'id' => $newAppearance->id,
						'order' => $newAppearance->order,
						'label' => $newAppearance->label,
						'notes' => $newAppearance->notes_src,
						'ishuman' => $newAppearance->ishuman,
						'usetemplate' => $usetemplate,
						'private' => $newAppearance->private,
						'owner_id' => $newAppearance->owner_id,
					]);

					if ($newAppearance->owner_id !== null){
						PCGSlotHistory::record($newAppearance->owner_id, 'appearance_add', null, [
							'id' => $newAppearance->id,
							'label' => $newAppearance->label,
						]);
						$newAppearance->owner->syncPCGSlotCount();
					}

					Response::done($response);
				}

				$this->_appearance->clearRenderedImages([Appearance::CLEAR_PALETTE, Appearance::CLEAR_PREVIEW]);

				if (!$creating){
					$diff = [];
					foreach (['label' => true, 'notes_src' => 'notes', 'private' => true, 'owner_id' => true] as $orig => $mapped){
						$key = $mapped === true ? $orig : $mapped;
						if ($EditedAppearance->{$orig} !== $olddata[$orig]){
							$diff["old$key"] = $olddata[$orig];
							$diff["new$key"] = $EditedAppearance->{$orig};
						}
					}
					if (!empty($diff)) Logs::logAction('appearance_modify', [
						'appearance_id' => $this->_appearance->id,
						'changes' => JSON::encode($diff),
					]);
				}

				$response = [];
				if (!$this->_appearancePage){
					$response['label'] = $EditedAppearance->label;
					if (isset($olddata['label']) && $olddata['label'] !== $this->_appearance->label)
						$response['newurl'] = $EditedAppearance->toURL();
					$response['notes'] = $EditedAppearance->getNotesHTML(NOWRAP);
				}

				Response::done($response);
			break;
			case 'delete':
				if ($this->_appearance->id === 0)
					Response::fail('This appearance cannot be deleted');

				$Tagged = Tags::getFor($this->_appearance->id, null, true);

				if (!DB::$instance->where('id', $this->_appearance->id)->delete(Appearance::$table_name))
					Response::dbError();

				if ($this->_appearance->owner_id === null){
					try {
						CoreUtils::elasticClient()->delete($this->_appearance->toElasticArray(true));
					}
					catch (Missing404Exception $e){
						$message = JSON::decode($e->getMessage());

						// Eat error if appearance was not indexed
						if (!isset($message['found']) || $message['found'] !== false)
							throw $e;
					}
					catch (NoNodesAvailableException $e){
						CoreUtils::error_log('ElasticSearch server was down when server attempted to remove appearance '.$this->_appearance->id);
					}
				}

				if (!empty($Tagged))
					foreach($Tagged as $tag)
						$tag->updateUses();

				$fpath = SPRITE_PATH."{$this->_appearance->id}.png";
				CoreUtils::deleteFile($fpath);

				$this->_appearance->clearRenderedImages();

				Logs::logAction('appearances', [
					'action' => 'del',
					'id' => $this->_appearance->id,
					'order' => $this->_appearance->order,
					'label' => $this->_appearance->label,
					'notes' => $this->_appearance->notes_src,
					'ishuman' => $this->_appearance->ishuman,
					'added' => $this->_appearance->added,
					'private' => $this->_appearance->private,
					'owner_id' => $this->_appearance->owner_id,
				]);

				/** @var $spriteColorNotifs Notification[] */
				$spriteColorNotifs = DB::$instance
					->where('type', 'sprite-colors')
					->where("data->'appearance_id'", $this->_appearance->id)
					->get(Notification::$table_name);
				foreach ($spriteColorNotifs as $notif)
					$notif->safeMarkRead();

				if ($this->_appearance->owner_id !== null){
					PCGSlotHistory::record($this->_appearance->owner_id, 'appearance_del', null, [
						'id' => $this->_appearance->id,
						'label' => $this->_appearance->label,
					]);
					$this->_appearance->owner->syncPCGSlotCount();
				}

				Response::success('Appearance removed');
			break;
			case 'getcgs':
				$cgs = $this->_appearance->color_groups;
				if (empty($cgs))
					Response::fail('This appearance does not have any color groups');
				if (\count($cgs) < 2)
					Response::fail('An appearance needs at least 2 color groups before you can change their order');
				foreach ($cgs as $i => $cg)
					$cgs[$i] = $cg->to_array([
						'only' => ['id','label'],
					]);
				Response::done(['cgs' => $cgs]);
			break;
			case 'setcgs':
				/** @var $order int[] */
				$order = (new Input('cgs','int[]', [
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Color group order data missing',
						Input::ERROR_INVALID => 'Color group order data (@value) is invalid',
					]
				]))->out();
				$oldCGs = DB::$instance->where('appearance_id', $this->_appearance->id)->get('color_groups');
				$possibleIDs = [];
				foreach ($oldCGs as $cg)
					$possibleIDs[$cg->id] = true;
				foreach ($order as $i => $GroupID){
					if (empty($possibleIDs[$GroupID]))
						Response::fail("There's no group with the ID of $GroupID on this appearance");

					DB::$instance->where('id', $GroupID)->update('color_groups', ['order' => $i]);
				}
				Table::clear_cache();
				$newCGs = DB::$instance->where('appearance_id', $this->_appearance->id)->get('color_groups');

				$this->_appearance->clearRenderedImages([Appearance::CLEAR_PALETTE, Appearance::CLEAR_PREVIEW]);

				$oldCGs = CGUtils::stringifyColorGroups($oldCGs);
				$newCGs = CGUtils::stringifyColorGroups($newCGs);
				if ($oldCGs !== $newCGs) Logs::logAction('cg_order', [
					'appearance_id' => $this->_appearance->id,
					'oldgroups' => $oldCGs,
					'newgroups' => $newCGs,
				]);

				Response::done(['cgs' => $this->_appearance->getColorsHTML(NOWRAP, !$this->_appearancePage, $this->_appearancePage)]);
			break;
			case 'delsprite':
			case 'getsprite':
			case 'setsprite':
				$finalpath = SPRITE_PATH.$this->_appearance->id.'.png';

				switch ($action){
					case 'setsprite':
						if ($this->_appearance->owner_id === Auth::$user->id && !UserPrefs::get('a_pcgsprite'))
							Response::fail('You are not allowed to upload sprite images on your own PCG appearances');
						CGUtils::processUploadedImage('sprite', $finalpath, ['image/png'], [300], [700, 300]);
						$this->_appearance->clearRenderedImages();

						$this->_appearance->checkSpriteColors();
					break;
					case 'delsprite':
						if (empty($this->_appearance->getSpriteURL())){
							if ($noResponse)
								return;
							Response::fail('No sprite file found');
						}

						if (!CoreUtils::deleteFile($finalpath))
							Response::fail('File could not be deleted');
						$this->_appearance->clearRenderedImages();
						Appearances::clearSpriteColorIssueNotifications($this->_appearance->id, 'del', null);

						if ($noResponse)
							return;

						Response::done(['sprite' => DEFAULT_SPRITE]);
					break;
				}

				Response::done(['path' => "/cg/v/{$this->_appearance->id}s.png?t=".filemtime($finalpath)]);
			break;
			case 'getrelations':
				if (!empty($this->_appearance->owner_id))
					Response::fail('Relations are unavailable for appearances in personal guides');

				$RelatedAppearances = $this->_appearance->related_appearances;
				$RelatedAppearanceIDs = [];
				foreach ($RelatedAppearances as $p)
					$RelatedAppearanceIDs[$p->target_id] = $p->is_mutual;

				$Appearances = DB::$instance->disableAutoClass()->where('ishuman', $this->_EQG)->where('"id" NOT IN (0,'.$this->_appearance->id.')')->orderBy('label')->get('appearances',null,'id,label');

				$Sorted = [
					'unlinked' => [],
					'linked' => [],
				];
				foreach ($Appearances as $a){
					$linked = isset($RelatedAppearanceIDs[$a['id']]);
					if ($linked)
						$a['mutual'] = $RelatedAppearanceIDs[$a['id']];
					$Sorted[$linked ? 'linked' : 'unlinked'][] = $a;
				}

				Response::done($Sorted);
			break;
			case 'setrelations':
				if (!empty($this->_appearance->owner_id))
					Response::fail('Relations are unavailable for appearances in personal guides');

				/** @var $AppearanceIDs int[] */
				$AppearanceIDs = (new Input('ids','int[]', [
					Input::IS_OPTIONAL => true,
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_INVALID => 'Appearance ID list is invalid',
					]
				]))->out();
				/** @var $MutualIDs int[] */
				$MutualIDs = (new Input('mutuals','int[]', [
					Input::IS_OPTIONAL => true,
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_INVALID => 'Mutial relation ID list is invalid',
					]
				]))->out();

				$appearances = [];
				if (!empty($AppearanceIDs))
					foreach ($AppearanceIDs as $id)
						$appearances[$id] = true;

				$mutuals = [];
				if (!empty($MutualIDs))
					foreach ($MutualIDs as $id)
						$mutuals[$id] = true;

				$this->_appearance->clearRelations();
				if (!empty($appearances))
					foreach ($appearances as $id => $_)
						RelatedAppearance::make($this->_appearance->id, $id, isset($mutuals[$id]));

				$out = [];
				if ($this->_appearancePage)
					$out['section'] = $this->_appearance->getRelatedHTML();
				Response::done($out);
			break;
			case 'getcms':
				$CMs = Cutiemarks::get($this->_appearance);
				foreach ($CMs as &$CM)
					$CM = $CM->to_js_response();
				unset($CM);

				$ProcessedCMs = Cutiemarks::get($this->_appearance);

				Response::done(['cms' => $CMs, 'preview' => Cutiemarks::getListForAppearancePage($ProcessedCMs, NOWRAP)]);
			break;
			case 'setcms':
				$GrabCMs = Cutiemarks::get($this->_appearance);
				/** @var $CurrentCMs Cutiemark[] */
				$CurrentCMs = [];
				foreach ($GrabCMs as $cm)
					$CurrentCMs[$cm->id] = $cm;
				/** @var $data array */
				$data = (new Input('CMData','json',[
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Cutie mark data is missing',
						Input::ERROR_INVALID => 'Cutie mark data (@value) is invalid',
					]
				]))->out();
				if (\count($data) > 4)
					Response::fail('Appearances can only have a maximum of 4 cutie marks.');
				/** @var $NewCMs Cutiemark[] */
				$NewCMs = [];
				$NewSVGs = [];
				$NewIDs = [];
				$labels = [];
				foreach ($data as $i => $item){
					if (isset($item['id'])){
						$cm = Cutiemark::find($item['id']);
						if (empty($cm))
							Response::fail("The cutie mark you're trying to update (#{$item['id']}) does not exist");
						$NewIDs[] = $cm->id;
					}
					else $cm = new Cutiemark([
						'appearance_id' => $this->_appearance->id,
					]);

					$svg_data_missing = empty($item['svgdata']);
					if ($cm->id === null || !$svg_data_missing){
						if ($svg_data_missing)
							Response::fail('SVG data is missing');
						if (CoreUtils::stringSize($item['svgdata']) > UploadedFile::SIZES['megabyte'])
							Response::fail('SVG data exceeds the maximum size of 1 MB');
						if (CoreUtils::validateSvg($item['svgdata']) !== Input::ERROR_NONE)
							Response::fail('SVG data is invalid');
						$svgdata = $item['svgdata'];
					}
					else $svgdata = null;

					$label = null;
					if (isset($item['label'])){
						$item['label'] = CoreUtils::trim($item['label']);
						if (!empty($item['label'])){
							CoreUtils::checkStringValidity($item['label'], 'Cutie Mark label', INVERSE_PRINTABLE_ASCII_PATTERN);
							if (Input::checkStringLength($item['label'], [1, 32]) === Input::ERROR_RANGE)
								Response::fail('Cutie mark label must be between 1 and 32 chars long');
							if (isset($labels[$item['label']]))
								Response::fail('Cutie mark labels must be unique within an appearance');
							else $labels[$item['label']] = true;
							$label = $item['label'];
						}
					}
					$cm->label = $label;

					if (isset($item['facing'])){
						$facing = CoreUtils::trim($item['facing']);
						if (empty($facing))
							$facing = null;
						else if (!\in_array($facing,Cutiemarks::VALID_FACING_VALUES,true))
							Response::fail('Body orientation "'.CoreUtils::escapeHTML($facing).'" is invalid');
					}
					else $facing = null;
					$cm->facing = $facing;

					switch ($item['attribution']){
						case 'deviation':
							if (empty($item['deviation']))
								Response::fail('Deviation link is missing');

							try {
								$Image = new ImageProvider(CoreUtils::trim($item['deviation']), ImageProvider::PROV_DEVIATION, true);
								/** @var $deviation CachedDeviation */
								$deviation = $Image->extra;
							}
							catch (MismatchedProviderException $e){
								Response::fail('The link must point to a DeviantArt submission, '.$e->getActualProvider().' links are not allowed');
							}
							catch (\Exception $e){ Response::fail('Error while checking deviation link: '.$e->getMessage()); }

							if (empty($deviation))
								Response::fail('The provided deviation could not be fetched');
							$cm->favme = $deviation->id;
							$contributor = Users::get($deviation->author, 'name');
							if (empty($contributor))
								Response::fail("The provided deviation's creator could not be fetched");
							$cm->contributor_id = $contributor->id;
						break;
						case 'user':
							global $USERNAME_REGEX;
							if (empty($item['username']))
								Response::fail('Username is missing');
							if (!preg_match($USERNAME_REGEX, $item['username']))
								Response::fail("Username ({$item['username']}) is invalid");
							$contributor = Users::get($item['username'], 'name');
							if (empty($contributor))
								Response::fail("The provided deviation's creator could not be fetched");
							$cm->favme = null;
							$cm->contributor_id = $contributor->id;
						break;
						case 'none':
							$cm->favme = null;
							$cm->contributor_id = null;
						break;
						default:
							Response::fail('The specified attribution method is invalid');
					}

					if (!isset($item['rotation']))
						Response::fail('Preview rotation amount is missing');
					if (!is_numeric($item['rotation']))
						Response::fail('Preview rotation must be a number');
					$rotation = (int) $item['rotation'];
					if (abs($rotation) > 45)
						Response::fail('Preview rotation must be between -45 and 45');
					$cm->rotation = $rotation;

					$NewCMs[$i] = $cm;
					$NewSVGs[$i] = $svgdata;
				}

				if (!empty($NewCMs)){
					CoreUtils::createFoldersFor(Cutiemark::SOURCE_FOLDER);
					foreach ($NewCMs as $i => $cm){
						if (!$cm->save())
							Response::dbError("Saving cutie mark (index $i) failed");

						if ($NewSVGs[$i] !== null){
							if (false !== File::put($cm->getSourceFilePath(), $NewSVGs[$i])){
								CoreUtils::deleteFile($cm->getTokenizedFilePath());
								CoreUtils::deleteFile($cm->getRenderedFilePath());
								continue;
							}

							Response::fail("Saving SVG data for cutie mark (index $i) failed");
						}
					}

					$RemovedIDs = CoreUtils::array_subtract(array_keys($CurrentCMs), $NewIDs);
					if (!empty($RemovedIDs)){
						foreach ($RemovedIDs as $removedID)
							$CurrentCMs[$removedID]->delete();
					}

					$CutieMarks = Cutiemarks::get($this->_appearance);
					$olddata = Cutiemarks::convertDataForLogs($CurrentCMs);
					$newdata = Cutiemarks::convertDataForLogs($CutieMarks);
					if ($olddata !== $newdata)
						Logs::logAction('cm_modify',[
							'appearance_id' => $this->_appearance->id,
							'olddata' => $olddata,
							'newdata' => $newdata,
						]);
				}
				else {
					foreach ($CurrentCMs as $cm)
						$cm->delete();

					$this->_appearance->clearRenderedImages([Appearance::CLEAR_CMDIR]);

					Logs::logAction('cm_delete',[
						'appearance_id' => $this->_appearance->id,
						'data' => Cutiemarks::convertDataForLogs($CurrentCMs),
					]);

					$CutieMarks = [];
				}

				$data = [];
				if ($this->_appearancePage && !empty($CutieMarks))
					$data['html'] = Cutiemarks::getListForAppearancePage(Cutiemarks::get($this->_appearance));
				Response::done($data);
			break;
			case 'clear-cache':
				if (!$this->_appearance->clearRenderedImages())
					Response::fail('Cache could not be purged');

				if ($noResponse)
					return;

				Response::success('Cached images have been removed, they will be re-generated on the next request');
			break;
			case 'gettags':
			case 'settags':
				if ($this->_appearance->owner_id !== null)
					Response::fail('Tagging is unavailable for appearances in personal guides');

				if ($this->_appearance->id === 0)
					Response::fail('This appearance cannot be tagged');

				if ($action === 'gettags')
					Response::done([ 'tags' => $this->_appearance->getTagsAsText() ]);

				$tags = (new Input('tags','string',[
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'List of tags is missing',
						Input::ERROR_INVALID => 'List of tags is invalid',
					]
				]))->out();
				$this->_appearance->processTagChanges($tags, $this->_EQG);
				$this->_appearance->updateIndex();

				Response::done();
			break;
			case 'applytemplate':
				try {
					$this->_appearance->applyTemplate();
				}
				catch (\Exception $e){
					Response::fail('Applying the template failed. Reason: '.$e->getMessage());
				}

				Response::done(['cgs' => $this->_appearance->getColorsHTML(NOWRAP, !$this->_appearancePage, $this->_appearancePage)]);
			break;
			case 'selectiveclear':
				$wipe_cache = (new Input('wipe_cache','bool',[
					Input::IS_OPTIONAL => true,
				]))->out();
				if ($wipe_cache)
					$this->_execAction('clear-cache',null,true);

				$wipe_cm_tokenized = (new Input('wipe_cm_tokenized','bool',[
					Input::IS_OPTIONAL => true,
				]))->out();
				if ($wipe_cm_tokenized){
					foreach ($this->_appearance->cutiemarks as $cm)
						CoreUtils::deleteFile($cm->getTokenizedFilePath());
				}

				$wipe_cm_source = (new Input('wipe_cm_source','bool',[
					Input::IS_OPTIONAL => true,
				]))->out();
				if ($wipe_cm_source){
					foreach ($this->_appearance->cutiemarks as $cm)
						CoreUtils::deleteFile($cm->getSourceFilePath());
				}

				$wipe_sprite = (new Input('wipe_sprite','bool',[
					Input::IS_OPTIONAL => true,
				]))->out();
				if ($wipe_sprite)
					$this->_execAction('delsprite',null,true);

				$wipe_colors = (new Input('wipe_colors','string',[
					Input::IS_OPTIONAL => true,
				]))->out();
				switch ($wipe_colors){
					case 'color_hex':
						if ($this->_appearance->hasColors(true)){
							/** @noinspection NestedPositiveIfStatementsInspection */
							if (!DB::$instance->query('UPDATE colors SET hex = null WHERE group_id IN (SELECT id FROM color_groups WHERE appearance_id = ?)', [$this->_appearance->id]))
								Response::dbError();
						}
					break;
					case 'color_all':
						if ($this->_appearance->hasColors()){
							/** @noinspection NestedPositiveIfStatementsInspection */
							if (!DB::$instance->query('DELETE FROM colors WHERE group_id IN (SELECT id FROM color_groups WHERE appearance_id = ?)', [$this->_appearance->id]))
								Response::dbError();
						}
					break;
					case 'all':
						if (ColorGroup::exists(['conditions' => ['appearance_id = ?', $this->_appearance->id]])){
							/** @noinspection NestedPositiveIfStatementsInspection */
							if (!DB::$instance->query('DELETE FROM color_groups WHERE appearance_id = ?', [$this->_appearance->id]))
								Response::dbError();
						}
					break;
				}

				if (empty($this->_appearance->owner_id)){
					$wipe_tags = (new Input('wipe_tags','bool',[
						Input::IS_OPTIONAL => true,
					]))->out();
					if ($wipe_tags && !empty($this->_appearance->tagged)){
						if (!DB::$instance->where('appearance_id', $this->_appearance->id)->delete('tagged'))
							Response::dbError('Failed to wipe tags');
						foreach ($this->_appearance->tagged as $tag)
							Tags::updateUses($tag->tag_id);
					}
				}

				$update = ['last_cleared' => date('c')];

				$wipe_notes = (new Input('wipe_notes','bool',[
					Input::IS_OPTIONAL => true,
				]))->out();
				if ($wipe_notes)
					$update['notes'] = null;

				$mkpriv = (new Input('mkpriv','bool',[
					Input::IS_OPTIONAL => true,
				]))->out();
				if ($mkpriv)
					$update['private'] = 1;

				$reset_priv_key = (new Input('reset_priv_key','bool',[
					Input::IS_OPTIONAL => true,
				]))->out();
				if ($reset_priv_key)
					$update['token'] = Uuid::uuid4();

				if (!empty($update))
					DB::$instance->where('id', $this->_appearance->id)->update('appearances',$update);

				Response::done();
			break;
			default: CoreUtils::notFound();
		}
	}

	public function sprite($params){
		if (Permission::insufficient('member'))
			CoreUtils::noPerm();

		$this->_getAppearance($params);
		if ($this->_appearance->owner_id !== Auth::$user->id && Permission::insufficient('staff'))
			CoreUtils::noPerm();

		if ($this->_appearance->owner_id !== null)
			$params['name'] = $this->_appearance->owner->name;
		$this->_initPersonal($params, false);

		$Map = CGUtils::getSpriteImageMap($this->_appearance->id);
		if (empty($Map))
			CoreUtils::notFound();

		[$Colors,$ColorGroups,$AllColors] = $this->_appearance->getSpriteRelevantColors();

		$SafeLabel = $this->_appearance->getURLSafeLabel();
		CoreUtils::fixPath("{$this->_cgPath}/sprite/{$this->_appearance->id}-$SafeLabel");

		CoreUtils::loadPage('ColorGuideController::sprite', [
			'title' => "Sprite of {$this->_appearance->label}",
			'css' => [true],
			'js' => [true],
			'import' => [
				'Appearance' => $this->_appearance,
				'ColorGroups' => $ColorGroups,
				'Colors' => $Colors,
				'AllColors' => $AllColors,
				'Map' => $Map,
				'Owner' => $this->_appearance->owner,
			],
		]);
	}

	public function spriteColorCheckup(){
		if (Permission::insufficient('staff'))
			Response::fail();

		ini_set('max_execution_time', '0');

		$SpriteDir = new \DirectoryIterator(SPRITE_PATH);
		foreach ($SpriteDir as $item){
			if ($item->isDot())
				continue;

			$id = (int)preg_replace(new RegExp('\..+$'),'',$item->getFilename());
			$Appearance = Appearance::find($id);
			if (empty($Appearance))
				continue;

			$Appearance->checkSpriteColors();
		}

		Response::success('Checkup finished');
	}
}
