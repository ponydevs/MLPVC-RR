<?php

namespace App;

use App\Models\Appearance;
use App\Models\Show;
use App\Models\ShowVideo;
use App\Models\ShowVote;
use App\Models\Post;

class ShowHelper {
	public const TITLE_CUTOFF = 26;
	public const ALLOWED_PREFIXES = [
		'Equestria Girls' => 'EQG',
		'My Little Pony' => 'MLP',
	];
	public const VALID_TYPES = [
		#----------# - max length
		'movie'      => true,
		'short'      => true,
		'episode'    => true,
		'special'    => true,
	];

	/**
	 * Returns all episodes from the database, properly sorted
	 *
	 * @param int|int[]   $limit
	 * @param string|null $where
	 * @param bool        $pre_ordered Indicates whether the user paid half their salary for an unfinished game /s
	 *                                 Jokes aside, this indicates that orderBy calls took place before this point, so that we don't order further.
	 *
	 * @return Show|Show[]
	 */
	public static function get($limit = null, $where = null, bool $pre_ordered = false) {
		/** @var $ep Show */
		if (!empty($where))
			DB::$instance->where($where);
		if (!$pre_ordered)
			DB::$instance->orderBy('season', 'DESC')->orderBy('episode', 'DESC');
		if ($limit !== 1)
			return DB::$instance->get(Show::$table_name, $limit);

		return DB::$instance->getOne(Show::$table_name);
	}

	public const ALLOW_MOVIES = true;

	private static $episodeCache = [];

	/**
	 * If an episode is a two-parter's second part, then returns the first part
	 * Otherwise returns the episode itself
	 *
	 * @param int  $episode
	 * @param int  $season
	 * @param bool $allowMovies
	 * @param bool $cache
	 *
	 * @throws \InvalidArgumentException
	 * @return Show|null
	 */
	public static function getActual(int $season, int $episode, bool $allowMovies = false, $cache = false) {
		$cache_key = "$season-$episode";
		if (!$allowMovies && $season === 0)
			throw new \InvalidArgumentException('This action cannot be performed on movies');

		if ($cache && isset(self::$episodeCache[$cache_key]))
			return self::$episodeCache[$cache_key];

		$ep = Show::find_by_season_and_episode($season, $episode);
		if (!empty($ep))
			return $ep;

		$part_1 = Show::find_by_season_and_episode($season, $episode - 1);
		$output = !empty($part_1) && $part_1->twoparter === true
			? $part_1
			: null;
		if ($cache)
			self::$episodeCache[$cache_key] = $output;

		return $output;
	}

	/**
	 * Returns the latest episode by air time
	 *
	 * @return Show
	 */
	public static function getLatest() {
		DB::$instance->orderBy('airs', 'DESC');

		return self::get(1, "season is not null AND airs < NOW() + INTERVAL '24 HOUR'", true);
	}

	public static function removeTitlePrefix($title) {
		return Regexes::$ep_title_prefix->replace('', $title);
	}

	public static function shortenTitlePrefix($title) {
		if (!Regexes::$ep_title_prefix->match($title, $match) || !isset(self::ALLOWED_PREFIXES[$match[1]]))
			return $title;

		return self::ALLOWED_PREFIXES[$match[1]].': '.self::removeTitlePrefix($title);
	}

