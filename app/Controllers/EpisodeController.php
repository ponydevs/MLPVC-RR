<?php

namespace App\Controllers;

use ActiveRecord\Table;
use App\Auth;
use App\CGUtils;
use App\CoreUtils;
use App\CSRFProtection;
use App\DB;
use App\DeviantArt;
use App\ShowHelper;
use App\HTTP;
use App\Input;
use App\Logs;
use App\Models\Appearance;
use App\Models\ShowVote;
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
use App\Models\Show;
use App\Models\ShowVideo;

class EpisodeController extends Controller {
	public function latest():void {
		$latest_episode = ShowHelper::getLatest();
		if (empty($latest_episode))
			CoreUtils::loadPage(__CLASS__.'::view', [
				'title' => 'Home',
			]);

		HTTP::tempRedirect($latest_episode->toURL());
	}

	public function view($params):void {
		if (empty($params['id']))
			CoreUtils::notFound();

		$ep_data = Show::parseID($params['id']);
		if ($ep_data['season'] === 0)
			HTTP::tempRedirect('/movie/'.$ep_data['episode']);

		$current_episode = empty($ep_data)
			? ShowHelper::getLatest()
			: ShowHelper::getActual($ep_data['season'], $ep_data['episode']);

		ShowHelper::loadPage($current_episode);
	}

	/** @var Show */
	private $show;

	private function load_episode($params):void {
		$this->show = Show::find($params['id']);
		if (empty($this->show))
			CoreUtils::notFound();
	}

	public function postList($params):void {
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		$this->load_episode($params);

		$section = $_GET['section'];
		$only = $section === 'requests' ? ONLY_REQUESTS : ONLY_RESERVATIONS;

		switch ($only){
			case ONLY_REQUESTS:
				$requests = $this->show->getRequests();
				$rendered = Posts::getRequestsSection($requests);
			break;
			case ONLY_RESERVATIONS:
				$reservations = $this->show->getReservations();
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
					'ep' => $this->show->to_array(),
				]);
			break;
			case 'POST':
			case 'PUT':
				$update = [];
				if ($this->creating){
					$update['type'] = ShowHelper::validateType();
					$update['posted_by'] = Auth::$user->id;
					$is_movie = $update['type'] === 'movie';
					$is_episode = $update['type'] === 'episode';
				}
				else {
					$is_movie = $this->show->is_movie;
					$is_episode = !$this->show->is_movie;
				}
				$what = $is_episode ? 'Episode' : 'Movie';

				if ($is_episode){
					$update['season'] = ShowHelper::validateSeason(ShowHelper::ALLOW_MOVIES);
					$update['episode'] = ShowHelper::validateEpisode(!$is_episode);

					$episode_changed = true;
					$season_changed = true;
					$original_episode = $this->creating ? $update['episode'] : $this->show->episode;
					$original_season = $this->creating ? $update['season'] : $this->show->season;
					if ($this->creating){
						$matching_id = Show::find_by_season_and_episode($update['season'], $update['episode']);
						if (!empty($matching_id)){
							Response::fail('An episode with the same season and episode number already exists');
						}
					}
					else {
						$season_changed = $update['season'] !== $this->show->season;
						$episode_changed = $update['episode'] !== $this->show->episode;
						if ($season_changed || $episode_changed){
							$target = ShowHelper::getActual(
								$update['season'] ?? $this->show->season,
								$update['episode'] ?? $this->show->episode,
								ShowHelper::ALLOW_MOVIES
							);
							if (!empty($target))
								Response::fail("There's already an episode with the same season & episode number");
						}
					}

					$update['twoparter'] = isset($_REQUEST['twoparter']);
					if ($update['twoparter']){
						$next_part = Show::find_by_season_and_episode($update['season'], $update['episode'] + 1);
						if (!empty($next_part))
							Response::fail("This episode cannot have two parts because {$next_part->toURL()} already exists.");
					}
				}

