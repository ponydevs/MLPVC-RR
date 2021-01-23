<?php

namespace App\Controllers;

use App\Auth;
use App\CGUtils;
use App\CoreUtils;
use App\DB;
use App\DeviantArt;
use App\HTTP;
use App\Input;
use App\Models\Appearance;
use App\Models\Show;
use App\Models\ShowAppearance;
use App\Models\ShowVideo;
use App\Models\ShowVote;
use App\Pagination;
use App\Permission;
use App\Posts;
use App\Regexes;
use App\Response;
use App\ShowHelper;
use App\Twig;
use App\VideoProvider;
use DateInterval;
use Exception;
use League\Uri\UriModifier;
use Throwable;

class ShowController extends Controller {
  public function index() {
    $base_path = '/show';
    $episodes_pagination = new Pagination($base_path, 8, Show::count(['conditions' => "type = 'episode'"]), 'ep');
    $show_pagination = new Pagination($base_path, 8, Show::count(['conditions' => "type != 'episode'"]));

    DB::$instance->orderBy('generation', 'DESC')->orderBy('no', 'DESC');
    $episodes = ShowHelper::get($episodes_pagination->getLimit(), "type = 'episode'", true);
    DB::$instance->orderBy('no', 'DESC');
    $movies = ShowHelper::get($show_pagination->getLimit(), "type != 'episode'", true);

    $path = $episodes_pagination->toURI();
    $path = UriModifier::appendQuery($path, $show_pagination->getPageQueryString());
    CoreUtils::fixPath($path);
    $heading = 'Episodes & Movies';

    $settings = [
      'heading' => $heading,
      'title' => $heading,
      'css' => [true],
      'js' => ['paginate', true],
      'import' => [
        'episodes_pagination' => $episodes_pagination,
        'show_pagination' => $show_pagination,
        'episodes' => $episodes,
        'movies' => $movies,
      ],
    ];
    if (Permission::sufficient('staff')){
      $settings['js'][] = 'pages/show/index-manage';
      $settings['import']['export'] = [
        'episodeTitleRegex' => Regexes::$ep_title,
        'showTypes' => ShowHelper::VALID_TYPES,
        'generations' => ShowHelper::GENERATIONS,
      ];
    }
    CoreUtils::loadPage(__METHOD__, $settings);
  }

  public function latest():void {
    $latest_episode = ShowHelper::getLatest();
    if (empty($latest_episode))
      CoreUtils::loadPage(__CLASS__.'::view', [
        'title' => 'Home',
      ]);

    HTTP::tempRedirect($latest_episode->toURL());
  }

  public function viewEpisode($params):void {
    if (empty($params['id']))
      CoreUtils::notFound();

    $generation = $params['gen'] ?? null;
    if (!isset(ShowHelper::GENERATIONS[$generation]))
      $generation = ShowHelper::GEN_FIM;

    $ep_data = Show::parseID($params['id']);

    $current_episode = empty($ep_data)
      ? ShowHelper::getLatest()
      : ShowHelper::getActual($generation, $ep_data['season'], $ep_data['episode']);

    ShowHelper::loadPage($current_episode);
  }

  public function viewById($params):void {
    ShowHelper::loadPage(Show::find($params['id']));
  }

  /** @var Show */
  private $show;

  private function load_show($params):void {
    $this->show = Show::find($params['id']);
    if (empty($this->show))
      CoreUtils::notFound();
  }

