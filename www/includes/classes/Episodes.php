<?php

use DB\Episode;

class Episodes {
		const TITLE_CUTOFF = 26;
		static $ALLOWED_PREFIXES = array(
			'Equestria Girls' => 'EQG',
		);

		/**
		 * Returns all episodes from the database, properly sorted
		 *
		 * @param int|int[]   $count
		 * @param string|null $where
		 *
		 * @return Episode|Episode[]
		 */
		static function Get($count = null, $where = null){
			/** @var $ep Episode */
			global $Database;

			if (!empty($where))
				$Database->where($where);

			$Database->orderBy('season')->orderBy('episode')->where('season != 0');
			if ($count !== 1){
				$eps =  $Database->get('episodes',$count);
				foreach ($eps as &$ep)
					$ep = $ep->addAiringData();
				return $eps;
			}
			else {
				$ep = $Database->getOne('episodes');
				return $ep->addAiringData();
			}
		}

		const ALLOW_MOVIES = true;

		/**
		 * If an episode is a two-parter's second part, then returns the first part
		 * Otherwise returns the episode itself
		 *
		 * @param int  $episode
		 * @param int  $season
		 * @param bool $allowMovies
		 *
		 * @throws Exception
		 *
		 * @return Episode|null
		 */
		static function GetActual(int $season, int $episode, bool $allowMovies = false){
			global $Database;

			if (!$allowMovies && $season == 0)
				throw new Exception('This action cannot be performed on movies');

			/**
			 * @var $Ep Episode
			 * @var $Part1 Episode
			 */
			$Ep = $Database->whereEp($season,$episode)->getOne('episodes');
			if (!empty($Ep))
				return $Ep->addAiringData();

			$Part1 = $Database->whereEp($season,$episode-1)->getOne('episodes');
			return !empty($Part1) && !empty($Part1->twoparter)
				? $Part1->addAiringData()
				: null;
		}

		/**
		 * Returns the latest episode
		 *
		 * @return Episode
		 */
		static function GetLatest(){
			return self::Get(1,"airs < NOW() + INTERVAL '24 HOUR' && season != 0");
		}

		/**
		 * Checks if provided episode is the latest episode
		 *
		 * @param Episode $Episode
		 *
		 * @deprecated User $Episode->isLatest()
		 *
		 * @return bool
		 */
		static function IsLatest($Episode){
			$latest = self::GetLatest();
			return $Episode->season === $latest->season
				&& $Episode->episode === $latest->episode;
		}

		/**
		 * Adds airing-.related information to an episodes table row
		 *
		 * @param Episode $Episode
		 *
		 * @deprecated
		 *
		 * @return Episode
		 */
		static function AddAiringData($Episode){
			if (empty($Episode))
				return null;

			return $Episode->addAiringData();
		}

		/**
		 * Turns an 'episode' database row into a readable title
		 *
		 * @param Episode $Episode     Episode row from the database
		 * @param bool        $returnArray Whether to return as an array instead of string
		 * @param string      $arrayKey
		 * @param bool        $append_num  Append overall # to ID
		 *
		 * @deprecated
		 *
		 * @return string|array
		 */
		static function FormatTitle($Episode, $returnArray = false, $arrayKey = null, $append_num = true){
			if (empty($Episode))
				return null;

			$Episode->formatTitle($returnArray, $arrayKey, $append_num);
		}

		static function RemoveTitlePrefix($title){
			global $PREFIX_REGEX;

			return $PREFIX_REGEX->replace('', $title);
		}

		static function ShortenTitlePrefix($title){
			global $PREFIX_REGEX;

			if (!$PREFIX_REGEX->match($title, $match) || !isset(self::$ALLOWED_PREFIXES[$match[1]]))
				return $title;

			return self::$ALLOWED_PREFIXES[$match[1]].': '.self::RemoveTitlePrefix($title);
		}