				$update['no'] = (new Input('no', 'int', [
					Input::IS_OPTIONAL => true,
					Input::IN_RANGE => [1, null],
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_INVALID => 'Overall number (@value) is invalid',
						Input::ERROR_RANGE => 'Overall number cannot be less than @min',
					],
				]))->out();

				$update['title'] = (new Input('title', function (&$value, $range) use ($is_episode) {
					$prefixed = Regexes::$ep_title_prefix->match($value, $match);
					if ($prefixed){
						if ($is_episode){
							return 'prefix-movie-only';
						}
						if (!isset(ShowHelper::ALLOWED_PREFIXES[$match[1]])){
							$most_similar = null;
							$most_matching = 0;
							foreach (ShowHelper::ALLOWED_PREFIXES as $prefix => $shorthand){
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

						$title = ShowHelper::removeTitlePrefix($value);
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
					if ($this->creating || $notes !== $this->show->notes)
						$update['notes'] = $notes;
				}
				else $update['notes'] = null;

				if ($this->creating){
					$this->show = new Show($update);
					if (!$this->show->save())
						Response::dbError('Show entry creation failed');

					$this->show->createCGTag();

					Response::done(['url' => (new Show($update))->toURL()]);
				}

				// Updating
				if (!DB::$instance->where('show_id', $this->show->id)->update(Show::$table_name, $update))
					Response::dbError('Updating show entry failed');

				Response::done();
			break;
			case 'DELETE':
				if (!DB::$instance->where('id', $this->show->id)->delete(Show::$table_name))
					Response::dbError();

				$show_tag = $this->show->getCGTag();
				if ($show_tag->uses === 0)
					$show_tag->delete();

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
					Response::done(['html' => ShowHelper::getSidebarVoting($this->show)]);

				$table = ShowVote::$table_name;
				$vote_count_query = DB::$instance->query(
					"SELECT count(*) as value, vote as label FROM $table WHERE show_id = ? GROUP BY vote ORDER BY vote ASC", [$this->show->id]);
				$vote_counts = [];
				foreach ($vote_count_query as $row)
					$vote_counts[$row['label']] = $row['value'];

				Response::done(['data' => $vote_counts]);
			break;
			case 'POST':
				if (!Auth::$signed_in)
					Response::fail();

				if (!$this->show->aired)
					Response::fail('You can only vote on this episode after it has aired.');

				$user_vote = $this->show->getVoteOf(Auth::$user);
				if (!empty($user_vote))
					Response::fail('You already voted for this '.($this->show->is_movie ? 'movie' : 'episode'));

				$vote_value = (new Input('vote', 'int', [
					Input::IN_RANGE => [1, 5],
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Vote value missing from request',
						Input::ERROR_RANGE => 'Vote value must be an integer between @min and @max (inclusive)',
					],
				]))->out();

				$vote = new ShowVote();
				$vote->show_id = $this->show->id;
				$vote->user_id = Auth::$user->id;
				$vote->vote = $vote_value;
				if (!$vote->save())
					Response::dbError();

				$this->show->updateScore();
				Response::done(['newhtml' => ShowHelper::getSidebarVoting($this->show)]);
			break;
			default:
				CoreUtils::notAllowed();
		}
	}

	public function videoEmbeds($params):void {
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		$this->load_episode($params);

		Response::done($this->show->getVideoEmbeds());
	}

	public function videoDataApi($params):void {
		if (Permission::insufficient('staff'))
			Response::fail();

		$this->load_episode($params);

		switch ($this->action){
			case 'GET':
				$response = [
					'twoparter' => $this->show->twoparter,
					'vidlinks' => [],
					'fullep' => [],
					'airs' => date('c', strtotime($this->show->airs)),
				];
				$videos = $this->show->videos;
				foreach ($videos as $part => $vid){
					if (!empty($vid->id))
						$response['vidlinks']["{$vid->provider}_{$vid->part}"] = VideoProvider::getEmbed($vid, VideoProvider::URL_ONLY);
					if ($vid->fullep)
						$response['fullep'][] = $vid->provider;
				}
				Response::done($response);
			break;
			case 'PUT':
				foreach (array_keys(ShowHelper::VIDEO_PROVIDER_NAMES) as $provider){
					for ($part = 1; $part <= ($this->show->twoparter ? 2 : 1); $part++){
						$set = null;
						$post_key = "{$provider}_$part";
						if (!empty($_REQUEST[$post_key])){
							$provider_name = ShowHelper::VIDEO_PROVIDER_NAMES[$provider];
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

						$fullep = $this->show->twoparter ? false : true;
						if ($part === 1 && $this->show->twoparter && isset($_REQUEST["{$post_key}_full"])){
							$next_part = $provider.'_'.($part + 1);
							$_REQUEST[$next_part] = null;
							$fullep = true;
						}

						$video_count = DB::$instance
							->where('show_id', $this->show->id)
							->where('provider', $provider)
							->where('part', $part)
							->count('episode_videos');
						if ($video_count === 0){
							if (!empty($set))
								ShowVideo::create([
									'show_id' => $this->show->id,
									'provider' => $provider,
									'part' => $part,
									'id' => $set,
									'fullep' => $fullep,
								]);
						}
						else {
							DB::$instance
								->where('show_id', $this->show->id)
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

				Response::success('Links updated', ['epsection' => ShowHelper::getVideosHTML($this->show)]);
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
				$ep_tag_ids = $this->show->getTagIDs();

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
					->where('id', 0, '!=')
					->orderBy('label')
					->get('appearances', null, 'id,label,ishuman');

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

				$ep_tag_ids = $this->show->getTagIDs();
				if (empty($ep_tag_ids)){
					$tag = new Tag();
					$tag->name = CGUtils::normalizeEpisodeTagName($this->show->getID());
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

				Response::done(['section' => ShowHelper::getAppearancesSectionHTML($this->show)]);
			break;
			default:
				CoreUtils::notAllowed();
		}
	}

	public function brokenVideos($params):void {
		$this->load_episode($params);

		$removed = 0;
		foreach ($this->show->videos as $k => $video){
			if (!$video->isBroken())
				continue;

			$removed++;
			$video->delete();
			Logs::logAction('video_broken', [
				'show_id' => $this->show->id,
				'provider' => $video->provider,
				'id' => $video->id,
			]);
			unset($this->show->videos[$k]);
		}

		if ($removed === 0){
			Response::success('No broken videos found under this '.($this->show->is_movie ? 'movie' : 'episode').'.');

			return;
		}

		Response::success("$removed video link".($removed === 1 ? ' has' : 's have').' been removed from the site. Thank you for letting us know.', [
			'epsection' => ShowHelper::getVideosHTML($this->show, NOWRAP),
		]);
	}

	public function synopsis($params) {
		$this->load_episode($params);

		Response::done([
			'html' => Twig::$env->render('episode/_synopsis.html.twig', [
				'current_episode' => $this->show,
				'wrap' => false,
				'lazyload' => false,
			]),
		]);
	}

	public function next():void {
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		$next_episode = DB::$instance->where('season != 0 AND airs > now()')->orderBy('airs')->getOne(Show::$table_name);
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

		/** @var $last_added Show */
		$last_added = DB::$instance->orderBy('no', 'DESC')->where('season != 0')->getOne(Show::$table_name);
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
