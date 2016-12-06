<?php

use App\CGUtils;
use App\CoreUtils;
use App\CSRFProtection;
use App\Episode;
use App\EpisodeVideo;
use App\Episodes;
use App\Input;
use App\Log;
use App\Permission;
use App\Posts;
use App\Response;
use App\VideoProvider;

/** @var $Episode Episode */
if (!POST_REQUEST)
	Episodes::LoadPage();

CSRFProtection::Protect();
if (empty($data))
	CoreUtils::NotFound();

$data = explode('/', $data);
$action = array_splice($data, 0, 1)[0] ?? null;
$data = implode('/', $data);

$EpData = Episodes::ParseID(!empty($data) ? $data : ($_POST['epid'] ?? null));
if (!empty($EpData)){
	$Episode = Episodes::GetActual($EpData['season'], $EpData['episode'], Episodes::ALLOW_MOVIES);
	if (empty($Episode))
		Response::Fail("There's no episode with this season & episode number");
	$isMovie = $Episode->isMovie;
}
else if ($action !== 'add')
	CoreUtils::NotFound();

switch ($action){
	case "get":
		Response::Done(array(
			'ep' => $Episode,
			'epid' => $Episode->formatTitle(AS_ARRAY, 'id'),
			'caneditid' => $Episode->getPostCount() === 0,
		));
	break;
	case "delete":
		if (!Permission::Sufficient('staff'))
			Response::Fail();

		if (!$Database->whereEp($Episode)->delete('episodes'))
			Response::DBError();
		Log::Action('episodes',array(
			'action' => 'del',
			'season' => $Episode->season,
			'episode' => $Episode->episode,
			'twoparter' => $Episode->twoparter,
			'title' => $Episode->title,
			'airs' => $Episode->airs,
		));
		$CGDb->where('name', "s{$Episode->season}e{$Episode->episode}")->delete('tags');
		Response::Success('Episode deleted successfuly',array(
			'upcoming' => Episodes::GetSidebarUpcoming(NOWRAP),
		));
	break;
	case "requests":
	case "reservations":
		$only = $action === 'requests' ? ONLY_REQUESTS : ONLY_RESERVATIONS;
		$posts = Posts::Get($Episode, $only);

		switch ($only){
			case ONLY_REQUESTS: $rendered = Posts::GetRequestsSection($posts); break;
			case ONLY_RESERVATIONS: $rendered = Posts::GetReservationsSection($posts); break;
		}
		Response::Done(array('render' => $rendered));
	break;
	case "vote":
		if (isset($_REQUEST['detail'])){
			$VoteCountQuery = $Database->rawQuery(
				"SELECT count(*) as value, vote as label
				FROM episodes__votes v
				WHERE season = ? && episode = ?
				GROUP BY v.vote
				ORDER BY v.vote ASC",array($Episode->season,$Episode->episode));
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

			Response::Done(array('data' => $VoteCounts));
		}
		else if (isset($_REQUEST['html']))
			Response::Done(array('html' => Episodes::GetSidebarVoting($Episode)));

		if (!Permission::Sufficient('user'))
			Response::Fail();

		if (!$Episode->aired)
			Response::Fail('You can only vote on this episode after it has aired.');

		$UserVote = Episodes::GetUserVote($Episode);
		if (!empty($UserVote))
			Response::Fail('You already voted for this episode');

		$vote = (new Input('vote','int',array(
			Input::IN_RANGE => [1,5],
			Input::CUSTOM_ERROR_MESSAGES => array(
				Input::ERROR_MISSING => 'Vote value missing from request',
				Input::ERROR_RANGE => 'Vote value must be an integer between @min and @max (inclusive)',
			)
		)))->out();

		if (!$Database->insert('episodes__votes',array(
			'season' => $Episode->season,
			'episode' => $Episode->episode,
			'user' => $currentUser->id,
			'vote' => $vote,
		))) Response::DBError();
		$Episode->updateScore();
		Response::Done(array('newhtml' => Episodes::GetSidebarVoting($Episode)));
	break;
	case "videos":
		Response::Done(Episodes::GetVideoEmbeds($Episode));
	break;
	case "getvideos":
		$return = array(
			'twoparter' => $Episode->twoparter,
			'vidlinks' => array(),
			'fullep' => array(),
			'airs' => date('c',strtotime($Episode->airs)),
		);
		/** @var $Vids EpisodeVideo[] */
		$Vids = $Database->whereEp($Episode)->get('episodes__videos');
		foreach ($Vids as $part => $vid){
			if (!empty($vid->id))
				$return['vidlinks']["{$vid->provider}_{$vid->part}"] = VideoProvider::getEmbed($vid, VideoProvider::URL_ONLY);
			if ($vid->fullep)
				$return['fullep'][] = $vid->provider;
		}
		Response::Done($return);
	break;
	case "setvideos":
		foreach (array('yt','dm') as $provider){
			for ($part = 1; $part <= ($Episode->twoparter?2:1); $part++){
				$set = null;
				$PostKey = "{$provider}_$part";
				if (!empty($_POST[$PostKey])){
					$Provider = Episodes::$VIDEO_PROVIDER_NAMES[$provider];
					try {
						$vidProvider = new VideoProvider($_POST[$PostKey]);
					}
					catch (Exception $e){
						Response::Fail("$Provider link issue: ".$e->getMessage());
					};
					if (!isset($vidProvider->episodeVideo) || $vidProvider->episodeVideo->provider !== $provider)
						Response::Fail("Incorrect $Provider URL specified");
					/** @noinspection PhpUndefinedFieldInspection */
					$set = $vidProvider::$id;
				}

				$fullep = $Episode->twoparter ? false : true;
				if ($part === 1 && $Episode->twoparter && isset($_POST["{$PostKey}_full"])){
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
							'season' => $Episode->season,
							'episode' => $Episode->episode,
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

		Response::Success('Links updated',array('epsection' => Episodes::GetVideosHTML($Episode)));
	break;
	case "brokenvideos":
		/** @var $videos EpisodeVideo[] */
		$videos = $Database
			->whereEp($Episode)
			->get('episodes__videos');

		$removed = 0;
		foreach ($videos as $video){
			if (!$video->isBroken())
				continue;

			$removed++;
			$Database->whereEp($Episode)->where('provider', $video->provider)->where('id', $video->id)->delete('episodes__videos');
			Log::Action('video_broken',array(
				'season' => $Episode->season,
				'episode' => $Episode->episode,
				'provider' => $video->provider,
				'id' => $video->id,
			));
		}

		if ($removed === 0)
			return Response::Success('No broken videos found under this '.($Episode->isMovie?'movie':'episode').'.');

		Response::Success("$removed video link".($removed===1?' has':'s have')." been removed from the site. Thank you for letting us know.",array(
			'epsection' => Episodes::GetVideosHTML($Episode, NOWRAP),
		));
	break;
	case "getcgrelations":
		$CheckTag = array();

		$EpTagIDs = Episodes::GetTagIDs($Episode);
		if (empty($EpTagIDs))
			Response::Fail('The episode has no associated tag(s)!');

		$TaggedAppearanceIDs = array();
		foreach ($EpTagIDs as $tid){
			$AppearanceIDs = $CGDb->where('tid',$tid)->get('tagged',null,'ponyid');
			foreach ($AppearanceIDs as $id)
				$TaggedAppearanceIDs[$id['ponyid']] = true;
		}

		$Appearances = $CGDb->where('ishuman', $Episode->isMovie)->where('"id" != 0')->orderBy('label','ASC')->get('appearances',null,'id,label');

		$Sorted = array(
			'unlinked' => array(),
			'linked' => array(),
		);
		foreach ($Appearances as $a)
			$Sorted[isset($TaggedAppearanceIDs[$a['id']]) ? 'linked' : 'unlinked'][] = $a;

		Response::Done($Sorted);
	break;
	case "setcgrelations":
		$AppearanceIDs = (new Input('ids','int[]',array(
			Input::IS_OPTIONAL => true,
			Input::CUSTOM_ERROR_MESSAGES => array(
				Input::ERROR_MISSING => 'Missing appearance ID list',
				Input::ERROR_INVALID => 'Appearance ID list is invalid',
			)
		)))->out();

		$EpTagIDs = Episodes::GetTagIDs($Episode);
		if (empty($EpTagIDs))
			Response::Fail('The episode has no associated tag(s)!');
		$EpTagIDs = implode(',',$EpTagIDs);
		$Tags = $CGDb->where("tid IN ($EpTagIDs)")->orderByLiteral('char_length(name)','DESC')->getOne('tags','tid');
		$UseID = $Tags['tid'];

		if (!empty($AppearanceIDs)){
			foreach ($AppearanceIDs as $id){
				if (!$CGDb->where("tid IN ($EpTagIDs)")->where('ponyid', $id)->has('tagged'))
					@$CGDb->insert('tagged', array('tid' => $UseID, 'ponyid' => $id));
			}
			$CGDb->where('ponyid NOT IN ('.implode(',',$AppearanceIDs).')');
		}
		$CGDb->where("tid IN ($EpTagIDs)")->delete('tagged');

		Response::Done(array('section' => Episodes::GetAppearancesSectionHTML($Episode)));
	break;
	case "edit":
	case "add":
		if (!Permission::Sufficient('staff'))
			Response::Fail();
		$editing = $action === 'edit';
		$canEditID = !empty($Episode) && $Episode->getPostCount() === 0;

		$insert = array();
		if (!$editing)
			$insert['posted_by'] = $currentUser->id;

		if (!$editing || $canEditID){
			$insert['season'] = Episodes::ValidateSeason(Episodes::ALLOW_MOVIES);
			$isMovie = $insert['season'] === 0;
			$insert['episode'] = Episodes::ValidateEpisode($isMovie);
		}
		else if (!$canEditID){
			$isMovie = $Episode->season === 0;
			$insert['season'] = $isMovie ? 0 : $Episode->season;
			$insert['episode'] = $Episode->episode;
		}
		$What = $isMovie ? 'Movie' : 'Episode';
		$what = strtolower($What);

		$EpisodeChanged = true;
		$SeasonChanged = true;
		if ($editing){
			$SeasonChanged = $isMovie ? false : $insert['season'] != $Episode->season;
			$EpisodeChanged = $insert['episode'] != $Episode->episode;
			if ($SeasonChanged || $EpisodeChanged){
				$Target = Episodes::GetActual(
					$insert['season'] ?? $Episode->season,
					$insert['episode'] ?? $Episode->episode,
					Episodes::ALLOW_MOVIES
				);
				if (!empty($Target))
					Response::Fail("There's already an episode with the same season & episode number");

				if ((new Episode($insert))->getPostCount() > 0)
					Response::Fail('This epsiode\'s ID cannot be changed because it already has posts and this action could break existing links');
			}
		}
		else if ($canEditID){
			$MatchingID = $Database->whereEp($insert['season'], $insert['episode'])->getOne('episodes');
			if (!empty($MatchingID))
				Response::Fail(($isMovie?'A movie':'An episode').' with the same '.($isMovie?'overall':'season and episode').' number already exists');
		}

		if (!$isMovie)
			$insert['no'] = (new Input('no','int',array(
				Input::IS_OPTIONAL => true,
				Input::IN_RANGE => [1,255],
				Input::CUSTOM_ERROR_MESSAGES => array(
				    Input::ERROR_INVALID => 'Overall episode number (@value) is invalid',
				    Input::ERROR_RANGE => 'Overall episode number must be between @min and @max',
				)
			)))->out();

		$insert['twoparter'] = !$isMovie  && isset($_POST['twoparter']) ? 1 : 0;

		$insert['title'] = (new Input('title',function(&$value, $range) use ($isMovie){
			global $PREFIX_REGEX;
			$prefixed = $PREFIX_REGEX->match($value, $match);
			if ($prefixed){
				if (!$isMovie){
					return 'prefix-movieonly';
				}
				if (!isset(Episodes::$ALLOWED_PREFIXES[$match[1]])){
					$mostSimilar = null;
					$mostMatcing = 0;
					foreach (Episodes::$ALLOWED_PREFIXES as $prefix => $shorthand){
						foreach (array($prefix, $shorthand) as $test){
							$matchingChars = similar_text(strtolower($match[1]), strtolower($test));
							if ($matchingChars >= 3 && $matchingChars > $mostMatcing){
								$mostMatcing = $matchingChars;
								$mostSimilar = $prefix;
							}
						}
					}
					Response::Fail("Unsupported prefix: {$match[1]}. ".(isset($mostSimilar) ? "<em>Did you mean <span class='color-ui'>$mostSimilar</span></em>?" : 'Use a backslash if the colon is part of the title (e.g. <code>\:</code>)'));
				}

				$title = Episodes::RemoveTitlePrefix($value);
				if (Input::CheckStringLength($title, $range, $code))
					return $code;

				$value = "{$match[1]}: $title";
			}
			else if (Input::CheckStringLength($value, $range, $code))
				return $code;
		},array(
			Input::IN_RANGE => [5,35],
			Input::CUSTOM_ERROR_MESSAGES => array(
				Input::ERROR_MISSING => "$What title is missing",
				Input::ERROR_RANGE => "$What title must be between @min and @max characters",
				'prefix-movieonly' => "Prefixes can only be used for movies",
			)
		)))->out();
		CoreUtils::CheckStringValidity($insert['title'], "$What title", INVERSE_EP_TITLE_PATTERN);

		$airs = (new Input('airs','timestamp',array(
			Input::CUSTOM_ERROR_MESSAGES => array(
				Input::ERROR_MISSING => 'No air date & time specified',
				Input::ERROR_INVALID => 'Invalid air date and/or time (@value) specified'
			)
		)))->out();
		if (empty($airs))
			Response::Fail('Please specify an air date & time');
		$insert['airs'] = date('c',strtotime('this minute', $airs));

		if ($editing){
			if (!$Database->whereEp($Episode)->update('episodes', $insert))
				Response::DBError('Updating episode failed');
		}
		else if (!$Database->insert('episodes', $insert))
			Response::DBError('Episode creation failed');

		if (!$editing || $SeasonChanged || $EpisodeChanged){
			if ($isMovie){
				if ($EpisodeChanged){
					$TagName = CGUtils::CheckEpisodeTagName("movie#{$insert['episode']}");
					$MovieTag = $CGDb->where('name', $editing ? "movie#{$Episode->episode}" : $TagName)->getOne('tags', 'tid');

					if (!empty($MovieTag)){
						if ($editing)
							$CGDb->where('tid',$MovieTag['tid'])->update('tags', array(
								'name' => $TagName,
							));
					}
					else {
						if (!$CGDb->insert('tags', array(
							'name' => $TagName,
							'type' => 'ep',
						))) Response::DBError('Episode tag creation failed');
					}
				}
			}
			else if ($SeasonChanged || $EpisodeChanged){
				$TagName = CGUtils::CheckEpisodeTagName("s{$insert['season']}e{$insert['episode']}");
				$EpTag = $CGDb->where('name', $editing ? "s{$Episode->season}e{$Episode->episode}" : $TagName)->getOne('tags', 'tid');

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
					))) Response::DBError('Episode tag creation failed');
				}
			}
		}

		if ($editing){
			$logentry = array('target' => $Episode->formatTitle(AS_ARRAY,'id'));
			$changes = 0;
			if (!empty($Episode->airs))
				$Episode->airs = date('c',strtotime($Episode->airs));
			foreach (array('season', 'episode', 'twoparter', 'title', 'airs') as $k){
				if (isset($insert[$k]) && $insert[$k] != $Episode->{$k}){
					$logentry["old$k"] = $Episode->{$k};
					$logentry["new$k"] = $insert[$k];
					$changes++;
				}
			}
			if ($changes > 0)
				Log::Action('episode_modify',$logentry);
		}
		else Log::Action('episodes',array(
			'action' => 'add',
			'season' => $insert['season'],
			'episode' => $insert['episode'],
			'twoparter' => isset($insert['twoparter']) ? $insert['twoparter'] : 0,
			'title' => $insert['title'],
			'airs' => $insert['airs'],
		));
		if ($editing)
			Response::Done();
		Response::Done(array('url' => (new Episode($insert))->formatURL()));
	break;
}