		/**
		 * Loads the episode page
		 *
		 * @param null|int|Episode $force If null: Parses $data and loads approperiate epaisode
		 *                              If array: Uses specified arra as Episode data
		 */
		static function LoadPage($force = null){
			global $data, $CurrentEpisode, $Latest, $Database, $PrevEpisode, $NextEpisode;

			if ($force instanceof Episode)
				$CurrentEpisode = $force;
			else {
				$EpData = self::ParseID($data);

				if ($EpData['season'] === 0){
					error_log("Attempted visit to $data from ".(!empty($_SERVER['HTTP_REFERER'])?$_SERVER['HTTP_REFERER']:'[unknown referrer]').', redirecting to /movie page');
					HTTP::Redirect('/movie/'.$EpData['episode']);
				}

				$CurrentEpisode = empty($EpData)
					? self::GetLatest()
					: self::GetActual($EpData['season'], $EpData['episode']);
			}
			if (!empty($CurrentEpisode))
				$Latest = empty($EpData) ? true : $CurrentEpisode->isLatest();

			$url = $CurrentEpisode->formatURL();
			CoreUtils::FixPath($url);

			$js = array('imagesloaded.pkgd','jquery.ba-throttle-debounce','jquery.fluidbox','Chart','episode');
			if (Permission::Sufficient('member'))
				$js[] = 'episode-manage';
			if (Permission::Sufficient('staff')){
				$js[] = 'moment-timezone';
				$js[] = 'episodes-manage';
			}

			if (!$CurrentEpisode->isMovie){
				$PrevEpisode = $Database
					->where('no',$CurrentEpisode->no, '<')
					->where('season', 0, '!=')
					->orderBy('no','DESC')
					->getOne('episodes','season,episode,title,twoparter');
				$NextEpisode = $Database
					->where('no',$CurrentEpisode->no, '>')
					->where('season', 0, '!=')
					->orderBy('no','ASC')
					->getOne('episodes','season,episode,title,twoparter');
			}
			else {
				$PrevEpisode = $Database
					->where('season', 0)
					->where('episode',$CurrentEpisode->episode, '<')
					->orderBy('episode','DESC')
					->getOne('episodes','season,episode,title');
				$NextEpisode = $Database
					->where('season', 0)
					->where('episode',$CurrentEpisode->episode, '>')
					->orderBy('episode','ASC')
					->getOne('episodes','season,episode,title');
			}

			$heading = $CurrentEpisode->formatTitle();
			CoreUtils::LoadPage(array(
				'title' => "$heading - Vector Requests & Reservations",
				'heading' => $heading,
				'view' => 'episode',
				'css' => 'episode',
				'js' => $js,
			));
		}

		/**
		 * Extracts the season and episode numbers from the episode ID string
		 * Examples:
		 *   "S1E1" => {season:1,episode:1}
		 *   "S01E01" => {season:1,episode:1}
		 *   "S1E1-2" => {season:1,episode:1,twoparter:true}
		 *   "S01E01-02" => {season:1,episode:1,twoparter:true}
		 *
		 * @param string $id
		 * @return null|array
		 */
		static function ParseID($id){
			if (empty($id))
				return null;

			global $EPISODE_ID_REGEX, $MOVIE_ID_REGEX;
			if (regex_match($EPISODE_ID_REGEX, $id, $match))
				return array(
					'season' => intval($match[1], 10),
					'episode' => intval($match[2], 10),
					'twoparter' => !empty($match[3]),
				);
			else if (regex_match($MOVIE_ID_REGEX, $id, $match))
				return array(
					'season' => 0,
					'episode' => intval($match[1], 10),
					'twoparter' => false,
				);
			else return null;
		}

		static function MovieSafeTitle($title){
			return regex_replace(new RegExp('-{2,}'), '-', regex_replace(new RegExp('[^a-z]','i'), '-', $title));
		}

		/**
		 * @param Episode $Episode
		 *
		 * @deprecated
		 *
		 * @return string
		 */
		static function FormatURL($Episode){
			if (empty($Episode))
				return null;

			return $Episode->formatURL();
		}

		/**
		 * Get user's vote for an episode
		 *
		 * Accepts a single array containing values
		 *  for the keys 'season' and 'episode'
		 * Return's the user's vote entry from the DB
		 *
		 * @param Episode $Ep
		 * @return array
		 */
		static function GetUserVote($Ep){
			global $Database, $signedIn, $currentUser;
			if (!$signedIn) return null;
			return $Database
				->whereEp($Ep)
				->where('user', $currentUser['id'])
				->getOne('episodes__votes');
		}

