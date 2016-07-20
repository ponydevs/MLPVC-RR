<?php

	if (!POST_REQUEST)
		Episode::LoadPage();

	CSRFProtection::Protect();

	if (empty($data)) CoreUtils::NotFound();

	$EpData = Episode::ParseID($data);
	if (!empty($EpData)){
		$Ep = Episode::GetActual($EpData['season'],$EpData['episode']);
		$airs =  strtotime($Ep['airs']);
		unset($Ep['airs']);
		$Ep['airdate'] = gmdate('Y-m-d', $airs);
		$Ep['airtime'] = gmdate('H:i', $airs);
		CoreUtils::Respond(array(
			'ep' => $Ep,
			'epid' => Episode::FormatTitle($Ep, AS_ARRAY, 'id'),
			'caneditid' => Episode::GetPostCount($Ep) === 0,
		));
	}
	unset($EpData);

	$data = explode('/', $data);
	$action = array_splice($data, 0, 1)[0] ?? null;
	$data = implode('/', $data);

	if (regex_match($EPISODE_ID_REGEX,$data,$_match)){
		$Episode = Episode::GetActual(intval($_match[1], 10), intval($_match[2], 10), $action !== 'delete' ? ALLOW_SEASON_ZERO : !ALLOW_SEASON_ZERO);
		if (empty($Episode))
			CoreUtils::Respond("There's no episode with this season & episode number");
	}
	else if ($action !== 'add')
		CoreUtils::StatusCode(400, AND_DIE);

	switch ($action){
		case "delete":
			if (!Permission::Sufficient('staff'))
				CoreUtils::Respond();

			if (!$Database->whereEp($Episode)->delete('episodes'))
				CoreUtils::Respond(ERR_DB_FAIL);
			Log::Action('episodes',array(
				'action' => 'del',
				'season' => $Episode['season'],
				'episode' => $Episode['episode'],
				'twoparter' => $Episode['twoparter'],
				'title' => $Episode['title'],
				'airs' => $Episode['airs'],
			));
			$CGDb->where('name', "s{$Episode['season']}e{$Episode['episode']}")->delete('tags');
			CoreUtils::Respond('Episode deleted successfuly',1,array(
				'upcoming' => Episode::GetSidebarUpcoming(NOWRAP),
			));
		break;
		case "requests":
		case "reservations":
			$only = $action === 'requests' ? ONLY_REQUESTS : ONLY_RESERVATIONS;
			$posts = Posts::Get($Episode['season'], $Episode['episode'], $only);

			switch ($only){
				case ONLY_REQUESTS: $rendered = Posts::GetRequestsSection($posts); break;
				case ONLY_RESERVATIONS: $rendered = Posts::GetReservationsSection($posts); break;
			}
			CoreUtils::Respond(array('render' => $rendered));
		break;
		case "vote":
			if (isset($_REQUEST['detail'])){
				$VoteCountQuery = $Database->rawQuery(
					"SELECT count(*) as value, vote as label
					FROM episodes__votes v
					WHERE season = ? && episode = ?
					GROUP BY v.vote
					ORDER BY v.vote ASC",array($Episode['season'],$Episode['episode']));
				$VoteCounts = array(
				    'labels' => array(),
				    'datasets' => array(
						array(
							'data' => array()
						)
				    )
				);
				foreach ($VoteCountQuery as $row){
					$VoteCounts['labels'][] = $row['label'];
					$VoteCounts['datasets'][0]['data'][] = $row['value'];
				}

				CoreUtils::Respond(array('data' => $VoteCounts));
			}
			else if (isset($_REQUEST['html']))
				CoreUtils::Respond(array('html' => Episode::GetSidebarVoting($Episode)));

			if (!Permission::Sufficient('user'))
				CoreUtils::Respond();

			if (!$Episode['aired'])
				CoreUtils::Respond('You can only vote on this episode after it has aired.');

			$UserVote = Episode::GetUserVote($Episode);
			if (!empty($UserVote))
				CoreUtils::Respond('You already voted for this episode');

			$vote = (new Input('vote','int',array(
				Input::IN_RANGE => [1,5],
				Input::CUSTOM_ERROR_MESSAGES => array(
					Input::ERROR_MISSING => 'Vote value missing from request',
					Input::ERROR_RANGE => 'Vote value must be an integer between @min and @max (inclusive)',
				)
			)))->out();

			if (!$Database->insert('episodes__votes',array(
				'season' => $Episode['season'],
				'episode' => $Episode['episode'],
				'user' => $currentUser['id'],
				'vote' => $vote,
			))) CoreUtils::Respond(ERR_DB_FAIL);
			CoreUtils::Respond(array('newhtml' => Episode::GetSidebarVoting($Episode)));
		break;
		case "videos":
			CoreUtils::Respond(Episode::GetVideoEmbeds($Episode));
		break;
		case "getvideos":
			$return = array(
				'twoparter' => $Episode['twoparter'],
				'vidlinks' => array(),
				'fullep' => array(),
				'airs' => date('c',strtotime($Episode['airs'])),
			);
			$Vids = $Database->whereEp($Episode)->get('episodes__videos',null,'provider as name, *');
			foreach ($Vids as $part => $prov){
				if (!empty($prov['id']))
					$return['vidlinks']["{$prov['name']}_{$prov['part']}"] = VideoProvider::get_embed($prov['id'], $prov['name'], VideoProvider::URL_ONLY);
				if ($prov['fullep'])
					$return['fullep'][] = $prov['name'];
			}
			CoreUtils::Respond($return);
		break;
		case "setvideos":
			foreach (array('yt','dm') as $provider){
				for ($part = 1; $part <= ($Episode['twoparter']?2:1); $part++){
					$set = null;
					$PostKey = "{$provider}_$part";
					if (!empty($_POST[$PostKey])){
						$Provider = Episode::$VIDEO_PROVIDER_NAMES[$provider];
						try {
							$vid = new VideoProvider($_POST[$PostKey]);
						}
						catch (Exception $e){
							CoreUtils::Respond("$Provider link issue: ".$e->getMessage());
						};
						if (!isset($vid->provider) || $vid->provider['name'] !== $provider)
							CoreUtils::Respond("Incorrect $Provider URL specified");
						/** @noinspection PhpUndefinedFieldInspection */
						$set = $vid::$id;
					}

					$fullep = $Episode['twoparter'] ? false : true;
					if ($part === 1 && $Episode['twoparter'] && isset($_POST["{$PostKey}_full"])){
						$NextPart = $provider.'_'.($part+1);
						$_POST[$NextPart] = null;
						$fullep = true;
					}

					$videocount = $Database
						->whereEp($Episode)
						->where('provider', $provider)
						->where('part', $part)
						->count('episodes__videos');
					if ($videocount === 0){
						if (!empty($set))
							$Database->insert('episodes__videos', array(
								'season' => $Episode['season'],
								'episode' => $Episode['episode'],
								'provider' => $provider,
								'part' => $part,
								'id' => $set,
								'fullep' => $fullep,
							));
					}
					else {
						$Database
							->whereEp($Episode)
							->where('provider', $provider)
							->where('part', $part);
						if (empty($set))
							$Database->delete('episodes__videos');
						else $Database->update('episodes__videos', array(
							'id' => $set,
							'fullep' => $fullep,
							'modified' => date('c'),
						));
					}
				}
			}

			CoreUtils::Respond('Links updated',1,array('epsection' => Episode::RenderVideos($Episode)));
		break;
		case "getcgrelations":
			$CheckTag = array();

			$EpTagIDs = Episode::GetTagIDs($Episode);
			if (empty($EpTagIDs))
				CoreUtils::Respond('The episode has no associated tag(s)!');

			$TaggedAppearanceIDs = array();
			foreach ($EpTagIDs as $tid){
				$AppearanceIDs = $CGDb->where('tid',$tid)->get('tagged',null,'ponyid');
				foreach ($AppearanceIDs as $id)
					$TaggedAppearanceIDs[$id['ponyid']] = true;
			}

			$Appearances = $CGDb->where('ishuman', 0)->where('"id" != 0')->orderBy('label','ASC')->get('appearances',null,'id,label');

			$Sorted = array(
				'unlinked' => array(),
				'linked' => array(),
			);
			foreach ($Appearances as $a)
				$Sorted[isset($TaggedAppearanceIDs[$a['id']]) ? 'linked' : 'unlinked'][] = $a;

			CoreUtils::Respond($Sorted);
		break;
		case "setcgrelations":
			$AppearanceIDs = (new Input('ids','int[]',array(
				Input::CUSTOM_ERROR_MESSAGES => array(
					Input::ERROR_MISSING => 'Missing appearance ID list',
					Input::ERROR_INVALID => 'Appearance ID list is invalid',
				)
			)))->out();

			$EpTagIDs = Episode::GetTagIDs($Episode);
			if (empty($EpTagIDs))
				CoreUtils::Respond('The episode has no associated tag(s)!');
			$EpTagIDs = implode(',',$EpTagIDs);
			$Tags = $CGDb->where("tid IN ($EpTagIDs)")->orderByLiteral('char_length(name)','DESC')->getOne('tags','tid');
			$UseID = $Tags['tid'];

			foreach ($AppearanceIDs as $id){
				if (!$CGDb->where("tid IN ($EpTagIDs)")->where('ponyid', $id)->has('tagged'))
					@$CGDb->insert('tagged', array('tid' => $UseID, 'ponyid' => $id));
			}
			$CGDb->where("tid IN ($EpTagIDs)")->where('ponyid NOT IN ('.implode(',',$AppearanceIDs).')')->delete('tagged');

			CoreUtils::Respond(array('section' => Episode::GetAppearancesSectionHTML($Episode)));
		break;
		case "edit":
		case "add":
			if (!Permission::Sufficient('staff'))
				CoreUtils::Respond();
			$editing = $action === 'edit';

			$insert = array();
			if (!$editing)
				$insert['posted_by'] = $currentUser['id'];

			$insert['season'] = Episode::ValidateSeason();
			$insert['episode'] = Episode::ValidateEpisode();

			if ($editing){
				$SeasonChanged = $insert['season'] != $Episode['season'];
				$EpisodeChanged = $insert['episode'] != $Episode['episode'];
				if ($SeasonChanged || $EpisodeChanged){
					$Target = Episode::GetActual(
						$insert['season'] ?? $Episode['season'],
						$insert['episode'] ?? $Episode['episode']
					);
					if (!empty($Target))
						CoreUtils::Respond("There's already an episode with the same season & episode number");

					if (Episode::GetPostCount($insert) > 0)
						CoreUtils::Respond('This epsiode\'s ID cannot be changed because it already has posts and this action could break existing links');
				}
			}
			else if ($Database->whereEp($insert)->has('episodes'))
				CoreUtils::Respond('An episode with the same season and episode number already exists');

			$insert['no'] = (new Input('no','int',array(
				Input::IS_OPTIONAL => true,
				Input::IN_RANGE => [1,255],
				Input::CUSTOM_ERROR_MESSAGES => array(
				    Input::ERROR_INVALID => 'Overall episode number (@value) is invalid',
				    Input::ERROR_RANGE => 'Overall episode number must be between @min and @max',
				)
			)))->out();

			$insert['twoparter'] = isset($_POST['twoparter']) ? 1 : 0;

			$insert['title'] = (new Input('title','string',array(
				Input::IN_RANGE => [5,35],
				Input::CUSTOM_ERROR_MESSAGES => array(
					Input::ERROR_MISSING => 'Episode title is missing',
					Input::ERROR_RANGE => 'Episode title must be between @min and @max characters',
				)
			)))->out();
			CoreUtils::CheckStringValidity($insert['title'], 'Episode title', INVERSE_EP_TITLE_PATTERN);

			$airs = (new Input('airs','timestamp',array(
				Input::CUSTOM_ERROR_MESSAGES => array(
					Input::ERROR_MISSING => 'No air date & time specified',
					Input::ERROR_INVALID => 'Invalid air date and/or time (@value) specified'
				)
			)))->out();
			if (empty($airs))
				CoreUtils::Respond('Please specify an air date & time');
			$insert['airs'] = date('c',strtotime('this minute', $airs));

			if ($editing){
				if (!$Database->whereEp($Episode)->update('episodes', $insert))
					CoreUtils::Respond('Updating episode failed: '.ERR_DB_FAIL);
			}
			else if (!$Database->insert('episodes', $insert))
				CoreUtils::Respond('Episode creation failed: '.ERR_DB_FAIL);

			if (!$editing || $SeasonChanged || $EpisodeChanged){
				$TagName = CGUtils::CheckEpisodeTagName("s{$insert['season']}e{$insert['episode']}");
				$EpTag = $CGDb->where('name', $editing ? "s{$Episode['season']}e{$Episode['episode']}" : $TagName)->getOne('tags', 'tid');

				if (!empty($EpTag)){
					if ($editing)
						$CGDb->where('tid',$EpTag['tid'])->update('tags', array(
							'name' => $TagName,
						));
				}
				else {
					if (!$CGDb->insert('tags', array(
						'name' => $TagName,
						'type' => 'ep',
					))) CoreUtils::Respond('Episode tag creation failed: '.ERR_DB_FAIL);
				}
			}

			if ($editing){
				$logentry = array('target' => Episode::FormatTitle($Episode,AS_ARRAY,'id'));
				$changes = 0;
				if (!empty($Episode['airs']))
					$Episode['airs'] = date('c',strtotime($Episode['airs']));
				foreach (array('season', 'episode', 'twoparter', 'title', 'airs') as $k){
					if (isset($insert[$k]) && $insert[$k] != $Episode[$k]){
						$logentry["old$k"] = $Episode[$k];
						$logentry["new$k"] = $insert[$k];
						$changes++;
					}
				}
				if ($changes > 0) Log::Action('episode_modify',$logentry);
			}
			else Log::Action('episodes',array(
				'action' => 'add',
				'season' => $insert['season'],
				'episode' => $insert['episode'],
				'twoparter' => isset($insert['twoparter']) ? $insert['twoparter'] : 0,
				'title' => $insert['title'],
				'airs' => $insert['airs'],
			));
			CoreUtils::Respond($editing ? true : array('epid' => Episode::FormatTitle($insert,AS_ARRAY,'id')));
		break;
	}
