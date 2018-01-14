<?php

namespace App\Controllers;
use App\Auth;
use App\CGUtils;
use App\CoreUtils;
use App\CSRFProtection;
use App\DB;
use App\DeviantArt;
use App\Episodes;
use App\Input;
use App\Logs;
use App\Models\Appearance;
use App\Models\EpisodeVote;
use App\Models\Tag;
use App\Models\Tagged;
use App\Permission;
use App\Posts;
use App\Response;
use App\VideoProvider;
use App\Models\Episode;
use App\Models\EpisodeVideo;
use Peertopark\UriBuilder;

/** @property Episode $_episode */
class EpisodeController extends Controller {
	public function latest(){
		$CurrentEpisode = Episodes::getLatest();
		if (empty($CurrentEpisode))
			CoreUtils::loadPage(__CLASS__.'::view', [
				'title' => 'Home',
			]);

		Episodes::loadPage($CurrentEpisode);
	}

	public function page($params){
		Episodes::loadPage($params['id'] ?? null);
	}

	private $_episode;
	private function _getEpisode($params, $required = true){
		$EpData = Episode::parseID(!empty($params['id']) ? $params['id'] : null);
		if (!empty($EpData)){
			$this->_episode = Episodes::getActual($EpData['season'], $EpData['episode'], Episodes::ALLOW_MOVIES);
			if (empty($this->_episode))
				Response::fail('There’s no episode with this season & episode number');
		}
		else if ($required)
			CoreUtils::notFound();
	}

	public function postList($params){
		$this->_getEpisode($params);

		$section = $_GET['section'];
		$only = $section === 'requests' ? ONLY_REQUESTS : ONLY_RESERVATIONS;
		$posts = Posts::get($this->_episode, $only, Permission::sufficient('staff'));

		switch ($only){
			case ONLY_REQUESTS: $rendered = Posts::getRequestsSection($posts); break;
			case ONLY_RESERVATIONS: $rendered = Posts::getReservationsSection($posts); break;
			default:
				Response::fail('This should never happen');
		}
		Response::done(['render' => $rendered]);
	}

	public function get($params){
		CSRFProtection::protect();
		$this->_getEpisode($params);

		Response::done([
			'ep' => $this->_episode->to_array(),
			'epid' => $this->_episode->getID(),
		]);
	}

