<?php

namespace App;

use App\Models\Appearance;
use App\Models\Episode;
use App\Models\EpisodeVideo;
use App\Models\EpisodeVote;
use App\Models\Post;

class Episodes {
	const TITLE_CUTOFF = 26;
	public static $ALLOWED_PREFIXES = [
		'Equestria Girls' => 'EQG',
		'My Little Pony' => 'MLP',
	];

	/**
	 * Returns all episodes from the database, properly sorted
	 *
	 * @param int|int[]   $count
	 * @param string|null $where
	 *
	 * @return Episode|Episode[]
	 */
	public static function get($count = null, $where = null){
		/** @var $ep Episode */
		if (!empty($where))
			DB::where($where);
		DB::orderBy('season')->orderBy('episode')->where('season != 0');
		if ($count !== 1)
			return DB::get('episodes',$count);
		return DB::getOne('episodes');
	}

	const ALLOW_MOVIES = true;

	private static $EP_CACHE = [];

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
		if (!$allowMovies && $season === 0)
			throw new \InvalidArgumentException('This action cannot be performed on movies');

		if ($cache && isset(self::$EP_CACHE["$season-$episode"]))
			return self::$EP_CACHE["$season-$episode"];

		$Ep = Episode::find_by_season_and_episode($season, $episode);
		if (!empty($Ep))
			return $Ep;

