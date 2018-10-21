<?php

namespace App\Controllers;

use ActiveRecord\Table;
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
use App\Regexes;
use App\Response;
use App\Time;
use App\TMDBHelper;
use App\Twig;
use App\VideoProvider;
use App\Models\Episode;
use App\Models\EpisodeVideo;

class EpisodeController extends Controller {
	public function latest():void {
		$latest_episode = Episodes::getLatest();
		if (empty($latest_episode))
			CoreUtils::loadPage(__CLASS__.'::view', [
				'title' => 'Home',
			]);

		HTTP::tempRedirect($latest_episode->toURL());
	}

	public function view($params):void {
		if (empty($params['id']))
			CoreUtils::notFound();

		$ep_data = Episode::parseID($params['id']);
		if ($ep_data['season'] === 0)
			HTTP::tempRedirect('/movie/'.$ep_data['episode']);

		$current_episode = empty($ep_data)
			? Episodes::getLatest()
			: Episodes::getActual($ep_data['season'], $ep_data['episode']);

		Episodes::loadPage($current_episode);
	}

	/** @var Episode */
	private $episode;

	private function load_episode($params):void {
		$ep_data = Episode::parseID(!empty($params['id']) ? $params['id'] : null);
		if (empty($ep_data))
			CoreUtils::notFound();

		$this->episode = Episodes::getActual($ep_data['season'], $ep_data['episode'], Episodes::ALLOW_MOVIES);
		if (empty($this->episode))
			Response::fail("There's no episode with this season & episode number");
	}

	public function postList($params):void {
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		$this->load_episode($params);

		$section = $_GET['section'];
		$only = $section === 'requests' ? ONLY_REQUESTS : ONLY_RESERVATIONS;

		switch ($only){
			case ONLY_REQUESTS:
				$requests = $this->episode->getRequests();
				$rendered = Posts::getRequestsSection($requests);
			break;
			case ONLY_RESERVATIONS:
				$reservations = $this->episode->getReservations();
				$rendered = Posts::getReservationsSection($reservations);
			break;
			default:
				Response::fail('This should never happen');
		}
		Response::done(['render' => $rendered]);
	}

	public function api($params):void {
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
				else $is_movie = $this->episode->season === 0;

				$update['season'] = Episodes::validateSeason(Episodes::ALLOW_MOVIES);
				if ($this->creating)
					$is_movie = $update['season'] === 0;
				$update['episode'] = Episodes::validateEpisode($is_movie);
				$what = $is_movie ? 'Movie' : 'Episode';

				$episode_changed = true;
				$season_changed = true;
				$original_episode = $this->creating ? $update['episode'] : $this->episode->episode;
				$original_season = $this->creating ? $update['season'] : $this->episode->season;
				if ($this->creating){
					$matching_id = Episode::find_by_season_and_episode($update['season'], $update['episode']);
					if (!empty($matching_id)){
						$a_what = $is_movie ? 'A movie' : 'An episode';
						$same_what = $is_movie ? 'overall' : 'season and episode';
						Response::fail("$a_what with the same $same_what number already exists");
					}
				}
				else {
					$season_changed = $is_movie ? false : $update['season'] !== $this->episode->season;
					$episode_changed = $update['episode'] !== $this->episode->episode;
					if ($season_changed || $episode_changed){
						$target = Episodes::getActual(
							$update['season'] ?? $this->episode->season,
							$update['episode'] ?? $this->episode->episode,
							Episodes::ALLOW_MOVIES
						);
						if (!empty($target))
							Response::fail("There's already an episode with the same season & episode number");
					}
				}

				if (!$is_movie)
					$update['no'] = (new Input('no', 'int', [
						Input::IS_OPTIONAL => true,
						Input::IN_RANGE => [1, 255],
						Input::CUSTOM_ERROR_MESSAGES => [
							Input::ERROR_INVALID => 'Overall episode number (@value) is invalid',
							Input::ERROR_RANGE => 'Overall episode number must be between @min and @max',
						],
					]))->out();

				$update['twoparter'] = !$is_movie && isset($_REQUEST['twoparter']);
				if ($update['twoparter']){
					$next_part = Episode::find_by_season_and_episode($update['season'], $update['episode'] + 1);
					if (!empty($next_part))
						Response::fail("This episode cannot have two parts because {$next_part->toURL()} already exists.");
				}

				$update['title'] = (new Input('title', function (&$value, $range) use ($is_movie) {
					$prefixed = Regexes::$ep_title_prefix->match($value, $match);
					if ($prefixed){
						if (!$is_movie){
							return 'prefix-movie-only';
						}
						if (!isset(Episodes::ALLOWED_PREFIXES[$match[1]])){
							$most_similar = null;
							$most_matching = 0;
							foreach (Episodes::ALLOWED_PREFIXES as $prefix => $shorthand){
								foreach ([$prefix, $shorthand] as $test){
									$matching_chars = similar_text(strtolower($match[1]), strtolower($test));
									if ($matching_chars >= 3 && $matching_chars > $most_matching){
										$most_matching = $matching_chars;
										$most_similar = $prefix;
									}
								}
							}
							$did_you_mean = $most_similar !== null ? "<em>Did you mean <span class='color-ui'>$most_similar</span></em>?" : '';
							Response::fail("Unsupported prefix: {$match[1]}. $did_you_mean");
						}

						$title = Episodes::removeTitlePrefix($value);
						if (Input::checkStringLength($title, $range, $code))
							return $code;

						$value = "{$match[1]}: $title";
					}
					else if (Input::checkStringLength($value, $range, $code))
						return $code;
				}, [
					Input::IN_RANGE => [5, 35],
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => "$what title is missing",
						Input::ERROR_RANGE => "$what title must be between @min and @max characters",
						'prefix-movie-only' => 'Prefixes can only be used for movies',
					],
				]))->out();
				CoreUtils::checkStringValidity($update['title'], "$what title", INVERSE_EP_TITLE_PATTERN);

