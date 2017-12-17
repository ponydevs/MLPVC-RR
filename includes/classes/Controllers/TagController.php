<?php

namespace App\Controllers;
use App\CGUtils;
use App\CoreUtils;
use App\DB;
use App\Input;
use App\Models\Appearance;
use App\Models\Tag;
use App\Models\Tagged;
use App\Pagination;
use App\Permission;
use App\Response;
use App\Appearances;
use App\Tags;

class TagController extends ColorGuideController {
	public function list(){
		$Pagination = new Pagination('cg/tags', 20, DB::$instance->count('tags'));

		CoreUtils::fixPath("/cg/tags/{$Pagination->page}");
		$heading = 'Tags';
		$title = "Page $Pagination->page - $heading - Color Guide";

		$Tags = Tags::getFor(null,$Pagination->getLimit(), true);

		$Pagination->respondIfShould(Tags::getTagListHTML($Tags, NOWRAP), '#tags tbody');

		$js = ['paginate'];
		if (Permission::sufficient('staff'))
			$js[] = true;

		CoreUtils::loadPage('ColorGuideController::tagList', [
			'title' => $title,
			'heading' => $heading,
			'css' => [true],
			'js' => $js,
			'import' => [
				'Tags' => $Tags,
				'Pagination' => $Pagination,
			],
		]);
	}

	public function autocomplete(){
		global $TAG_NAME_REGEX;

		if (!Permission::sufficient('staff'))
			Response::fail();

		$except = (new Input('not','int', [Input::IS_OPTIONAL => true]))->out();
		if ((new Input('action','string', [Input::IS_OPTIONAL => true]))->out() === 'synon'){
			if ($except !== null)
				DB::$instance->where('id',$except);
			/** @var $Tag Tag */
			$Tag = DB::$instance->where('"synonym_of" IS NOT NULL')->getOne('tags');
			if (!empty($Tag))
				Response::fail("This tag is already a synonym of <strong>{$Tag->synonym->name}</strong>.<br>Would you like to remove the synonym?", ['undo' => true]);
		}

		$viaAutocomplete = !empty($_GET['s']);
		$limit = null;
		$cols = 'id, name, type';
		if ($viaAutocomplete){
			if (!preg_match($TAG_NAME_REGEX, $_GET['s']))
				CGUtils::autocompleteRespond('[]');

			$query = CoreUtils::trim(strtolower($_GET['s']));
			$TagCheck = CGUtils::normalizeEpisodeTagName($query);
			if ($TagCheck !== false)
				$query = $TagCheck;
			DB::$instance->where('name',"%$query%",'LIKE');
			$limit = 5;
			$cols = "id, name, 'typ-'||type as type";
			DB::$instance->orderBy('uses','DESC');
		}
		else DB::$instance->orderBy('type')->where('"synonym_of" IS NULL');

		if ($except !== null)
			DB::$instance->where('id',$except,'!=');

		$Tags = DB::$instance->disableAutoClass()->orderBy('name')->get('tags',$limit,"$cols, uses, synonym_of");
		if ($viaAutocomplete){
			foreach ($Tags as &$t){
				if (empty($t['synonym_of']))
					continue;
				$Syn = Tag::find($t['synonym_of']);
				if (!empty($Syn))
					$t['synonym_target'] = $Syn->name;
			}
			unset($t);
		}

		CGUtils::autocompleteRespond(empty($Tags) ? '[]' : $Tags);
	}

	public function recountUses(){
		if (Permission::insufficient('staff'))
			Response::fail();

		/** @var $tagIDs int[] */
		$tagIDs = (new Input('tagids','int[]', [
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Missing list of tags to update',
				Input::ERROR_INVALID => 'List of tags is invalid',
			]
		]))->out();
		$counts = [];
		$updates = 0;
		foreach ($tagIDs as $tid){
			if (Tags::getActual($tid,'id',RETURN_AS_BOOL)){
				$result = Tags::updateUses($tid, true);
				if ($result['status'])
					$updates++;
				$counts[$tid] = $result['count'];
			}
		}

