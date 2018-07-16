<?php

namespace App\Controllers;
use App\Auth;
use App\CGUtils;
use App\CoreUtils;
use App\CSRFProtection;
use App\DB;
use App\DeviantArt;
use App\Episodes;
use App\HTTP;
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

class EpisodeController extends Controller {
	public function latest(){
		$CurrentEpisode = Episodes::getLatest();
		if (empty($CurrentEpisode))
			CoreUtils::loadPage(__CLASS__.'::view', [
				'title' => 'Home',
			]);

		Episodes::loadPage($CurrentEpisode);
	}

	public function view($params){
		if (empty($params['id']))
			CoreUtils::notFound();

		$EpData = Episode::parseID($params['id']);
		if ($EpData['season'] === 0)
			HTTP::tempRedirect('/movie/'.$EpData['episode']);

		$CurrentEpisode = empty($EpData)
			? Episodes::getLatest()
			: Episodes::getActual($EpData['season'], $EpData['episode']);

		Episodes::loadPage($CurrentEpisode);
	}

	/** @var Episode */
	private $episode;
	private function load_episode($params){
		$EpData = Episode::parseID(!empty($params['id']) ? $params['id'] : null);
		if (empty($EpData))
			CoreUtils::notFound();

		$this->episode = Episodes::getActual($EpData['season'], $EpData['episode'], Episodes::ALLOW_MOVIES);
		if (empty($this->episode))
			Response::fail("There's no episode with this season & episode number");
	}

	public function postList($params){
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		$this->load_episode($params);

		$section = $_GET['section'];
		$only = $section === 'requests' ? ONLY_REQUESTS : ONLY_RESERVATIONS;
		$posts = Posts::get($this->episode, $only, Permission::sufficient('staff'));

		switch ($only){
			case ONLY_REQUESTS: $rendered = Posts::getRequestsSection($posts); break;
			case ONLY_RESERVATIONS: $rendered = Posts::getReservationsSection($posts); break;
			default:
				Response::fail('This should never happen');
		}
		Response::done(['render' => $rendered]);
	}