		/**
		 * Get video embed HTML for an episode
		 *
		 * @param Episode $Episode
		 *
		 * @return array
		 */
		static function GetVideoEmbeds($Episode):array {
			global $Database;

			$EpVideos = $Database
				->whereEp($Episode)
				->orderBy('provider', 'ASC')
				->orderBy('part', 'ASC')
				->get('episodes__videos');
			$Parts = 0;
			$embed = '';
			if (!empty($EpVideos)){
				$Videos = array();
				foreach ($EpVideos as $v)
					$Videos[$v['provider']][(int)$v['part']] = $v;
				// YouTube embed preferred
				$Videos = !empty($Videos['yt']) ? $Videos['yt'] : $Videos['dm'];

				$Parts = count($Videos);
				foreach ($Videos as $v)
					$embed .= "<div class='responsive-embed".($Episode->twoparter&& $v['part']!==1?' hidden':'')."'>".VideoProvider::get_embed($v['id'], $v['provider'])."</div>";
			}
			return array($Parts, $embed);
		}

		static
			$VIDEO_PROVIDER_NAMES = array(
				'yt' => 'YouTube',
				'dm' => 'Dailymotion',
			),
			$PROVIDER_BTN_CLASSES = array(
				'yt' => 'red typcn-social-youtube',
				'dm' => 'darkblue typcn-video',
			);
		/**
		 * Renders the HTML of the "Watch the Episode" section along with the buttons/links
		 *
		 * @param Episode $Episode
		 *
		 * @return string
		 */
		static function GetVideosHTML($Episode):string {
			global $Database;

			$HTML = '';
			$Videos = $Database
				->whereEp($Episode)
				->orderBy('provider', 'ASC')
				->orderBy('part', 'ASC')
				->get('episodes__videos');

			if (!empty($Videos)){
				$fullep = $Episode->twoparter ? 'Full episode' : '';
				if (count($Videos) === 1 && $Videos[0]['provider'] === 'yt'){
					$airtime = strtotime($Episode->airs);
					$modified = $Videos[0]['modified'];
					if (!empty($modified) && $airtime > strtotime($modified)){
						$fullep = 'Livestream';
						$Episode = $Episode->addAiringData();
						if ($Episode->aired)
							$fullep .= ' recording';
						if (!$Episode->twoparter)
							$fullep = " ($fullep)";
					}
				}

				$HTML = "<section class='episode'><h2>Watch the ".($Episode->isMovie?'Movie':'Episode')."</h2><p class='align-center actions'>";
				foreach ($Videos as $v){
					$url = VideoProvider::get_embed($v['id'], $v['provider'], VideoProvider::URL_ONLY);
					$partText = $Episode->twoparter ? (
						!$v['fullep']
						? " (Part {$v['part']})"
						: " ($fullep)"
					) : $fullep;
					$HTML .= "<a class='btn typcn ".self::$PROVIDER_BTN_CLASSES[$v['provider']]."' href='$url' target='_blank'>".self::$VIDEO_PROVIDER_NAMES[$v['provider']]."$partText</a>";
				}
				$HTML .= "<button class='green typcn typcn-eye showplayers'>Show on-site player</button></p></section>";
			}

			return $HTML;
		}

