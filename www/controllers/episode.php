<?php

	if (POST_REQUEST){
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
			));
		}
		unset($EpData);

		if (regex_match(new RegExp('^delete/'.EPISODE_ID_PATTERN.'$'),$data,$_match)){
			if (!Permission::Sufficient('inspector')) CoreUtils::Respond();
			list($season,$episode) = array_splice($_match,1,2);

			$Episode = Episode::GetActual(intval($season, 10),intval($episode, 10));
			if (empty($Episode))
				CoreUtils::Respond("There's no episode with this season & episode number");

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
		}
		else if (regex_match(new RegExp('^((?:request|reservation)s)/'.EPISODE_ID_PATTERN.'$'), $data, $_match)){
			$Episode = Episode::GetActual($_match[2],$_match[3],ALLOW_SEASON_ZERO);
			if (empty($Episode))
				CoreUtils::Respond("There's no episode with this season & episode number");
			$only = $_match[1] === 'requests' ? ONLY_REQUESTS : ONLY_RESERVATIONS;
			$posts = Posts::Get($Episode['season'], $Episode['episode'], $only);

			switch ($only){
				case ONLY_REQUESTS: $rendered = Posts::GetRequestsSection($posts); break;
				case ONLY_RESERVATIONS: $rendered = Posts::GetReservationsSection($posts); break;
			}
			CoreUtils::Respond(array('render' => $rendered));
		}
		else if (regex_match(new RegExp('^vote/'.EPISODE_ID_PATTERN.'$'), $data, $_match)){
			$Episode = Episode::GetActual($_match[1],$_match[2],ALLOW_SEASON_ZERO);
			if (empty($Episode))
				CoreUtils::Respond("There's no episode with this season & episode number");

			if (isset($_REQUEST['detail'])){
				$VoteCounts = $Database->rawQuery(
					"SELECT count(*) as value, vote as label
					FROM episodes__votes v
					WHERE season = ? && episode = ?
					GROUP BY v.vote
					ORDER BY v.vote DESC",array($Episode['season'],$Episode['episode']));

				CoreUtils::Respond(array('data' => $VoteCounts));
			}

			if (isset($_REQUEST['html']))
				CoreUtils::Respond(array('html' => Episode::GetSidebarVoting($Episode)));

			if (!Permission::Sufficient('user'))
				CoreUtils::Respond();

			if (!$Episode['aired'])
				CoreUtils::Respond('You can only vote on this episode after it has aired.');

			$UserVote = Episode::GetUserVote($Episode);
			if (!empty($UserVote))
				CoreUtils::Respond('You already voted for this episode');

			if (empty($_POST['vote']) || !is_numeric($_POST['vote']))
				CoreUtils::Respond('Vote value missing from request');

			$Vote = intval($_POST['vote'], 10);
			if ($Vote < 1 || $Vote > 5)
				CoreUtils::Respond('Vote value must be an integer between 1 and 5 (inclusive)');

			if (!$Database->insert('episodes__votes',array(
				'season' => $Episode['season'],
				'episode' => $Episode['episode'],
				'user' => $currentUser['id'],
				'vote' => $Vote,
			))) CoreUtils::Respond(ERR_DB_FAIL);
			CoreUtils::Respond(array('newhtml' => Episode::GetSidebarVoting($Episode)));
		}
		else if (regex_match(new RegExp('^(([sg])et)?videos/'.EPISODE_ID_PATTERN.'$'), $data, $_match)){
			$Episode = Episode::GetActual($_match[3],$_match[4],ALLOW_SEASON_ZERO);
			if (empty($Episode))
				CoreUtils::Respond("There's no episode with this season & episode number");

			if (empty($_match[1]))
				CoreUtils::Respond(Episode::GetVideoEmbeds($Episode));

			$set = $_match[2] === 's';

			if (!$set){
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
			}

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
		}
		else {
			if (!Permission::Sufficient('inspector')) CoreUtils::Respond();
			$editing = regex_match(new RegExp('^edit\/'.EPISODE_ID_PATTERN.'$'),$data,$_match);
			if ($editing){
				list($season, $episode) = array_map('intval', array_splice($_match, 1, 2));
				$insert = array();
			}
			else if ($data === 'add') $insert = array('posted_by' => $currentUser['id']);
			else CoreUtils::StatusCode(404, AND_DIE);

			if (!isset($_POST['season']) || !is_numeric($_POST['season']))
				CoreUtils::Respond('Season number is missing or invalid');
			$insert['season'] = intval($_POST['season'], 10);
			if ($insert['season'] < 1 || $insert['season'] > 8) CoreUtils::Respond('Season number must be between 1 and 8');

			if (!isset($_POST['episode']) || !is_numeric($_POST['episode']))
				CoreUtils::Respond('Episode number is missing or invalid');
			$insert['episode'] = intval($_POST['episode'], 10);
			if ($insert['episode'] < 1 || $insert['episode'] > 26) CoreUtils::Respond('Season number must be between 1 and 26');

			if ($editing){
				$Current = Episode::GetActual($insert['season'],$insert['episode']);
				if (empty($Current))
					CoreUtils::Respond("This episode doesn't exist");
			}
			$Target = Episode::GetActual($insert['season'],$insert['episode']);
			if (!empty($Target) && (!$editing || ($editing && ($Target['season'] !== $Current['season'] || $Target['episode'] !== $Current['episode']))))
				CoreUtils::Respond("There's already an episode with the same season & episode number");

			$insert['twoparter'] = isset($_POST['twoparter']) ? 1 : 0;

			if (empty($_POST['title']))
				CoreUtils::Respond('Episode title is missing or invalid');
			$insert['title'] = trim($_POST['title']);
			if (strlen($insert['title']) < 5 || strlen($insert['title']) > 35)
				CoreUtils::Respond('Episode title must be between 5 and 35 characters');
			if (!$EP_TITLE_REGEX->match($insert['title']))
				CoreUtils::Respond('Episode title contains invalid charcaters');

			if (empty($_POST['airs']))
				repond('No air date &time specified');
			$airs = strtotime($_POST['airs']);
			if (empty($airs))
				CoreUtils::Respond('Invalid air time');
			$insert['airs'] = date('c',strtotime('this minute', $airs));

			if ($editing){
				if (!$Database->whereEp($season,$episode)->update('episodes', $insert))
					CoreUtils::Respond('Updating episode failed: '.ERR_DB_FAIL);
			}
			else if (!$Database->insert('episodes', $insert))
				CoreUtils::Respond('Episode creation failed: '.ERR_DB_FAIL);

			$SeasonChanged = $editing && $season !== $insert['season'];
			$EpisodeChanged = $editing && $episode !== $insert['episode'];
			if (!$editing || $SeasonChanged || $EpisodeChanged){
				$TagName = "s{$insert['season']}e{$insert['episode']}";
				$EpTag = $CGDb->where('name', $editing ? "s{$season}e{$episode}" : $TagName)->getOne('tags');

				if (empty($EpTag)){
					if (!$CGDb->insert('tags', array(
						'name' => $TagName,
						'type' => 'ep',
					))) CoreUtils::Respond('Episode tag creation failed: '.ERR_DB_FAIL);
				}
				else if ($SeasonChanged || $EpisodeChanged)
					$CGDb->where('name',$EpTag['name'])->update('tags', array(
						'name' => $TagName,
					));
			}

			if ($editing){
				$logentry = array('target' => Episode::FormatTitle($Current,AS_ARRAY,'id'));
				$changes = 0;
				foreach (array('season', 'episode', 'twoparter', 'title', 'airs') as $k){
					if (isset($insert[$k]) && $insert[$k] != $Current[$k]){
						$logentry["old$k"] = $Current[$k];
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
		}
	}

	Episode::LoadPage();