	public function api($params){
		if ($this->action !== 'GET' && Permission::insufficient('staff'))
			Response::fail();

		if (!$this->creating)
			$this->load_episode($params);

		switch ($this->action){
			case 'GET':
				Response::done([
					'ep' => $this->episode->to_array(),
				]);
			break;
			case 'POST':
			case 'PUT':
				$update = [];
				if ($this->creating)
					$update['posted_by'] = Auth::$user->id;
				else $isMovie = $this->episode->season === 0;

				$update['season'] = Episodes::validateSeason(Episodes::ALLOW_MOVIES);
				if ($this->creating)
					$isMovie = $update['season'] === 0;
				$update['episode'] = Episodes::validateEpisode($isMovie);
				$What = $isMovie ? 'Movie' : 'Episode';

				$EpisodeChanged = true;
				$SeasonChanged = true;
				$OriginalEpisode = $this->creating ? $update['episode'] : $this->episode->episode;
				$OriginalSeason = $this->creating ? $update['season'] : $this->episode->season;
				if ($this->creating){
					$MatchingID = Episode::find_by_season_and_episode($update['season'], $update['episode']);
					if (!empty($MatchingID))
						Response::fail(($isMovie?'A movie':'An episode').' with the same '.($isMovie?'overall':'season and episode').' number already exists');
				}
				else {
					$SeasonChanged = $isMovie ? false : $update['season'] !== $this->episode->season;
					$EpisodeChanged = $update['episode'] !== $this->episode->episode;
					if ($SeasonChanged || $EpisodeChanged){
						$Target = Episodes::getActual(
							$update['season'] ?? $this->episode->season,
							$update['episode'] ?? $this->episode->episode,
							Episodes::ALLOW_MOVIES
						);
						if (!empty($Target))
							Response::fail("There's already an episode with the same season & episode number");
					}
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

				$update['twoparter'] = !$isMovie  && isset($_REQUEST['twoparter']);
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
						if (!isset(Episodes::ALLOWED_PREFIXES[$match[1]])){
							$mostSimilar = null;
							$mostMatcing = 0;
							foreach (Episodes::ALLOWED_PREFIXES as $prefix => $shorthand){
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
					if ($this->creating || $notes !== $this->episode->notes)
						$update['notes'] = $notes;
				}
				else $update['notes'] = null;

				if (!$this->creating){
					if (!DB::$instance->whereEp($this->episode)->update(Episode::$table_name, $update))
						Response::dbError('Updating episode failed');
				}
				else if (!(new Episode($update))->save())
					Response::dbError('Episode creation failed');

				if ($this->creating || $SeasonChanged || $EpisodeChanged){
					if ($isMovie){
						if ($EpisodeChanged){
							/** @var $TagName string */
							$TagName = CGUtils::normalizeEpisodeTagName("movie{$update['episode']}");
							/** @var $MovieTag Tag */
							$MovieTag = DB::$instance->where('name', $this->creating ? $TagName : "movie{$OriginalEpisode}")->getOne('tags');

							if (!empty($MovieTag)){
								if (!$this->creating){
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
						$EpTag = DB::$instance->where('name', $this->creating ? $TagName : CGUtils::normalizeEpisodeTagName("s{$OriginalSeason}e{$OriginalEpisode}"))->getOne('tags');

						if (!empty($EpTag)){
							if (!$this->creating){
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

				if ($this->creating){
					Logs::logAction('episodes', [
						'action' => 'add',
						'season' => $update['season'],
						'episode' => $update['episode'],
						'twoparter' => $update['twoparter'],
						'title' => $update['title'],
						'airs' => $update['airs'],
					]);
					Response::done(['url' => (new Episode($update))->toURL()]);
				}
				else {
					$logEntry = ['target' => $this->episode->getID()];
					$changes = 0;
					if (!empty($this->episode->airs))
						$this->episode->airs = date('c',strtotime($this->episode->airs));
					foreach (['season', 'episode', 'twoparter', 'title', 'airs'] as $k){
						if (isset($update[$k]) && $update[$k] != $this->episode->{$k}){
							$logEntry["old$k"] = $this->episode->{$k};
							$logEntry["new$k"] = $update[$k];
							$changes++;
						}
					}
					if ($changes > 0)
						Logs::logAction('episode_modify',$logEntry);
					Response::done();
				}
			break;
			case 'DELETE':
				if (!DB::$instance->whereEp($this->episode)->delete('episodes'))
					Response::dbError();
				Logs::logAction('episodes', [
					'action' => 'del',
					'season' => $this->episode->season,
					'episode' => $this->episode->episode,
					'twoparter' => $this->episode->twoparter,
					'title' => $this->episode->title,
					'airs' => $this->episode->airs,
				]);
				DB::$instance->where('name', "s{$this->episode->season}e{$this->episode->episode}")->where('uses',0)->delete('tags');
				Response::success('Episode deleted successfully', [
					'upcoming' => CoreUtils::getSidebarUpcoming(NOWRAP),
				]);
			break;
			default:
				CoreUtils::notAllowed();
		}
	}

	public function voteApi($params){
		$this->load_episode($params);

		switch ($this->action){
			case 'GET':
				if (isset($_REQUEST['html']))
					Response::done(['html' => Episodes::getSidebarVoting($this->episode)]);

				$VoteCountQuery = DB::$instance->query(
					'SELECT count(*) as value, vote as label
					FROM episode_votes v
					WHERE season = ? AND episode = ?
					GROUP BY v.vote
					ORDER BY v.vote ASC', [$this->episode->season, $this->episode->episode]);
				$VoteCounts = [];
				foreach ($VoteCountQuery as $row)
					$VoteCounts[$row['label']] = $row['value'];

				Response::done(['data' => $VoteCounts]);
			break;
			case 'POST':
				if (!Auth::$signed_in)
					Response::fail();

				if (!$this->episode->aired)
					Response::fail('You can only vote on this episode after it has aired.');

				$UserVote = $this->episode->getVoteOf(Auth::$user);
				if (!empty($UserVote))
					Response::fail('You already voted for this episode');

				$voteValue = (new Input('vote','int', [
					Input::IN_RANGE => [1,5],
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Vote value missing from request',
						Input::ERROR_RANGE => 'Vote value must be an integer between @min and @max (inclusive)',
					]
				]))->out();

				$vote = new EpisodeVote();
				$vote->season = $this->episode->season;
				$vote->episode = $this->episode->episode;
				$vote->user_id = Auth::$user->id;
				$vote->vote = $voteValue;
				if (!$vote->save())
					Response::dbError();

				$this->episode->updateScore();
				Response::done(['newhtml' => Episodes::getSidebarVoting($this->episode)]);
			break;
			default:
				CoreUtils::notAllowed();
		}
	}

	public function videoEmbeds($params){
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		$this->load_episode($params);

		Response::done($this->episode->getVideoEmbeds());
	}

	public function videoDataApi($params){
		if (Permission::insufficient('staff'))
			Response::fail();

		$this->load_episode($params);

		switch ($this->action){
			case 'GET':
				$return = [
					'twoparter' => $this->episode->twoparter,
					'vidlinks' => [],
					'fullep' => [],
					'airs' => date('c',strtotime($this->episode->airs)),
				];
				$Vids = $this->episode->videos;
				foreach ($Vids as $part => $vid){
					if (!empty($vid->id))
						$return['vidlinks']["{$vid->provider}_{$vid->part}"] = VideoProvider::getEmbed($vid, VideoProvider::URL_ONLY);
					if ($vid->fullep)
						$return['fullep'][] = $vid->provider;
				}
				Response::done($return);
			break;
			case 'PUT':
				foreach (array_keys(Episodes::VIDEO_PROVIDER_NAMES) as $provider){
					for ($part = 1; $part <= ($this->episode->twoparter?2:1); $part++){
						$set = null;
						$PostKey = "{$provider}_$part";
						if (!empty($_REQUEST[$PostKey])){
							$Provider = Episodes::VIDEO_PROVIDER_NAMES[$provider];
							try {
								$vidProvider = new VideoProvider(DeviantArt::trimOutgoingGateFromUrl($_REQUEST[$PostKey]));
							}
							catch (\Exception $e){
								Response::fail("$Provider link issue: ".$e->getMessage());
							};
							if ($vidProvider->episodeVideo == null || $vidProvider->episodeVideo->provider !== $provider)
								Response::fail("Incorrect $Provider URL specified");
							/** @noinspection PhpUndefinedFieldInspection */
							$set = $vidProvider::$id;
						}

						$fullep = $this->episode->twoparter ? false : true;
						if ($part === 1 && $this->episode->twoparter && isset($_REQUEST["{$PostKey}_full"])){
							$NextPart = $provider.'_'.($part+1);
							$_REQUEST[$NextPart] = null;
							$fullep = true;
						}

						$videocount = DB::$instance
							->whereEp($this->episode)
							->where('provider', $provider)
							->where('part', $part)
							->count('episode_videos');
						if ($videocount === 0){
							if (!empty($set))
								EpisodeVideo::create([
									'season' => $this->episode->season,
									'episode' => $this->episode->episode,
									'provider' => $provider,
									'part' => $part,
									'id' => $set,
									'fullep' => $fullep,
								]);
						}
						else {
							DB::$instance
								->whereEp($this->episode)
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

				Response::success('Links updated', ['epsection' => Episodes::getVideosHTML($this->episode)]);
			break;
			default:
				CoreUtils::notAllowed();
		}
	}

	public function guideRelationsApi($params){
		if (Permission::insufficient('staff'))
			Response::fail();

		$this->load_episode($params);

		switch ($this->action){
			case 'GET':
				$EpTagIDs = $this->episode->getTagIDs();

				$TaggedAppearanceIDs = [];
				if (!empty($EpTagIDs)){
					foreach ($EpTagIDs as $tid){
						$Tagged = Tagged::by_tag($tid);
						foreach ($Tagged as $tg)
							$TaggedAppearanceIDs[$tg->appearance_id] = true;
					}
				}

				/** @var $Appearances Appearance[] */
				$Appearances = DB::$instance->disableAutoClass()
					->where('ishuman', $this->episode->is_movie)
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
			break;
			case 'PUT':
				/** @var $AppearanceIDs int[] */
				$AppearanceIDs = (new Input('ids','int[]', [
					Input::IS_OPTIONAL => true,
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Missing appearance ID list',
						Input::ERROR_INVALID => 'Appearance ID list is invalid',
					]
				]))->out();

				$EpTagIDs = $this->episode->getTagIDs();
				if (empty($EpTagIDs)){
					$Tag = new Tag();
					$Tag->name = CGUtils::normalizeEpisodeTagName($this->episode->getID());
					$Tag->type = 'ep';
					$Tag->save();
					$EpTagIDs[] = $Tag->id;
				}
				/** @var $Tag Tag */
				else $Tag = DB::$instance->where('id', $EpTagIDs)->orderByLiteral('char_length(name)','DESC')->getOne('tags');

				if (!empty($AppearanceIDs)){
					foreach ($AppearanceIDs as $appearance_id){
						if (!Tagged::multi_is($EpTagIDs, $appearance_id))
							$Tag->add_to($appearance_id);
					}
					DB::$instance->where('appearance_id',$AppearanceIDs,'!=');
				}
				DB::$instance->where('tag_id',$EpTagIDs)->delete('tagged');

				Response::done(['section' => Episodes::getAppearancesSectionHTML($this->episode)]);
			break;
			default:
				CoreUtils::notAllowed();
		}

	}

	public function brokenVideos($params){
		$this->load_episode($params);

		$removed = 0;
		foreach ($this->episode->videos as $k => $video){
			if (!$video->isBroken())
				continue;

			$removed++;
			$video->delete();
			Logs::logAction('video_broken', [
				'season' => $this->episode->season,
				'episode' => $this->episode->episode,
				'provider' => $video->provider,
				'id' => $video->id,
			]);
			unset($this->episode->videos[$k]);
		}

		if ($removed === 0)
			return Response::success('No broken videos found under this '.($this->episode->is_movie?'movie':'episode').'.');

		Response::success("$removed video link".($removed===1?' has':'s have').' been removed from the site. Thank you for letting us know.', [
			'epsection' => Episodes::getVideosHTML($this->episode, NOWRAP),
		]);
	}

	public function next(){
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		$NextEpisode = DB::$instance->where('airs > now()')->orderBy('airs')->getOne('episodes');
		if (empty($NextEpisode))
			Response::fail("The show is on hiatus, the next episode's title and air date is unknown.");

		Response::done($NextEpisode->to_array([
			'only' => ['episode','airs','season','title'],
		]));
	}

	public function prefill(){
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		if (Permission::insufficient('staff'))
			Response::fail();

		/** @var $LastAdded Episode */
		$LastAdded = DB::$instance->orderBy('no','DESC')->where('season != 0')->getOne(Episode::$table_name);
		if (empty($LastAdded))
			Response::fail('No last added episode found');

		$season = $LastAdded->season;
		if ($LastAdded->twoparter && $LastAdded->episode + 1 === 26){
			$season++;
			$episode = 1;
			$airs = date('Y-m-d',strtotime('this saturday'));
		}
		else {
			$episode = min($LastAdded->episode + 1, 26);
			$airs = $LastAdded->airs->add(new \DateInterval('P1W'))->format('Y-m-d');
		}
		Response::done([
			'season' => $season,
			'episode' => $episode,
			'no' => $LastAdded->no + ($LastAdded->twoparter ? 2 : 1),
			'airday' => $airs,
		]);
	}
}