  public function postList($params):void {
    if ($this->action !== 'GET')
      CoreUtils::notAllowed();

    $this->load_show($params);

    $section = $_GET['section'];
    $only = $section === 'requests' ? ONLY_REQUESTS : ONLY_RESERVATIONS;

    switch ($only){
      case ONLY_REQUESTS:
        if ($this->show->generation === ShowHelper::GEN_PL)
          $rendered = '';
        else {
          $requests = $this->show->getRequests();
          $rendered = Posts::getRequestsSection($requests);
        }
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
      $this->load_show($params);

    switch ($this->action){
      case 'GET':
        Response::done([ 'show' => $this->show->to_array() ]);
      break;
      case 'POST':
      case 'PUT':
        $update = [];
        if ($this->creating){
          $update['type'] = ShowHelper::validateType();
          $update['posted_by'] = Auth::$user->id;
          $is_episode = $update['type'] === 'episode';
          $what = CoreUtils::capitalize($update['type']);
        }
        else {
          $is_episode = $this->show->is_episode;
          $what = CoreUtils::capitalize($this->show->type);
        }

        if ($is_episode){
          $update['season'] = ShowHelper::validateSeason(ShowHelper::ALLOW_MOVIES);
          $update['episode'] = ShowHelper::validateEpisode(!$is_episode);

          if ($this->creating){
            $update['generation'] = ShowHelper::validateGeneration();

            $matching_id = Show::find_by_generation_and_season_and_episode($update['generation'], $update['season'], $update['episode']);
            if ($matching_id !== null){
              Response::fail('An episode with the same season and episode number already exists in this generation');
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

          $update['parts'] = 1;
          if (isset($_REQUEST['twoparter'])){
            $next_part = Show::find_by_season_and_episode($update['season'], $update['episode'] + 1);
            if (!empty($next_part))
              Response::fail("This episode cannot have two parts because {$next_part->toURL()} already exists.");
            $update['parts'] = 2;
          }
        }
        else if (!$this->creating){
          $update['type'] = ShowHelper::validateType();
          if ($update['type'] === 'episode')
            Response::fail('Show entries cannot be converted to episodes via the interface.');
        }

        $update['no'] = (new Input('no', 'int', [
          Input::IS_OPTIONAL => true,
          Input::IN_RANGE => [1, null],
          Input::CUSTOM_ERROR_MESSAGES => [
            Input::ERROR_INVALID => 'Overall number (@value) is invalid',
            Input::ERROR_RANGE => 'Overall number cannot be less than @min',
          ],
        ]))->out();

        $update['title'] = (new Input('title', 'string', [
          Input::IN_RANGE => [5, 100],
          Input::CUSTOM_ERROR_MESSAGES => [
            Input::ERROR_MISSING => "$what title is missing",
            Input::ERROR_RANGE => "$what title must be between @min and @max characters",
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
        if ($airs < strtotime('2010-10-10T00:00:00'))
          Response::fail('Air dates before October 10th, 2010 are invalid.');
        $update['airs'] = date('c', strtotime('this minute', $airs));

        $notes = (new Input('notes', 'text', [
          Input::IS_OPTIONAL => true,
          Input::IN_RANGE => [null, 1000],
          Input::CUSTOM_ERROR_MESSAGES => [
            Input::ERROR_RANGE => "$what notes cannot be longer than @max characters",
          ],
        ]))->out();
        if ($notes !== null){
          CoreUtils::checkStringValidity($notes, "$what notes");
          $notes = CoreUtils::sanitizeHtml($notes, ['a'], ['a.href']);
          if ($this->creating || $notes !== $this->show->notes)
            $update['notes'] = $notes;
        }
        else $update['notes'] = null;

        if ($this->creating){
          $this->show = new Show($update);
          if (!$this->show->save())
            Response::dbError('Show entry creation failed');

          Response::done(['url' => $this->show->toURL()]);
        }

        // Updating
        if (!DB::$instance->where('id', $this->show->id)->update(Show::$table_name, $update))
          Response::dbError('Updating show entry failed');

        Response::done();
      break;
      case 'DELETE':
        if (!DB::$instance->where('id', $this->show->id)->delete(Show::$table_name))
          Response::dbError();

        Response::success('Episode deleted successfully', [
          'upcoming' => CoreUtils::getSidebarUpcoming(NOWRAP),
        ]);
      break;
      default:
        CoreUtils::notAllowed();
    }
  }

  public function voteApi($params):void {
    $this->load_show($params);

    switch ($this->action){
      case 'GET':
        if (isset($_REQUEST['html']))
          Response::done(['html' => ShowHelper::getSidebarVoting($this->show)]);

        $vote_count_query = DB::$instance->query(
          "SELECT count(*) as value, vote as label FROM show_votes WHERE show_id = ? GROUP BY vote ORDER BY vote", [$this->show->id]);
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
          Response::fail("You already voted for this {$this->show->type}");

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

    $this->load_show($params);

    Response::done($this->show->getVideoEmbeds());
  }

  public function videoDataApi($params):void {
    if (Permission::insufficient('staff'))
      Response::fail();

    $this->load_show($params);

    switch ($this->action){
      case 'GET':
        $response = [
          'twoparter' => $this->show->parts === 2,
          'vidlinks' => [],
          'fullep' => [],
          'airs' => date('c', strtotime($this->show->airs)),
          'type' => $this->show->type,
        ];
        $videos = $this->show->videos;
        foreach ($videos as $part => $vid){
          if (!empty($vid->provider_id))
            $response['vidlinks']["{$vid->provider_abbr}_{$vid->part}"] = VideoProvider::getEmbed($vid, VideoProvider::URL_ONLY);
          if ($vid->fullep)
            $response['fullep'][] = $vid->provider_abbr;
        }
        Response::done($response);
      break;
      case 'PUT':
        foreach (array_keys(ShowHelper::VIDEO_PROVIDER_NAMES) as $provider){
          for ($part = 1; $part <= $this->show->parts; $part++){
            $set = null;
            $post_key = "{$provider}_$part";
            if (!empty($_REQUEST[$post_key])){
              $provider_name = ShowHelper::VIDEO_PROVIDER_NAMES[$provider];
              try {
                $vid_provider = new VideoProvider(DeviantArt::trimOutgoingGateFromUrl($_REQUEST[$post_key]));
              }
              catch (Exception $e){
                Response::fail("$provider_name#$part link issue: ".$e->getMessage());
              }
              if ($vid_provider->episodeVideo === null || $vid_provider->episodeVideo->provider_abbr !== $provider)
                Response::fail("Incorrect $provider_name#$part URL specified");
              $set = $vid_provider->id;
            }

            $fullep = $this->show->parts === 1;
            if ($part === 1 && $this->show->parts === 2 && isset($_REQUEST["{$post_key}_full"])){
              $next_part = $provider.'_'.($part + 1);
              $_REQUEST[$next_part] = null;
              $fullep = true;
            }

            $video_count = DB::$instance
              ->where('show_id', $this->show->id)
              ->where('provider_abbr', $provider)
              ->where('part', $part)
              ->count(ShowVideo::$table_name);
            if ($video_count === 0){
              if (!empty($set))
                ShowVideo::create([
                  'show_id' => $this->show->id,
                  'provider_abbr' => $provider,
                  'part' => $part,
                  'provider_id' => $set,
                  'fullep' => $fullep,
                ]);
            }
            else {
              DB::$instance
                ->where('show_id', $this->show->id)
                ->where('provider_abbr', $provider)
                ->where('part', $part);
              if (empty($set))
                DB::$instance->delete(ShowVideo::$table_name);
              else DB::$instance->update(ShowVideo::$table_name, [
                'provider_id' => $set,
                'fullep' => $fullep,
                'updated_at' => date('c'),
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

    $this->load_show($params);

    switch ($this->action){
      case 'GET':
        $columns = ['id', 'label', 'guide'];

        $linked_ids = [];
        foreach ($this->show->related_appearances as $p){
          $linked_ids[] = $p->id;
        }

        /** @var $appearances Appearance[] */
        $entries = DB::$instance->disableAutoClass()
          ->where('id', 0, '!=')
          ->where('owner_id IS NULL')
          ->orderBy('label')
          ->get('appearances', null, $columns);

        Response::done([
          'groups' => CGUtils::GUIDE_MAP,
          'entries' => $entries,
          'linkedIds' => $linked_ids,
        ]);
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
        if (empty($appearance_ids))
          $appearance_ids = [];

        $existing_relation_ids = array_map(function ($p) { return $p->id; }, $this->show->related_appearances);

        $added = array_diff($appearance_ids, $existing_relation_ids);
        if (!empty($added)){
          foreach ($added as $appearance_id)
            ShowAppearance::makeRelation($this->show->id, $appearance_id);
        }

        $removed = array_diff($existing_relation_ids, $appearance_ids);
        if (!empty($removed))
          DB::$instance->where('show_id', $this->show->id)->where('appearance_id', $removed)->delete(ShowAppearance::$table_name);

        $this->show->reload();

        Response::done(['section' => ShowHelper::getAppearancesSectionHTML($this->show)]);
      break;
      default:
        CoreUtils::notAllowed();
    }
  }

  public function brokenVideos($params):void {
    $this->load_show($params);

    $removed = 0;
    foreach ($this->show->videos as $k => $video){
      if (!$video->isBroken())
        continue;

      $removed++;
      $video->delete();
      unset($this->show->videos[$k]);
    }

    if ($removed === 0){
      Response::success("No broken videos found under this {$this->show->type}.");

      return;
    }

    Response::success("$removed video link".($removed === 1 ? ' has' : 's have').' been removed from the site. Thank you for letting us know.', [
      'epsection' => ShowHelper::getVideosHTML($this->show, NOWRAP),
    ]);
  }

  public function synopsis($params) {
    $this->load_show($params);

    try {
      $synopses = $this->show->getSynopses();
    }
    catch (Throwable $exception){
      $message = $exception->getMessage();
      if (strpos($message, '503 Service Temporarily Unavailable') !== false){
        Response::fail('The TMDB API is experiencing a temporary outage, synopsis data is currently unavailable.');
      }
    }

    Response::done([
      'html' => Twig::$env->render('show/_synopsis.html.twig', [
        'current_episode' => $this->show,
        'synopses' => $synopses,
        'wrap' => false,
        'lazyload' => false,
        'signed_in' => Auth::$signed_in,
      ]),
    ]);
  }

  public function next():void {
    if ($this->action !== 'GET')
      CoreUtils::notAllowed();

    $next_episode = DB::$instance->where('season is not null AND airs > now()')->orderBy('airs')->getOne(Show::$table_name);
    if (empty($next_episode))
      Response::fail("The show is on hiatus, the next episode's title and air date is unknown.", ['hiatus' => true]);

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
    $last_added = DB::$instance->orderBy('no', 'DESC')->where('season is not null')->getOne(Show::$table_name);
    if (empty($last_added))
      Response::fail('No last added episode found');

    $season = $last_added->season;
    if ($last_added->parts === 2 && $last_added->episode + 1 === 26){
      $season++;
      $episode = 1;
      $airs = date('Y-m-d', strtotime('this saturday'));
    }
    else {
      $episode = min($last_added->episode + 1, 26);
      $airs = $last_added->airs->add(new DateInterval('P1W'))->format('Y-m-d');
    }
    Response::done([
      'season' => $season,
      'episode' => $episode,
      'no' => $last_added->no + $last_added->parts,
      'airday' => $airs,
    ]);
  }
}