		$Part1 = Episode::find_by_season_and_episode($season, $episode-1);
		$output = !empty($Part1) && !empty($Part1->twoparter)
			? $Part1
			: null;
		if ($cache)
			self::$EP_CACHE["$season-$episode"] = $output;
		return $output;
	}

	/**
	 * Returns the latest episode
	 *
	 * @return Episode
	 */
	public static function getLatest(){
		return self::get(1,"airs < NOW() + INTERVAL '24 HOUR'");
	}

	public static function removeTitlePrefix($title){
		global $PREFIX_REGEX;

		return $PREFIX_REGEX->replace('', $title);
	}

	public static function shortenTitlePrefix($title){
		global $PREFIX_REGEX;

		if (!$PREFIX_REGEX->match($title, $match) || !isset(self::$ALLOWED_PREFIXES[$match[1]]))
			return $title;

		return self::$ALLOWED_PREFIXES[$match[1]].': '.self::removeTitlePrefix($title);
	}

	/**
	 * Loads the episode page
	 *
	 * @param null|string|Episode $force              If null: Parses $data and loads approperiate epaisode
	 *                                             If array: Uses specified arra as Episode data
	 * @param bool                $serverSideRedirect Handle redirection to the correct page on the server/client side
	 * @param Post                $LinkedPost         Linked post (when sharing)
	 */
	public static function loadPage($force = null, $serverSideRedirect = true, Post $LinkedPost = null){
		global $CurrentEpisode;

		if ($force instanceof Episode)
			$CurrentEpisode = $force;
		else if (is_string($force)){
			$EpData = Episode::parseID($force);

			if ($EpData['season'] === 0){
				error_log("Attempted visit to $force from ".(!empty($_SERVER['HTTP_REFERER'])?$_SERVER['HTTP_REFERER']:'[unknown referrer]').', redirecting to /movie page');
				HTTP::redirect('/movie/'.$EpData['episode']);
			}

			$CurrentEpisode = empty($EpData)
				? self::getLatest()
				: self::getActual($EpData['season'], $EpData['episode']);
		}
		if (empty($CurrentEpisode))
			CoreUtils::notFound();

		$url = $CurrentEpisode->toURL();
		if (isset($LinkedPost)){
			$url .= '#'.$LinkedPost->getID();
		}
		if ($serverSideRedirect)
			CoreUtils::fixPath($url);

		$js = ['imagesloaded.pkgd', 'jquery.fluidbox', 'Chart', 'episode', 'episode-manage'];
		if (Permission::sufficient('staff')){
			$js[] = 'moment-timezone';
			$js[] = 'episodes-manage';
		}

		$PrevEpisode = $CurrentEpisode->getPrevious();
		$NextEpisode = $CurrentEpisode->getNext();

		$heading = $CurrentEpisode->formatTitle();
		CoreUtils::loadPage([
			'title' => "$heading - Vector Requests & Reservations",
			'heading' => $heading,
			'view' => 'episode',
			'css' => 'episode',
			'js' => $js,
			'url' => $serverSideRedirect ? null : $url,
			'import' => [
				'CurrentEpisode' => $CurrentEpisode,
				'PrevEpisode' => $PrevEpisode,
				'NextEpisode' => $NextEpisode,
				'LinkedPost' => $LinkedPost,
			],
		]);
	}

	/**
	 * Get user's vote for an episode
	 *
	 * Accepts a single array containing values
	 *  for the keys 'season' and 'episode'
	 * Return's the user's vote entry from the DB
	 *
	 * @param Episode $Ep
	 * @return EpisodeVote|null
	 */
	public static function getUserVote($Ep){
		if (!Auth::$signed_in) return null;
		/** @noinspection PhpIncompatibleReturnTypeInspection */
		return EpisodeVote::find_for($Ep, Auth::$user);
	}

	/**
	 * Get video embed HTML for an episode
	 *
	 * @param Episode $Episode
	 *
	 * @return array
	 */
	public static function getVideoEmbeds($Episode):array {
		$EpVideos = $Episode->videos;
		$Parts = 0;
		$embed = '';
		if (!empty($EpVideos)){
			$Videos = [];
			foreach ($EpVideos as $v)
				$Videos[$v->provider][$v->part] = $v;
			// YouTube embed preferred
			$Videos = !empty($Videos['yt']) ? $Videos['yt'] : $Videos['dm'];
			/** @var $Videos EpisodeVideo[] */

			$Parts = count($Videos);
			foreach ($Videos as $v)
				$embed .= "<div class='responsive-embed".($Episode->twoparter && $v->part!==1?' hidden':'')."'>".VideoProvider::getEmbed($v).'</div>';
		}
		return [
			'parts' => $Parts,
			'html' => $embed
		];
	}

	const
		VIDEO_PROVIDER_NAMES = [
			'yt' => 'YouTube',
			'dm' => 'Dailymotion',
		],
		PROVIDER_BTN_CLASSES = [
			'yt' => 'red typcn-social-youtube',
			'dm' => 'darkblue typcn-video',
		];

	/**
	 * Renders the HTML of the "Watch the Episode" section along with the buttons/links
	 *
	 * @param Episode $Episode
	 * @param bool    $wrap
	 *
	 * @return string
	 */
	public static function getVideosHTML($Episode, bool $wrap = WRAP):string {
		$HTML = '';
		/** @var $Videos EpisodeVideo[] */
		$Videos = $Episode->videos;

		if (!empty($Videos)){
			$fullep = $Episode->twoparter ? 'Full episode' : '';

			$HTML = ($wrap ? "<section class='episode'>" : '').'<h2>Watch the '.($Episode->is_movie?'Movie':'Episode')."</h2><p class='align-center actions'>";
			foreach ($Videos as $v){
				$url = VideoProvider::getEmbed($v, VideoProvider::URL_ONLY);
				$partText = $Episode->twoparter ? (
					!$v->fullep
					? " (Part {$v->part})"
					: " ($fullep)"
				) : $fullep;
				$HTML .= "<a class='btn typcn ".self::PROVIDER_BTN_CLASSES[$v->provider]."' href='$url' target='_blank' rel='noopener'>".self::VIDEO_PROVIDER_NAMES[$v->provider]."$partText</a>";
			}
			$HTML .= "<button class='green typcn typcn-eye showplayers'>Show on-site player</button><button class='orange typcn typcn-flag reportbroken'>Report broken video</button></p>";
			if ($wrap)
				$HTML .= '</section>';
		}

		return $HTML;
	}

	/**
	 * Get the <tbody> contents for the episode list table
	 *
	 * @param Episode[]|null $Episodes
	 * @param bool           $areMovies
	 *
	 * @return string
	 */
	public static function getTableTbody($Episodes = null, bool $areMovies = false):string {
		if (empty($Episodes))
			return "<tr class='empty align-center'><td colspan='3'><em>There are no ".($areMovies?'movies':'episodes').' to display</em></td></tr>';

		$Body = '';
		$PathStart = '/episode/';
		$displayed = false;
		foreach ($Episodes as $Episode) {
			$adminControls = Permission::insufficient('staff') ? '' : <<<HTML
<span class='admincontrols'>
<button class='edit-episode typcn typcn-pencil blue' title='Edit episode'></button>
<button class='delete-episode typcn typcn-times red' title='Delete episode'></button>
</span>
HTML;
			$SeasonEpisode = $DataID = '';
			$title = $Episode->formatTitle(AS_ARRAY);
			if (!$Episode->is_movie){
				$href = $PathStart.$title['id'];
				if ($Episode->twoparter)
					$title['episode'] .= '-'.(intval($title['episode'],10)+1);
				$SeasonEpisode = <<<HTML
			<td class='season' rowspan='2'>{$title['season']}</td>
			<td class='episode' rowspan='2'>{$title['episode']}</td>
HTML;

			}
			else {
				$href = $Episode->toURL();
				$SeasonEpisode = "<td class='episode' rowspan='2'>{$title['episode']}</td>";
			}
			$DataID = " data-epid='{$title['id']}'";

			$star = '';
			if ($Episode->isLatest()){
				$displayed = true;
				$star = '<span class="typcn typcn-home" title="Curently visible on the homepage"></span> ';
			}
			if (!$Episode->aired)
				$star .= '<span class="typcn typcn-media-play-outline" title="'.($Episode->is_movie?'Movie':'Episode').' didn’t air yet, voting disabled"></span>&nbsp;';

			$airs = Time::tag($Episode->airs, Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME);

			$Body .= <<<HTML
	<tr$DataID>
		$SeasonEpisode
		<td class='title'>$star<a href="$href">{$title['title']}</a>$adminControls</td>
	</tr>
	<tr><td class='airs'>$airs</td></tr>
HTML;
		}
		return $Body;
	}

	/**
	 * Render episode voting HTML
	 *
	 * @param Episode $Episode
	 *
	 * @return string
	 */
	public static function getSidebarVoting(Episode $Episode):string {
		$thing = $Episode->is_movie ? 'movie' : 'episode';
		if (!$Episode->aired)
			return '<p>Voting will start '.Time::tag($Episode->willair).", after the $thing aired.</p>";

		$HTML = '';

		if (empty($Episode->score))
			$Episode->updateScore();

		$Score = preg_replace(new RegExp('^(\d+)\.0+$'),'$1',number_format($Episode->score,1));
		$ScorePercent = round(($Score/5)*1000)/10;

		$HTML .= '<p>'.(!empty($Score) ? "This $thing is rated $Score/5 (<a class='detail'>Details</a>)" : 'Nopony voted yet.').'</p>';
		if ($Score > 0)
			$HTML .= "<img src='/muffin-rating?w=$ScorePercent' id='muffins' alt='muffin rating svg'>";

		$UserVote = $Episode->getUserVote();
		if (empty($UserVote)){
			$HTML .= "<br><p>What did <em>you</em> think about the $thing?</p>";
			if (Auth::$signed_in)
				$HTML .= "<button class='blue rate typcn typcn-star'>Cast your vote</button>";
			else $HTML .= '<p><em>Sign in above to cast your vote!</em></p>';
		}
		else $HTML .= '<p>Your rating: '.CoreUtils::makePlural('muffin', $UserVote->vote, PREPEND_NUMBER).'</p>';

		return $HTML;
	}

	public static function getAppearancesSectionHTML(Episode $Episode):string {
		$HTML = '';
		$EpTagIDs = $Episode->getTagIDs();
		if (!empty($EpTagIDs)){
			/** @var $TaggedAppearances Appearance[] */
			$TaggedAppearances = DB::setModel('Appearance')->rawQuery(
				'SELECT p.*
				FROM tagged t
				LEFT JOIN appearances p ON t.appearance_id = p.id
				WHERE t.tag_id IN ('.implode(',',$EpTagIDs).') AND p.ishuman = ?
				ORDER BY p.label', [$Episode->is_movie]);

			if (!empty($TaggedAppearances)){
				$hidePreviews = UserPrefs::get('ep_noappprev');
				$pages = CoreUtils::makePlural('page', count($TaggedAppearances));
				$HTML .= "<section class='appearances'><h2>Related <a href='/cg'>Color Guide</a> $pages</h2>";
				$LINKS = '<ul>';
				$isStaff = Permission::sufficient('staff');
				foreach ($TaggedAppearances as $p){
					$safeLabel = $p->getSafeLabel();
					if ($p->isPrivate(true))
						$preview = "<span class='typcn typcn-".($isStaff?'lock-closed':'time').' color-'.($isStaff?'orange':'darkblue')."'></span> ";
					else {
						if ($hidePreviews)
							$preview = '';
						else {
							$preview = $p->getPreviewURL();
							$preview = "<img src='$preview' class='preview' alt=''>";
						}
					}
					$label = $p->processLabel();
					$LINKS .= "<li><a href='/cg/v/{$p->id}-$safeLabel'>$preview$label</a></li>";
				}
				$HTML .= "$LINKS</ul></section>";
			}
		}
		return $HTML;
	}

	public static function validateSeason($allowMovies = false){
		return (new Input('season','int', [
			Input::IN_RANGE => [$allowMovies ? 0 : 1, 8],
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