	public function _addEdit($params, $action){
		CSRFProtection::protect();

		if (!Permission::sufficient('staff'))
			Response::fail();

		$editing = $action === 'set';
		if ($editing)
			$this->_getEpisode($params);

		$update = [];
		if (!$editing)
			$update['posted_by'] = Auth::$user->id;
		else $isMovie = $this->_episode->season === 0;

		$update['season'] = Episodes::validateSeason(Episodes::ALLOW_MOVIES);
		if (!$editing)
			$isMovie = $update['season'] === 0;
		$update['episode'] = Episodes::validateEpisode($isMovie);
		$What = $isMovie ? 'Movie' : 'Episode';

		$EpisodeChanged = true;
		$SeasonChanged = true;
		$OriginalEpisode = $editing ? $this->_episode->episode : $update['episode'];
		$OriginalSeason = $editing ? $this->_episode->season : $update['season'];
		if ($editing){
			$SeasonChanged = $isMovie ? false : $update['season'] !== $this->_episode->season;
			$EpisodeChanged = $update['episode'] !== $this->_episode->episode;
			if ($SeasonChanged || $EpisodeChanged){
				$Target = Episodes::getActual(
					$update['season'] ?? $this->_episode->season,
					$update['episode'] ?? $this->_episode->episode,
					Episodes::ALLOW_MOVIES
				);
				if (!empty($Target))
					Response::fail('There’s already an episode with the same season & episode number');
			}
		}
		else {
			$MatchingID = Episode::find_by_season_and_episode($update['season'], $update['episode']);
			if (!empty($MatchingID))
				Response::fail(($isMovie?'A movie':'An episode').' with the same '.($isMovie?'overall':'season and episode').' number already exists');
		}

		if (!$isMovie)
			$update['no'] = (new Input('no','int', [
				Input::IS_OPTIONAL => true,
				Input::IN_RANGE => [1,255],
				Input::CUSTOM_ERROR_MESSAGES => [
				    Input::ERROR_INVALID => 'Overall episode number (@value) is invalid',
				    Input::ERROR_RANGE => 'Overall episode number must be between @min and @max',
				]
			]))->out();

		$update['twoparter'] = !$isMovie  && isset($_POST['twoparter']);
		if ($update['twoparter']){
			$nextPart = Episode::find_by_season_and_episode($update['season'], $update['episode']+1);
			if (!empty($nextPart))
				Response::fail("This episode cannot have two parts because {$nextPart->toURL()} already exists.");
		}

		$update['title'] = (new Input('title',function(&$value, $range) use ($isMovie){
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
						foreach ([$prefix, $shorthand] as $test){
							$matchingChars = similar_text(strtolower($match[1]), strtolower($test));
							if ($matchingChars >= 3 && $matchingChars > $mostMatcing){
								$mostMatcing = $matchingChars;
								$mostSimilar = $prefix;
							}
						}
					}
					Response::fail("Unsupported prefix: {$match[1]}. ".($mostSimilar !== null ? "<em>Did you mean <span class='color-ui'>$mostSimilar</span></em>?" : ''));
				}

				$title = Episodes::removeTitlePrefix($value);
				if (Input::checkStringLength($title, $range, $code))
					return $code;

				$value = "{$match[1]}: $title";
			}
			else if (Input::checkStringLength($value, $range, $code))
				return $code;
		}, [
			Input::IN_RANGE => [5,35],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => "$What title is missing",
				Input::ERROR_RANGE => "$What title must be between @min and @max characters",
				'prefix-movieonly' => 'Prefixes can only be used for movies',
			]
		]))->out();
		CoreUtils::checkStringValidity($update['title'], "$What title", INVERSE_EP_TITLE_PATTERN);

		$airs = (new Input('airs','timestamp', [
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'No air date & time specified',
				Input::ERROR_INVALID => 'Invalid air date and/or time (@value) specified'
			]
		]))->out();
		if (empty($airs))
			Response::fail('Please specify an air date & time');
		$update['airs'] = date('c',strtotime('this minute', $airs));

		$notes = (new Input('notes','text', [
			Input::IS_OPTIONAL => true,
			Input::IN_RANGE => [null,1000],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_RANGE => "$What notes cannot be longer than @max characters",
			]
		]))->out();
		if ($notes !== null){
			CoreUtils::checkStringValidity($notes, "$What notes", INVERSE_PRINTABLE_ASCII_PATTERN);
			$notes = CoreUtils::sanitizeHtml($notes, ['a'], ['a.href']);
			if (!$editing || $notes !== $this->_episode->notes)
				$update['notes'] = $notes;
		}
		else $update['notes'] = null;

		if ($editing){
			if (!DB::$instance->whereEp($this->_episode)->update(Episode::$table_name, $update))
				Response::dbError('Updating episode failed');
		}
		else if (!(new Episode($update))->save())
			Response::dbError('Episode creation failed');

		if (!$editing || $SeasonChanged || $EpisodeChanged){
			if ($isMovie){
				if ($EpisodeChanged){
					/** @var $TagName string */
					$TagName = CGUtils::normalizeEpisodeTagName("movie{$update['episode']}");
					/** @var $MovieTag Tag */
					$MovieTag = DB::$instance->where('name', $editing ? "movie{$OriginalEpisode}" : $TagName)->getOne('tags');

					if (!empty($MovieTag)){
						if ($editing){
							$MovieTag->name = $TagName;
							$MovieTag->save();
						}
					}
					else {
						if (!(new Tag([
							'name' => $TagName,
							'type' => 'ep',
						]))->save()) Response::dbError('Episode tag creation failed');
					}
				}
			}
			else if ($SeasonChanged || $EpisodeChanged){
				/** @var $TagName string */
				$TagName = CGUtils::normalizeEpisodeTagName("s{$update['season']}e{$update['episode']}");
				$EpTag = DB::$instance->where('name', $editing ? CGUtils::normalizeEpisodeTagName("s{$OriginalSeason}e{$OriginalEpisode}") : $TagName)->getOne('tags');

				if (!empty($EpTag)){
					if ($editing){
						$EpTag->name = $TagName;
						$EpTag->save();
					}
				}
				else {
					if (!(new Tag([
						'name' => $TagName,
						'type' => 'ep',
					]))->save()) Response::dbError('Episode tag creation failed');
				}
			}
		}

		if ($editing){
			$logentry = ['target' => $this->_episode->getID()];
			$changes = 0;
			if (!empty($this->_episode->airs))
				$this->_episode->airs = date('c',strtotime($this->_episode->airs));
			foreach (['season', 'episode', 'twoparter', 'title', 'airs'] as $k){
				if (isset($update[$k]) && $update[$k] != $this->_episode->{$k}){
					$logentry["old$k"] = $this->_episode->{$k};
					$logentry["new$k"] = $update[$k];
					$changes++;
				}
			}
			if ($changes > 0)
				Logs::logAction('episode_modify',$logentry);
		}
		else Logs::logAction('episodes', [
			'action' => 'add',
			'season' => $update['season'],
			'episode' => $update['episode'],
			'twoparter' => $update['twoparter'],
			'title' => $update['title'],
			'airs' => $update['airs'],
		]);
		if ($editing)
			Response::done();
		Response::done(['url' => (new Episode($update))->toURL()]);
	}

	public function set($params){
		$this->_addEdit($params, 'set');
	}

	public function add($params){
		$this->_addEdit($params, 'add');
	}

	public function delete($params){
		$this->_getEpisode($params);

		if (!Permission::sufficient('staff'))
			Response::fail();

		if (!DB::$instance->whereEp($this->_episode)->delete('episodes'))
			Response::dbError();
		Logs::logAction('episodes', [
			'action' => 'del',
			'season' => $this->_episode->season,
			'episode' => $this->_episode->episode,
			'twoparter' => $this->_episode->twoparter,
			'title' => $this->_episode->title,
			'airs' => $this->_episode->airs,
		]);
		DB::$instance->where('name', "s{$this->_episode->season}e{$this->_episode->episode}")->where('uses',0)->delete('tags');
		Response::success('Episode deleted successfuly', [
			'upcoming' => CoreUtils::getSidebarUpcoming(NOWRAP),
		]);
	}

	public function vote($params){
		CSRFProtection::protect();
		$this->_getEpisode($params);

		if (isset($_REQUEST['detail'])){
			$VoteCountQuery = DB::$instance->query(
				'SELECT count(*) as value, vote as label
				FROM episode_votes v
				WHERE season = ? AND episode = ?
				GROUP BY v.vote
				ORDER BY v.vote ASC', [$this->_episode->season, $this->_episode->episode]);
			$VoteCounts = [];
			foreach ($VoteCountQuery as $row)
				$VoteCounts[$row['label']] = $row['value'];

			Response::done(['data' => $VoteCounts]);
		}
		else if (isset($_REQUEST['html']))
			Response::done(['html' => Episodes::getSidebarVoting($this->_episode)]);

		if (!Permission::sufficient('user'))
			Response::fail();

		if (!$this->_episode->aired)
			Response::fail('You can only vote on this episode after it has aired.');

		$UserVote = Episodes::getUserVote($this->_episode);
		if (!empty($UserVote))
			Response::fail('You already voted for this episode');

		$vote = (new Input('vote','int', [
			Input::IN_RANGE => [1,5],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Vote value missing from request',
				Input::ERROR_RANGE => 'Vote value must be an integer between @min and @max (inclusive)',
			]
		]))->out();

		if (!(new EpisodeVote([
			'season' => $this->_episode->season,
			'episode' => $this->_episode->episode,
			'user_id' => Auth::$user->id,
			'vote' => $vote,
		]))->save()) Response::dbError();
		$this->_episode->updateScore();
		Response::done(['newhtml' => Episodes::getSidebarVoting($this->_episode)]);
	}

	public function getVideoEmbeds($params){
		$this->_getEpisode($params);

		Response::done(Episodes::getVideoEmbeds($this->_episode));
	}

	private function _getVideoData($params){
		$this->_getEpisode($params);

		$return = [
			'twoparter' => $this->_episode->twoparter,
			'vidlinks' => [],
			'fullep' => [],
			'airs' => date('c',strtotime($this->_episode->airs)),
		];
		$Vids = $this->_episode->videos;
		foreach ($Vids as $part => $vid){
			if (!empty($vid->id))
				$return['vidlinks']["{$vid->provider}_{$vid->part}"] = VideoProvider::getEmbed($vid, VideoProvider::URL_ONLY);
			if ($vid->fullep)
				$return['fullep'][] = $vid->provider;
		}
		Response::done($return);
	}

	private function _setVideoData($params){
		CSRFProtection::protect();
		$this->_getEpisode($params);

		foreach (['yt', 'dm'] as $provider){
			for ($part = 1; $part <= ($this->_episode->twoparter?2:1); $part++){
				$set = null;
				$PostKey = "{$provider}_$part";
				if (!empty($_POST[$PostKey])){
					$Provider = Episodes::VIDEO_PROVIDER_NAMES[$provider];
					try {
						$vidProvider = new VideoProvider(DeviantArt::trimOutgoingGateFromUrl($_POST[$PostKey]));
					}
					catch (\Exception $e){
						Response::fail("$Provider link issue: ".$e->getMessage());
					};
					if ($vidProvider->episodeVideo == null || $vidProvider->episodeVideo->provider !== $provider)
						Response::fail("Incorrect $Provider URL specified");
					/** @noinspection PhpUndefinedFieldInspection */
					$set = $vidProvider::$id;
				}

				$fullep = $this->_episode->twoparter ? false : true;
				if ($part === 1 && $this->_episode->twoparter && isset($_POST["{$PostKey}_full"])){
					$NextPart = $provider.'_'.($part+1);
					$_POST[$NextPart] = null;
					$fullep = true;
				}

				$videocount = DB::$instance
					->whereEp($this->_episode)
					->where('provider', $provider)
					->where('part', $part)
					->count('episode_videos');
				if ($videocount === 0){
					if (!empty($set))
						EpisodeVideo::create([
							'season' => $this->_episode->season,
							'episode' => $this->_episode->episode,
							'provider' => $provider,
							'part' => $part,
							'id' => $set,
							'fullep' => $fullep,
						]);
				}
				else {
					DB::$instance
						->whereEp($this->_episode)
						->where('provider', $provider)
						->where('part', $part);
					if (empty($set))
						DB::$instance->delete('episode_videos');
					else DB::$instance->update('episode_videos', [
						'id' => $set,
						'fullep' => $fullep,
						'modified' => date('c'),
					]);
				}
			}
		}

		Response::success('Links updated', ['epsection' => Episodes::getVideosHTML($this->_episode)]);
	}

	public function videoData($params){
		if (!isset($_GET['action']))
			Response::fail('Missing action');

		switch ($_GET['action']){
			case 'get': $this->_getVideoData($params); break;
			case 'set': $this->_setVideoData($params); break;
			default: CoreUtils::notFound();
		}
	}

	public function brokenVideos($params){
		$this->_getEpisode($params);

		$removed = 0;
		foreach ($this->_episode->videos as $k => $video){
			if (!$video->isBroken())
				continue;

			$removed++;
			$video->delete();
			Logs::logAction('video_broken', [
				'season' => $this->_episode->season,
				'episode' => $this->_episode->episode,
				'provider' => $video->provider,
				'id' => $video->id,
			]);
			unset($this->_episode->videos[$k]);
		}

		if ($removed === 0)
			return Response::success('No broken videos found under this '.($this->_episode->is_movie?'movie':'episode').'.');

		Response::success("$removed video link".($removed===1?' has':'s have').' been removed from the site. Thank you for letting us know.', [
			'epsection' => Episodes::getVideosHTML($this->_episode, NOWRAP),
		]);
	}

	private function _getGuideRelations($params){
		$this->_getEpisode($params);

		$EpTagIDs = $this->_episode->getTagIDs();
		if (empty($EpTagIDs))
			Response::fail('The episode has no associated tags!');

		$TaggedAppearanceIDs = [];
		foreach ($EpTagIDs as $tid){
			$Tagged = Tagged::by_tag($tid);
			foreach ($Tagged as $tg)
				$TaggedAppearanceIDs[$tg->appearance_id] = true;
		}

		/** @var $Appearances Appearance[] */
		$Appearances = DB::$instance->disableAutoClass()
			->where('ishuman', $this->_episode->is_movie)
			->where('id',0,'!=')
			->orderBy('label')
			->get('appearances',null,'id,label');

		$Sorted = [
			'unlinked' => [],
			'linked' => [],
		];
		foreach ($Appearances as $a)
			$Sorted[isset($TaggedAppearanceIDs[$a['id']]) ? 'linked' : 'unlinked'][] = $a;

		Response::done($Sorted);
	}

	private function _setGuideRelations($params){
		$this->_getEpisode($params);

		/** @var $AppearanceIDs int[] */
		$AppearanceIDs = (new Input('ids','int[]', [
			Input::IS_OPTIONAL => true,
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Missing appearance ID list',
				Input::ERROR_INVALID => 'Appearance ID list is invalid',
			]
		]))->out();

		$EpTagIDs = $this->_episode->getTagIDs();
		if (empty($EpTagIDs))
			Response::fail('The episode has no associated tag(s)!');
		/** @var $Tag Tag */
		$Tag = DB::$instance->where('id', $EpTagIDs)->orderByLiteral('char_length(name)','DESC')->getOne('tags');

		if (!empty($AppearanceIDs)){
			foreach ($AppearanceIDs as $appearance_id){
				if (!Tagged::multi_is($EpTagIDs, $appearance_id))
					$Tag->add_to($appearance_id);
			}
			DB::$instance->where('appearance_id',$AppearanceIDs,'!=');
		}
		DB::$instance->where('tag_id',$EpTagIDs)->delete('tagged');

		Response::done(['section' => Episodes::getAppearancesSectionHTML($this->_episode)]);
	}

	public function guideRelations($params){
		$action = $_GET['action'];

		switch ($action){
			case 'get': $this->_getGuideRelations($params); break;
			case 'set': $this->_setGuideRelations($params); break;
			default: CoreUtils::notFound();
		}
	}

	public function nextup(){
		$NextEpisode = DB::$instance->where('airs > CURRENT_TIMESTAMP')->orderBy('airs')->getOne('episodes');
		if (empty($NextEpisode))
			Response::fail('No upcoming episode found');

		Response::done($NextEpisode->to_array([
			'only' => ['episode','airs','season','title'],
		]));
	}

	public function prefill(){
		CSRFProtection::protect();

		if (Permission::insufficient('staff'))
			Response::fail();

		/** @var $LastAdded Episode */
		$LastAdded = DB::$instance->orderBy('posted','DESC')->where('season != 0')->getOne(Episode::$table_name);

		if (empty($LastAdded))
			Response::fail('No last added episode found');

		Response::done([
			'season' => $LastAdded->season,
			'episode' => min($LastAdded->episode + 1, 26),
			'no' => $LastAdded->no + ($LastAdded->twoparter ? 2 : 1),
			'airday' => $LastAdded->airs->add(new \DateInterval('P1W'))->format('Y-m-d'),
		]);
	}
}