	/**
	 * Loads the episode page
	 *
	 * @param null|string|Show $force       If null: Parses $data and loads appropriate episode
	 *                                      If string: Loads episode by specified ID
	 *                                      If SHow: Uses the object as Show data
	 * @param Post             $linked_post Linked post (when sharing)
	 */
	public static function loadPage($force = null, Post $linked_post = null) {
		if ($force instanceof Show)
			$current_episode = $force;
		if (empty($current_episode))
			CoreUtils::notFound();

		if (!$linked_post)
			CoreUtils::fixPath($current_episode->toURL());

		$js = ['jquery.fluidbox', true, 'pages/show/manage'];
		if (Permission::sufficient('staff')){
			$js[] = 'moment-timezone';
			$js[] = 'pages/show/index-manage';
		}

		$prev_episode = $current_episode->getPrevious();
		$next_episode = $current_episode->getNext();

		$og_image = null;
		$og_description = null;
		if ($linked_post){
			if ($linked_post->is_request)
				$og_description = 'A request';
			else $og_description = "A reservation by {$linked_post->reserver->name}";
			$og_description .= " on the MLP Vector Club's website";

			if (!$linked_post->finished)
				$og_image = $linked_post->preview;
			else {
				$finishdeviation = DeviantArt::getCachedDeviation($linked_post->deviation_id);
				if (!empty($finishdeviation->preview))
					$og_image = $finishdeviation->preview;
			}
		}

		$ep_title_regex = null;
		if (Permission::sufficient('staff')){
			$ep_title_regex = Regexes::$ep_title;
		}
		$import = [
			'ep_title_regex' => $ep_title_regex,
			'current_episode' => $current_episode,
			'poster' => $current_episode->poster,
			'videos' => $current_episode->videos,
			'prev_episode' => $prev_episode,
			'next_episode' => $next_episode,
			'linked_post' => $linked_post,
		];
		if (Permission::sufficient('developer')){
			$import['username_regex'] = Regexes::$username;
		}
		if (Auth::$signed_in){
			$import['fullsize_match_regex'] = Regexes::$fullsize_match;
		}

		$linked_post_prefix = $linked_post ? ucfirst($linked_post->kind).": {$linked_post->label} - " : '';
		$heading = $current_episode->formatTitle();
		$title = "$linked_post_prefix$heading - Vector Requests & Reservations";
		CoreUtils::loadPage('ShowController::view', [
			'title' => $title,
			'heading' => $heading,
			'css' => [true],
			'js' => $js,
			'canonical' => $linked_post ? $linked_post->toURL() : null,
			'og' => [
				'url' => $linked_post ? $linked_post->toURL() : null,
				'image' => $og_image,
				'description' => $og_description,
			],
			'import' => $import,
		]);
	}

	public const
		VIDEO_PROVIDER_NAMES = [
		'yt' => 'YouTube',
		'dm' => 'Dailymotion',
		'sv' => 'sendvid',
		'mg' => 'Mega',
	],
		PROVIDER_BTN_CLASSES = [
		'yt' => 'red typcn-social-youtube',
		'dm' => 'darkblue typcn-video',
		'sv' => 'yellow typcn-video',
		'mg' => 'red typcn-video',
	];

	/**
	 * Renders the HTML of the "Watch the Episode" section along with the buttons/links
	 *
	 * @param Show $Episode
	 * @param bool $wrap
	 *
	 * @return string
	 */
	public static function getVideosHTML(Show $Episode, bool $wrap = WRAP):string {
		return Twig::$env->render('show/_watch.html.twig', [
			'current_episode' => $Episode,
			'wrap' => $wrap,
			'videos' => $Episode->videos,
		]);
	}

	/**
	 * Render episode voting HTML
	 *
	 * @param Show $Episode
	 *
	 * @return string
	 */
	public static function getSidebarVoting(Show $Episode):string {
		return Twig::$env->render('show/_sidebar_voting.html.twig', [
			'current_episode' => $Episode,
			'signed_in' => Auth::$signed_in,
		]);
	}

	public static function getAppearancesSectionHTML(Show $show):string {
		return Twig::$env->render('show/_related_appearances.html.twig', ['current_episode' => $show]);
	}

	public static function validateSeason($allowMovies = false) {
		return (new Input('season', 'int', [
			Input::IN_RANGE => [$allowMovies ? 0 : 1, 9],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Season number is missing',
				Input::ERROR_INVALID => 'Season number (@value) is invalid',
				Input::ERROR_RANGE => 'Season number must be between @min and @max',
			],
		]))->out();
	}

	public static function validateEpisode($optional = false, $EQG = false) {
		$field_name = $EQG ? 'Overall movie number' : 'Episode number';

		return (new Input('episode', 'int', [
			Input::IS_OPTIONAL => $optional,
			Input::IN_RANGE => [1, 26],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => "$field_name is missing",
				Input::ERROR_INVALID => "$field_name (@value) is invalid",
				Input::ERROR_RANGE => "$field_name must be between @min and @max",
			],
		]))->out();
	}

	public static function validateType() {
		return (new Input('type', function($value){
			if (!isset(self::VALID_TYPES[$value]))
				return Input::ERROR_INVALID;
		}, [
			Input::IS_OPTIONAL => false,
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Show type is missing',
				Input::ERROR_INVALID => 'Show type (@value) is invalid',
			],
		]))->out();
	}
}