				$airs = (new Input('airs', 'timestamp', [
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'No air date & time specified',
						Input::ERROR_INVALID => 'Invalid air date and/or time (@value) specified',
					],
				]))->out();
				if (empty($airs))
					Response::fail('Please specify an air date & time');
				$update['airs'] = date('c', strtotime('this minute', $airs));

				$notes = (new Input('notes', 'text', [
					Input::IS_OPTIONAL => true,
					Input::IN_RANGE => [null, 1000],
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_RANGE => "$what notes cannot be longer than @max characters",
					],
				]))->out();
				if ($notes !== null){
					CoreUtils::checkStringValidity($notes, "$what notes", INVERSE_PRINTABLE_ASCII_PATTERN);
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

				if ($this->creating || $season_changed || $episode_changed){
					if ($is_movie){
						if ($episode_changed){
							/** @var $tag_name string */
							$tag_name = CGUtils::normalizeEpisodeTagName("movie{$update['episode']}");
							/** @var $movie_tag Tag */
							$movie_tag = DB::$instance->where('name', $this->creating ? $tag_name : "movie{$original_episode}")->getOne('tags');

							if (!empty($movie_tag)){
								if (!$this->creating){
									$movie_tag->name = $tag_name;
									$movie_tag->save();
								}
							}
							else if (!(new Tag([
								'name' => $tag_name,
								'type' => 'ep',
							]))->save()
							) Response::dbError('Episode tag creation failed');
						}
					}
					else if ($season_changed || $episode_changed){
						/** @var $TagName string */
						$tag_name = CGUtils::normalizeEpisodeTagName("s{$update['season']}e{$update['episode']}");
						$ep_tag = DB::$instance->where('name', $this->creating ? $tag_name
							: CGUtils::normalizeEpisodeTagName("s{$original_season}e{$original_episode}"))->getOne('tags');

						if (!empty($ep_tag)){
							if (!$this->creating){
								$ep_tag->name = $tag_name;
								$ep_tag->save();
							}
						}
						else if (!(new Tag([
							'name' => $tag_name,
							'type' => 'ep',
						]))->save()
						) Response::dbError('Episode tag creation failed');
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
					$log_entry = ['target' => $this->episode->getID()];
					$changes = 0;
					if (!empty($this->episode->airs))
						$this->episode->airs = date('c', strtotime($this->episode->airs));

					$map_int = function (string $s) { return \intval($s, 10); };
					$map_bool = function (string $s) { return (bool)$s; };
					$type_map = [
						'season' => $map_int,
						'episode' => $map_int,
						'twoparter' => $map_bool,
						'title' => null,
						'airs' => null,
					];
					foreach ($type_map as $k => $v){
						if (isset($update[$k])){
							$value = !empty($type_map[$k]) ? $type_map[$k]($v) : $v;
							if ($value !== $this->episode->{$k}){
								$log_entry["old$k"] = $this->episode->{$k};
								$log_entry["new$k"] = $value;
								$changes++;
							}
						}
					}
					if ($changes > 0)
						Logs::logAction('episode_modify', $log_entry);
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
				DB::$instance->where('name', "s{$this->episode->season}e{$this->episode->episode}")->where('uses', 0)->delete('tags');
				Response::success('Episode deleted successfully', [
					'upcoming' => CoreUtils::getSidebarUpcoming(NOWRAP),
				]);
			break;
			default:
				CoreUtils::notAllowed();
		}
	}

	public function voteApi($params):void {
		$this->load_episode($params);

		switch ($this->action){
			case 'GET':
				if (isset($_REQUEST['html']))
					Response::done(['html' => Episodes::getSidebarVoting($this->episode)]);

				$vote_count_query = DB::$instance->query(
					'SELECT count(*) as value, vote as label
					FROM episode_votes v
					WHERE season = ? AND episode = ?
					GROUP BY v.vote
					ORDER BY v.vote ASC', [$this->episode->season, $this->episode->episode]);
				$vote_counts = [];
				foreach ($vote_count_query as $row)
					$vote_counts[$row['label']] = $row['value'];

				Response::done(['data' => $vote_counts]);
			break;
			case 'POST':
				if (!Auth::$signed_in)
					Response::fail();

				if (!$this->episode->aired)
					Response::fail('You can only vote on this episode after it has aired.');

				$user_vote = $this->episode->getVoteOf(Auth::$user);
				if (!empty($user_vote))
					Response::fail('You already voted for this episode');

				$vote_value = (new Input('vote', 'int', [
					Input::IN_RANGE => [1, 5],
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Vote value missing from request',
						Input::ERROR_RANGE => 'Vote value must be an integer between @min and @max (inclusive)',
					],
				]))->out();

				$vote = new EpisodeVote();
				$vote->season = $this->episode->season;
				$vote->episode = $this->episode->episode;
				$vote->user_id = Auth::$user->id;
				$vote->vote = $vote_value;
				if (!$vote->save())
					Response::dbError();

				$this->episode->updateScore();
				Response::done(['newhtml' => Episodes::getSidebarVoting($this->episode)]);
			break;
			default:
				CoreUtils::notAllowed();
		}
	}

	public function videoEmbeds($params):void {
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		$this->load_episode($params);

		Response::done($this->episode->getVideoEmbeds());
	}

	public function videoDataApi($params):void {
		if (Permission::insufficient('staff'))
			Response::fail();

		$this->load_episode($params);

		switch ($this->action){
			case 'GET':
				$response = [
					'twoparter' => $this->episode->twoparter,
					'vidlinks' => [],
					'fullep' => [],
					'airs' => date('c', strtotime($this->episode->airs)),
				];
				$videos = $this->episode->videos;
				foreach ($videos as $part => $vid){
					if (!empty($vid->id))
						$response['vidlinks']["{$vid->provider}_{$vid->part}"] = VideoProvider::getEmbed($vid, VideoProvider::URL_ONLY);
					if ($vid->fullep)
						$response['fullep'][] = $vid->provider;
				}
				Response::done($response);
			break;
			case 'PUT':
				foreach (array_keys(Episodes::VIDEO_PROVIDER_NAMES) as $provider){
					for ($part = 1; $part <= ($this->episode->twoparter ? 2 : 1); $part++){
						$set = null;
						$post_key = "{$provider}_$part";
						if (!empty($_REQUEST[$post_key])){
							$provider_name = Episodes::VIDEO_PROVIDER_NAMES[$provider];
							try {
								$vid_provider = new VideoProvider(DeviantArt::trimOutgoingGateFromUrl($_REQUEST[$post_key]));
							}
							catch (\Exception $e){
								Response::fail("$provider_name link issue: ".$e->getMessage());
							}
							if ($vid_provider->episodeVideo === null || $vid_provider->episodeVideo->provider !== $provider)
								Response::fail("Incorrect $provider_name URL specified");
							/** @noinspection PhpUndefinedFieldInspection */
							$set = $vid_provider::$id;
						}

						$fullep = $this->episode->twoparter ? false : true;
						if ($part === 1 && $this->episode->twoparter && isset($_REQUEST["{$post_key}_full"])){
							$next_part = $provider.'_'.($part + 1);
							$_REQUEST[$next_part] = null;
							$fullep = true;
						}

						$video_count = DB::$instance
							->whereEp($this->episode)
							->where('provider', $provider)
							->where('part', $part)
							->count('episode_videos');
						if ($video_count === 0){
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

	public function guideRelationsApi($params):void {
		if (Permission::insufficient('staff'))
			Response::fail();

		$this->load_episode($params);

		switch ($this->action){
			case 'GET':
				$ep_tag_ids = $this->episode->getTagIDs();

				$tagged_appearance_ids = [];
				if (!empty($ep_tag_ids)){
					foreach ($ep_tag_ids as $tid){
						$tagged = Tagged::by_tag($tid);
						foreach ($tagged as $tg)
							$tagged_appearance_ids[$tg->appearance_id] = true;
					}
				}

				/** @var $appearances Appearance[] */
				$appearances = DB::$instance->disableAutoClass()
					->where('ishuman', $this->episode->is_movie)
					->where('id', 0, '!=')
					->orderBy('label')
					->get('appearances', null, 'id,label');

				$sorted = [
					'unlinked' => [],
					'linked' => [],
				];
				foreach ($appearances as $a)
					$sorted[isset($tagged_appearance_ids[$a['id']]) ? 'linked' : 'unlinked'][] = $a;

				Response::done($sorted);
			break;
			case 'PUT':
				/** @var $appearance_ids int[] */
				$appearance_ids = (new Input('ids', 'int[]', [
					Input::IS_OPTIONAL => true,
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Missing appearance ID list',
						Input::ERROR_INVALID => 'Appearance ID list is invalid',
					],
				]))->out();

				$ep_tag_ids = $this->episode->getTagIDs();
				if (empty($ep_tag_ids)){
					$tag = new Tag();
					$tag->name = CGUtils::normalizeEpisodeTagName($this->episode->getID());
					$tag->type = 'ep';
					$tag->save();
					$ep_tag_ids[] = $tag->id;
				}
				/** @var $tag Tag */
				else $tag = DB::$instance->where('id', $ep_tag_ids)->orderByLiteral('char_length(name)', 'DESC')->getOne('tags');

				if (!empty($appearance_ids)){
					foreach ($appearance_ids as $appearance_id){
						if (!Tagged::multi_is($ep_tag_ids, $appearance_id))
							$tag->add_to($appearance_id);
					}
					DB::$instance->where('appearance_id', $appearance_ids, '!=');
				}
				DB::$instance->where('tag_id', $ep_tag_ids)->delete('tagged');

				Response::done(['section' => Episodes::getAppearancesSectionHTML($this->episode)]);
			break;
			default:
				CoreUtils::notAllowed();
		}
	}

	public function brokenVideos($params):void {
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

		if ($removed === 0){
			Response::success('No broken videos found under this '.($this->episode->is_movie ? 'movie' : 'episode').'.');

			return;
		}

		Response::success("$removed video link".($removed === 1 ? ' has' : 's have').' been removed from the site. Thank you for letting us know.', [
			'epsection' => Episodes::getVideosHTML($this->episode, NOWRAP),
		]);
	}

	public function synopsis($params) {
		$this->load_episode($params);

		Response::done([
			'html' => Twig::$env->render('episode/_synopsis.html.twig', [
				'current_episode' => $this->episode,
				'wrap' => false,
				'lazyload' => false,
			]),
		]);
	}

	public function next():void {
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		$next_episode = DB::$instance->where('season != 0 AND airs > now()')->orderBy('airs')->getOne('episodes');
		if (empty($next_episode))
			Response::fail("The show is on hiatus, the next episode's title and air date is unknown.");

		Response::done($next_episode->to_array([
			'only' => ['episode', 'airs', 'season', 'title'],
		]));
	}

	public function prefill():void {
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		if (Permission::insufficient('staff'))
			Response::fail();

		/** @var $last_added Episode */
		$last_added = DB::$instance->orderBy('no', 'DESC')->where('season != 0')->getOne(Episode::$table_name);
		if (empty($last_added))
			Response::fail('No last added episode found');

		$season = $last_added->season;
		if ($last_added->twoparter && $last_added->episode + 1 === 26){
			$season++;
			$episode = 1;
			$airs = date('Y-m-d', strtotime('this saturday'));
		}
		else {
			$episode = min($last_added->episode + 1, 26);
			$airs = $last_added->airs->add(new \DateInterval('P1W'))->format('Y-m-d');
		}
		Response::done([
			'season' => $season,
			'episode' => $episode,
			'no' => $last_added->no + ($last_added->twoparter ? 2 : 1),
			'airday' => $airs,
		]);
	}
}