		Response::success(
			(
				!$updates
				? 'There was no change in the tag usage counts'
				: "$updates tag".($updates!==1?"s'":"'s").' use count'.($updates!==1?'s were':' was').' updated'
			),
			['counts' => $counts]
		);
	}

	public function action($params){
		$this->_initialize($params);
		if (Permission::insufficient('staff'))
			CoreUtils::noPerm();

		$action = $params['action'];
		$adding = $action === 'make';

		if (!$adding){
			if (!isset($params['id']))
				Response::fail('Missing tag ID');
			$TagID = \intval($params['id'], 10);
			$Tag = Tag::find($TagID);
			if (empty($Tag))
				Response::fail('This tag does not exist');
		}

		$data = [];

		switch ($action){
			case 'get':
				Response::done($Tag->to_array());
			case 'del':
				$AppearanceID = CGUtils::validateAppearancePageID();

				$tid = $Tag->synonym_of ?? $Tag->id;
				$Uses = Tagged::by_tag($tid);
				$UseCount = \count($Uses);
				if (!isset($_POST['sanitycheck']) && $UseCount > 0)
					Response::fail('<p>This tag is currently used on '.CoreUtils::makePlural('appearance',$UseCount,PREPEND_NUMBER).'</p><p>Deleting will <strong class="color-red">permanently remove</strong> the tag from those appearances!</p><p>Are you <em class="color-red">REALLY</em> sure about this?</p>', ['confirm' => true]);

				$Tag->delete();

				if (!empty(CGUtils::GROUP_TAG_IDS_ASSOC[$this->_EQG?'eqg':'pony'][$Tag->id]))
					Appearances::getSortReorder($this->_EQG);
				foreach ($Uses as $use)
					$use->appearance->updateIndex();

				if ($AppearanceID !== null && $Tag->type === 'ep'){
					$Appearance = Appearance::find($AppearanceID);
					$resp = [
						'needupdate' => true,
						'eps' => $Appearance->getRelatedEpisodesHTML($this->_EQG),
					];
				}
				else $resp = null;
				Response::success('Tag deleted successfully', $resp);
			break;
			case 'unsynon':
				if ($Tag->synonym_of === null)
					Response::done();

				$keep_tagged = isset($_POST['keep_tagged']);
				$uses = 0;
				if (!empty($Tag->synonym)){
					$TargetTagged = Tagged::by_tag($Tag->synonym->id);
					if ($keep_tagged){
						foreach ($TargetTagged as $tg){
							if (!Tagged::make($Tag->id, $tg->appearance_id)->save())
								Response::fail('Tag synonym removal process failed, please re-try.<br>Technical details: '.$tg->to_json());
							$uses++;
						}
					}
					else {
						foreach ($TargetTagged as $tg)
							$tg->appearance->updateIndex();
					}
				}
				else $keep_tagged = false;

				if (!$Tag->update_attributes(['synonym_of' => null, 'uses' => $uses]))
					Response::dbError('Could not update tag');

				Response::done(['keep_tagged' => $keep_tagged]);
			break;
			case 'make':
			case 'set':
				$data['name'] = CGUtils::validateTagName('name');

				$epTagName = CGUtils::normalizeEpisodeTagName($data['name']);
				$surelyAnEpisodeTag = $epTagName !== false;
				$type = (new Input('type',function($value){
					if (!isset(Tags::TAG_TYPES[$value]))
						return Input::ERROR_INVALID;
				}, [
					Input::IS_OPTIONAL => true,
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_INVALID => 'Invalid tag type: @value',
					]
				]))->out();
				if (empty($type)){
					if ($surelyAnEpisodeTag)
						$data['name'] = $epTagName;
					$data['type'] = $epTagName === false ? null : 'ep';
				}
				else {
					if ($type === 'ep'){
						if (!$surelyAnEpisodeTag){
							$errmsg = <<<HTML
Episode tags must be in one of the following formats:
<ol>
<li>
	<code>s<var>S</var>e<var>E<sub>1</sub></var>[-<var>E<sub>2</sub></var>]</code> where
	<ul>
		<li><var>S</var> ∈ <var>{1, 2, 3, &hellip; 9}</var></li>
		<li><var>E<sub>1</sub></var>, <var>E<sub>2</sub></var> ∈ <var>{1, 2, 3, &hellip; 26}</var></li>
		<li>if specified: <var>E<sub>1</sub></var>+1 = <var>E<sub>2</sub></var></li>
	</ul>
</li>
<li>
	<code>movie<var>M</var></code> where <var>M</var> ∈ <var>&#x2124;<sup>+</sup></var>
</li>
</ol>
HTML;

							Response::fail($errmsg);
						}
						$data['name'] = $epTagName;
					}
					else if ($surelyAnEpisodeTag)
						$type = 'ep';
					$data['type'] = $type;
				}

				if (!$adding)
					DB::$instance->where('id', $Tag->id,'!=');
				if (DB::$instance->where('name', $data['name'])->where('type', $data['type'])->has('tags'))
					Response::fail('A tag with the same name and type already exists');

				$data['title'] = (new Input('title','string', [
					Input::IS_OPTIONAL => true,
					Input::IN_RANGE => [null,255],
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_RANGE => 'Tag title cannot be longer than @max characters'
					]
				]))->out();

				if ($adding){
					$Tag = new Tag($data);
					if (!$Tag->save())
						Response::dbError();

					$AppearanceID = (new Input('addto','int', [Input::IS_OPTIONAL => true]))->out();
					if ($AppearanceID !== null){
						if ($AppearanceID === 0)
							Response::success('The tag was created, <strong>but</strong> it could not be added to the appearance because it can’t be tagged.');

						$Appearance = Appearance::find($AppearanceID);
						if (empty($Appearance))
							Response::success("The tag was created, <strong>but</strong> it could not be added to the appearance (<a href='/cg/v/$AppearanceID'>#$AppearanceID</a>) because it doesn’t seem to exist. Please try adding the tag manually.");

						if (!Tagged::make($Tag->id, $Appearance->id)->save()) Response::dbError();
						$Appearance->updateIndex();
						Tags::updateUses($Tag->id);
						$r = ['tags' => $Appearance->getTagsHTML(NOWRAP)];
						if ($this->_appearancePage){
							$r['needupdate'] = true;
							$r['eps'] = $Appearance->getRelatedEpisodesHTML($this->_EQG);
						}
						Response::done($r);
					}
				}
				else {
					$Tag->update_attributes($data);
					$data = $Tag->to_array();
					$AppearanceID = !empty($this->_appearancePage) ? (int) $_POST['APPEARANCE_PAGE'] : null;
					$tagrelations = Tagged::by_tag($Tag->id);
					foreach ($tagrelations as $tagged){
						$tagged->appearance->updateIndex();

						if ($tagged->appearance_id === $AppearanceID){
							$data['needupdate'] = true;
							$Appearance = Appearance::find($AppearanceID);
							$data['eps'] = $Appearance->getRelatedEpisodesHTML($this->_EQG);
						}
					}
				}

				Response::done($data);
			break;
		}

		// TODO Untangle spaghetti
		$merging = $action === 'merge';
		$synoning = $action === 'synon';
		if ($merging || $synoning){
			if ($synoning && $Tag->synonym_of !== null)
				Response::fail('The selected tag is already a synonym of the "'.$Tag->synonym->name.'" ('.Tags::TAG_TYPES[$Tag->synonym->type].') tag');

			$targetid = (new Input('targetid','int', [
				Input::CUSTOM_ERROR_MESSAGES => [
					Input::ERROR_MISSING => 'Missing target tag ID',
				]
			]))->out();
			$Target = Tag::find($targetid);
			if (empty($Target))
				Response::fail('Target tag does not exist');
			if ($Target->synonym_of !== null)
				Response::fail('The selected tag is already a synonym of the "'.$Target->synonym->name.'" ('.Tags::TAG_TYPES[$Target->synonym->type].') tag');

			$TargetTagged = Tagged::by_tag($Target->id);
			$TaggedAppearanceIDs = [];
			foreach ($TargetTagged as $tg)
				$TaggedAppearanceIDs[] = $tg->appearance_id;

			$Tagged = Tagged::by_tag($Tag->id);
			foreach ($Tagged as $tg){
				if (\in_array($tg->appearance_id, $TaggedAppearanceIDs, true))
					continue;

				if (!Tagged::make($Target->id, $tg->appearance_id)->save())
					Response::fail('Tag '.($merging?'merging':'synonimizing').' failed, please re-try.<br>Technical details: '.$tg->to_json());
			}
			if ($merging)
				// No need to delete "tagged" table entries, constraints do it for us
				$Tag->delete();
			else {
				Tagged::delete_all(['conditions' => ['tag_id = ?', $Tag->id]]);
				$Tag->update_attributes([
					'synonym_of' => $Target->id,
					'uses' => 0,
				]);
			}
			foreach ($TaggedAppearanceIDs as $id)
				Appearance::find($id)->updateIndex();

			Tags::updateUses($Target->id);
			Response::success('Tags successfully '.($merging?'merged':'synonymized'), $synoning || $merging ? ['target' => $Target] : null);
		}

		CoreUtils::notFound();
	}
}
