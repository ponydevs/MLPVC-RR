<?php

namespace App;

use App\Models\Appearance;
use App\Models\Episode;
use App\Models\EpisodeVideo;
use App\Models\EpisodeVote;
use App\Models\Post;

class Episodes {
	public const TITLE_CUTOFF = 26;
	public const ALLOWED_PREFIXES = [
		'Equestria Girls' => 'EQG',
		'My Little Pony' => 'MLP',
	];

	/**
	 * Returns all episodes from the database, properly sorted
	 *
	 * @param int|int[]   $limit
	 * @param string|null $where
	 * @param bool        $allow_movies
	 * @param bool        $pre_ordered Indicates whether the user paid half their salary for an unfinished game /s
	 *                                 Jokes aside, this indicates that orderBy calls took place before this point, so that we don't order further.
	 *
	 * @return Episode|Episode[]
	 */
	public static function get($limit = null, $where = null, bool $allow_movies = false, bool $pre_ordered = false){
		/** @var $ep Episode */
		if (!empty($where))
			DB::$instance->where($where);
		if (!$pre_ordered)
			DB::$instance->orderBy('season','DESC')->orderBy('episode','DESC');
		if (!$allow_movies)
			DB::$instance->where('season != 0');
		if ($limit !== 1)
			return DB::$instance->get('episodes',$limit);
		return DB::$instance->getOne('episodes');
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
	 *
	 * @return Episode|null
	 */
	public static function getActual(int $season, int $episode, bool $allowMovies = false, $cache = false){
		$cacheKey = "$season-$episode";
		if (!$allowMovies && $season === 0)
			throw new \InvalidArgumentException('This action cannot be performed on movies');

		if ($cache && isset(self::$episodeCache[$cacheKey]))
			return self::$episodeCache[$cacheKey];

		$Ep = Episode::find_by_season_and_episode($season, $episode);
		if (!empty($Ep))
			return $Ep;

		$Part1 = Episode::find_by_season_and_episode($season, $episode-1);
		$output = !empty($Part1) && $Part1->twoparter === true
			? $Part1
			: null;
		if ($cache)
			self::$episodeCache[$cacheKey] = $output;
		return $output;
	}

	/**
	 * Returns the latest episode by air time
	 *
	 * @return Episode
	 */
	public static function getLatest(){
		DB::$instance->orderBy('airs', 'DESC');
		return self::get(1,"airs < NOW() + INTERVAL '24 HOUR'", false, true);
	}

	public static function removeTitlePrefix($title){
		global $PREFIX_REGEX;

		return $PREFIX_REGEX->replace('', $title);
	}

	public static function shortenTitlePrefix($title){
		global $PREFIX_REGEX;

		if (!$PREFIX_REGEX->match($title, $match) || !isset(self::ALLOWED_PREFIXES[$match[1]]))
			return $title;

		return self::ALLOWED_PREFIXES[$match[1]].': '.self::removeTitlePrefix($title);
	}

	/**
	 * Loads the episode page
	 *
	 * @param null|string|Episode $force       If null: Parses $data and loads appropriate episode
	 *                                        If string: Loads episode by specified ID
	 *                                        If Episode: Uses the object as Episode data
	 * @param Post                $linked_post Linked post (when sharing)
	 */
	public static function loadPage($force = null, Post $linked_post = null){
		if ($force instanceof Episode)
			$current_episode = $force;
		if (empty($current_episode))
			CoreUtils::notFound();

		if (!$linked_post)
			CoreUtils::fixPath($current_episode->toURL());

		$js = ['jquery.fluidbox', true, 'pages/episode/manage'];
		if (Permission::sufficient('staff')){
			$js[] = 'moment-timezone';
			$js[] = 'pages/show/index-manage';
		}

		$prev_episode = $current_episode->getPrevious();
		$next_episode = $current_episode->getNext();

		$ogImage = null;
		$ogDescription = null;
		if ($linked_post){
			if ($linked_post->is_request)
				$ogDescription = 'A request';
			else $ogDescription = "A reservation by {$linked_post->reserver->name}";
			$ogDescription .= " on the MLP Vector Club's website";

			if (!$linked_post->finished)
				$ogImage = $linked_post->preview;
			else {
				$finishdeviation = DeviantArt::getCachedDeviation($linked_post->deviation_id);
				if (!empty($finishdeviation->preview))
					$ogImage  = $finishdeviation->preview;
			}
		}

		$ep_title_regex = null;
		if (Permission::sufficient('staff')){
			global $EP_TITLE_REGEX;
			$ep_title_regex = $EP_TITLE_REGEX;
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
			global $USERNAME_REGEX;
			$import['username_regex'] = $USERNAME_REGEX;
		}
		if (Auth::$signed_in){
			global $FULLSIZE_MATCH_REGEX;
			$import['fullsize_match_regex'] = $FULLSIZE_MATCH_REGEX;
		}

		$heading = $current_episode->formatTitle();
		CoreUtils::loadPage('EpisodeController::view', [
			'title' => "$heading - Vector Requests & Reservations",
			'heading' => $heading,
			'css' => [true],
			'js' => $js,
			'canonical' => $linked_post ? $linked_post->toURL() : null,
			'og' => [
				'url' => $linked_post ? $linked_post->toURL() : null,
				'image' => $ogImage,
				'description' => $ogDescription,
				'title' => $linked_post ? $linked_post->label : null,
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
	 * @param Episode $Episode
	 * @param bool    $wrap
	 *
	 * @return string
	 */
	public static function getVideosHTML(Episode $Episode, bool $wrap = WRAP):string {
		return Twig::$env->render('episode/_watch.html.twig', ['current_episode' => $Episode, 'wrap' => $wrap ]);
	}

	/**
	 * Render episode voting HTML
	 *
	 * @param Episode $Episode
	 *
	 * @return string
	 */
	public static function getSidebarVoting(Episode $Episode):string {
		return Twig::$env->render('episode/_sidebar_voting.html.twig', ['current_episode' => $Episode]);
	}

	public static function getAppearancesSectionHTML(Episode $Episode):string {
		return Twig::$env->render('episode/_related_appearances.html.twig', ['current_episode' => $Episode ]);
	}

	public static function validateSeason($allowMovies = false){
		return (new Input('season','int', [
			Input::IN_RANGE => [$allowMovies ? 0 : 1, 9],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Season number is missing',
				Input::ERROR_INVALID => 'Season number (@value) is invalid',
				Input::ERROR_RANGE => 'Season number must be between @min and @max',
			]
		]))->out();
	}
	public static function validateEpisode($optional = false, $EQG = false){
		$FieldName = $EQG ? 'Overall movie number' : 'Episode number';
		return (new Input('episode','int', [
			Input::IS_OPTIONAL => $optional,
			Input::IN_RANGE => [1,26],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => "$FieldName is missing",
				Input::ERROR_INVALID => "$FieldName (@value) is invalid",
				Input::ERROR_RANGE => "$FieldName must be between @min and @max",
			]
		]))->out();
	}
}