		/**
		 * Get the <tbody> contents for the episode list table
		 *
		 * @param Episode[]|null $Episodes
		 * @param bool               $areMovies
		 *
		 * @return string
		 */
		static function GetTableTbody($Episodes = null, bool $areMovies = false):string {
			if (empty($Episodes))
				return "<tr class='empty align-center'><td colspan='3'><em>There are no ".($areMovies?'movies':'episodes')." to display</em></td></tr>";

			$Body = '';
			$PathStart = '/episode/';
			$displayed = false;
			/** @var Episode */
			foreach ($Episodes as $Episode) {
				$adminControls = Permission::Insufficient('staff') ? '' : <<<HTML
<span class='admincontrols'>
	<button class='edit-episode typcn typcn-pencil blue' title='Edit episode'></button>
	<button class='delete-episode typcn typcn-times red' title='Delete episode'></button>
</span>
HTML;
				$SeasonEpisode = $DataID = '';
				$title = $Episode->formatTitle(AS_ARRAY);
				if (!$Episode->isMovie){
					$href = $PathStart.$title['id'];

					$SeasonEpisode = <<<HTML
				<td class='season' rowspan='2'>{$title['season']}</td>
				<td class='episode' rowspan='2'>{$title['episode']}</td>
HTML;

				}
				else {
					$href = $Episode->formatURL();
					$Episode = $Episode->addAiringData();
					$SeasonEpisode = "<td class='episode' rowspan='2'>{$title['episode']}</td>";
				}
				$DataID = " data-epid='{$title['id']}'";

				$star = '';
				if ($Episode->isLatest()){
					$displayed = true;
					$star = '<span class="typcn typcn-home" title="Curently visible on the homepage"></span> ';
				}
				if (!$Episode->aired)
					$star .= '<span class="typcn typcn-media-play-outline" title="'.($Episode->isMovie?'Movie':'Episode').' didn\'t air yet, voting disabled"></span>&nbsp;';

				$airs = Time::Tag($Episode->airs, Time::TAG_EXTENDED, Time::TAG_NO_DYNTIME);

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
		 * Render upcoming episode HTML
		 *
		 * @param bool $wrap Whether to output the wrapper element
		 *
		 * @return string
		 */
		static function GetSidebarUpcoming($wrap = true){
			global $Database, $PREFIX_REGEX;
			$Upcoming = $Database->where('airs > NOW()')->orderBy('airs', 'ASC')->get('episodes');
			if (empty($Upcoming)) return;

			$HTML = $wrap ? '<section id="upcoming"><h2>Upcoming episodes</h2><ul>' : '';
			/** @var Episode */
			foreach ($Upcoming as $Episode){
				$airtime = strtotime($Episode->airs);
				$airs = date('c', $airtime);
				$month = date('M', $airtime);
				$day = date('j', $airtime);
				$diff = Time::Difference(time(), $airtime);

				$time = 'in ';
				if ($diff['time'] < Time::$IN_SECONDS['month']){
					$tz = "(".date('T', $airtime).")";
					if (!empty($diff['week']))
						$diff['day'] += $diff['week'] * 7;
					if (!empty($diff['day']))
						$time .=  "{$diff['day']} day".($diff['day']!==1?'s':'').' & ';
					if (!empty($diff['hour']))
						$time .= "{$diff['hour']}:";
					foreach (array('minute','second') as $k)
						$diff[$k] = CoreUtils::Pad($diff[$k]);
					$time = "<time datetime='$airs'>$time{$diff['minute']}:{$diff['second']} $tz</time>";
				}
				else $time = Time::Tag($Episode->airs);

				$title = !$Episode->isMovie
					? $Episode->title
					: (
						$PREFIX_REGEX->match($Episode->title)
						? Episodes::ShortenTitlePrefix($Episode->title)
						: "Movie: {$Episode->title}"
					);

				$HTML .= "<li><div class='calendar'><span class='top'>$month</span><span class='bottom'>$day</span></div>".
					"<div class='meta'><span class='title'>$title</span><span class='time'>Airs $time</span></div></li>";
			}
			return $HTML.($wrap?'</ul></section>':'');
		}

		/**
		 * Render episode voting HTML
		 *
		 * @param Episode $Episode
		 *
		 * @return string
		 */
		static function GetSidebarVoting($Episode){
			$thing = $Episode->isMovie ? 'movie' : 'episode';
			if (!$Episode->aired)
				return "<p>Voting will start ".Time::Tag($Episode->willair).", after the $thing had aired.</p>";
			global $Database, $signedIn, $currentUser;
			$HTML = '';

			$Score = $Database->whereEp($Episode)->getOne('episodes__votes','AVG(vote) as score, COUNT(vote) as votes');
			$Votes = !empty($Score['votes']) ? $Score['votes'] : 0;
			if (!empty($Score['score']))
				$Score = $Score['score'];
			else $Score = 0;

			$Score = round($Score*10)/10;
			$ScorePercent = round(($Score/5)*1000)/10;

			$HTML .= '<p>'.(!empty($Score) ? "This $thing is rated $Score/5 (<a class='detail'>".CoreUtils::MakePlural('vote', $Votes, true).'</a>)' : 'Nopony voted yet.').'</p>';
			if ($Score > 0)
				$HTML .= "<img src='/muffin-rating?w=$ScorePercent' id='muffins' alt='muffin rating svg'>";

			$UserVote = Episodes::GetUserVote($Episode);
			if (empty($UserVote)){
				$HTML .= "<br><p>What did <em>you</em> think about the $thing?</p>";
				if ($signedIn)
					$HTML .= "<button class='blue rate typcn typcn-star'>Cast your vote</button>";
				else $HTML .= "<p><em>Sign in above to cast your vote!</em></p>";
			}
			else $HTML .= "<p>Your rating: ".CoreUtils::MakePlural('muffin', $UserVote['vote'], PREPEND_NUMBER).'</p>';

			return $HTML;
		}

		/**
		 * Get a list of IDs for tags related to the episode
		 *
		 * @param Episode $Episode
		 *
		 * @return int[]
		 */
		static function GetTagIDs($Episode){
			global $CGDb;

			$sn = CoreUtils::Pad($Episode->season);
			$en = CoreUtils::Pad($Episode->episode);
			$EpTagIDs = array();
			$EpTagPt1 = $CGDb->where('name',"s{$sn}e{$en}")->where('type','ep')->getOne('tags','tid');
			if (!empty($EpTagPt1))
				$EpTagIDs[] = $EpTagPt1['tid'];
			if ($Episode->twoparter){
				$next_en = CoreUtils::Pad($Episode->episode+1);
				$EpTagPt2 = $CGDb->rawQuery("SELECT tid FROM tags WHERE name IN ('s{$sn}e{$next_en}', 's{$sn}e{$en}-{$next_en}') && type = 'ep'");
				foreach ($EpTagPt2 as $t)
					$EpTagIDs[] = $t['tid'];
			}
			return $EpTagIDs;
		}

		static function GetAppearancesSectionHTML($Epsiode){
			global $CGDb, $Color;

			$HTML = '';
			$EpTagIDs = Episodes::GetTagIDs($Epsiode);
			if (!empty($EpTagIDs)){
				$TaggedAppearances = $CGDb->rawQuery(
					"SELECT p.id, p.label
					FROM tagged t
					LEFT JOIN appearances p ON t.ponyid = p.id
					WHERE t.tid IN (".implode(',',$EpTagIDs).")
					ORDER BY p.label");

				if (!empty($TaggedAppearances)){
					$pages = CoreUtils::MakePlural('page', count($TaggedAppearances));
					$HTML .= "<section class='appearances'><h2>Related <a href='/cg'>$Color Guide</a> $pages</h2>";
					$LINKS = '<ul>';
					foreach ($TaggedAppearances as $p){
						$safeLabel = \CG\Appearances::GetSafeLabel($p);
						$preview = \CG\Appearances::GetPreviewURL($p);
						$LINKS .= "<li><a href='/cg/v/{$p['id']}-$safeLabel'><img src='$preview' class='preview'>{$p['label']}</a></li>";
					}
					$HTML .= "$LINKS</p></section>";
				}
			}
			return $HTML;
		}

		/**
		 * Gets the number of posts bound to an episode
		 *
		 * @param Episode $Episode
		 *
		 * @deprecated
		 *
		 * @return int
		 */
		static function GetPostCount($Episode){
			global $Database;
			
			return $Episode->getPostCount();
		}

		static function ValidateSeason($allowMovies = false){
			return (new Input('season','int',array(
				Input::IN_RANGE => [$allowMovies ? 0 : 1, 8],
				Input::CUSTOM_ERROR_MESSAGES => array(
					Input::ERROR_MISSING => 'Season number is missing',
					Input::ERROR_INVALID => 'Season number (@value) is invalid',
					Input::ERROR_RANGE => 'Season number must be between @min and @max',
				)
			)))->out();
		}
		static function ValidateEpisode($optional = false, $EQG = false){
			$FieldName = $EQG ? 'Overall movie number' : 'Episode number';
			return (new Input('episode','int',array(
				Input::IS_OPTIONAL => $optional,
				Input::IN_RANGE => [1,26],
				Input::CUSTOM_ERROR_MESSAGES => array(
					Input::ERROR_MISSING => "$FieldName is missing",
					Input::ERROR_INVALID => "$FieldName (@value) is invalid",
					Input::ERROR_RANGE => "$FieldName must be between @min and @max",
				)
			)))->out();
		}
	}
