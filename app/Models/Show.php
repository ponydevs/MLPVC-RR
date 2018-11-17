<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\Auth;
use App\CoreUtils;
use App\DB;
use App\ShowHelper;
use App\Permission;
use App\Posts;
use App\Regexes;
use App\RegExp;
use App\Time;
use App\TMDBHelper;
use App\VideoProvider;

/**
 * @property int          $id
 * @property string       $type
 * @property int          $season
 * @property int          $episode
 * @property bool         $twoparter             (Uses magic method)
 * @property string       $title
 * @property DateTime     $posted
 * @property string       $posted_by
 * @property DateTime     $airs
 * @property int          $no
 * @property string|null  $score                 (Uses magic method)
 * @property string       $notes
 * @property DateTime     $synopsis_last_checked
 * @property bool         $is_movie              (Via magic method)
 * @property bool         $displayed             (Via magic method)
 * @property bool         $aired                 (Via magic method)
 * @property DateTime     $willair               (Via magic method)
 * @property int          $willairts             (Via magic method)
 * @property string       $short_title           (Via magic method)
 * @property Appearance[] $related_appearances   (Via magic method)
 * @property ShowVideo[]  $videos                (Via relations)
 * @property User         $poster                (Via relations)
 * @method static Show find_by_season_and_episode(int $season, int $episode)
 * @method static Show find(int $show_id)
 */
class Show extends NSModel implements Linkable {
	public static $table_name = 'show';

	public static $has_many = [
		['videos', 'class' => 'ShowVideo', 'order' => 'provider asc, part asc'],
	];
	public static $belongs_to = [
		['poster', 'class' => 'User', 'foreign_key' => 'posted_by'],
	];

	public function get_is_movie():bool {
		return $this->season === null;
	}
	/** For Twig */
	public function getIs_movie():bool {
		return $this->is_movie;
	}

	private function _normalizeScore($value):string {
		return is_numeric($value) ? preg_replace('/^(\d+)\.0+$/','$1',number_format($value,1)) : '0';
	}

	public function get_score():string {
		$attr = $this->read_attribute('score');
		if (!is_numeric($attr))
			$this->updateScore();
		return $this->_normalizeScore($attr);
	}

	public function set_score($score){
		$this->assign_attribute('score', $this->_normalizeScore($score));
	}

	public function get_displayed(){
		return $this->isDisplayed();
	}

	public function get_willairts(){
		return $this->willHaveAiredBy();
	}

	public function get_aired(){
		return $this->hasAired();
	}

	public function get_willair(){
		return gmdate('c', $this->willairts);
	}

	public function get_short_title(){
		return ShowHelper::shortenTitlePrefix($this->title);
	}

	private $rel_appearances;
	/** @return Appearance[] */
	public function get_related_appearances(){
		if ($this->rel_appearances !== null)
			return $this->rel_appearances;

		$tag_ids = $this->getTagIDs();

		if (!empty($tag_ids)){
			$this->rel_appearances = DB::$instance->setModel(Appearance::class)->query(
				'SELECT p.* FROM tagged t
				LEFT JOIN appearances p ON t.appearance_id = p.id
				WHERE t.tag_id IN ('.implode(',', $tag_ids).')
				ORDER BY p.label');
		}
		else $this->rel_appearances = [];

		return $this->rel_appearances;
	}

	/**
	 * @return Post[]|null
	 */
	public function getRequests(){
		$requests = Posts::get($this->id, ONLY_REQUESTS, Permission::sufficient('staff'));

		$arranged = [
			'finished' => [],
			'unfinished' => [
				'chr' => [],
				'obj' => [],
				'bg'  => [],
			],
		];
		if (!empty($requests))
			foreach ($requests as $req){
				if ($req->finished)
					$arranged['finished'][] = $req;
				else $arranged['unfinished'][$req->type][] = $req;
			}

		return $arranged;
	}

	/**
	 * @return Post[]|null
	 */
	public function getReservations(){
		$reservations = Posts::get($this->id, ONLY_RESERVATIONS, Permission::sufficient('staff'));

		$arranged = [
			'unfinished' => [],
			'finished' => [],
		];
		if (!empty($reservations))
			foreach ($reservations as $res){
				$k = ($res->finished?'':'un').'finished';
				$arranged[$k][] = $res;
			}

		return $arranged;
	}

	/**
	 * @param array $o
	 *
	 * @return string
	 */
	public function getID(array $o = []):string {
		if ($this->is_movie)
			return "Movie#{$this->episode}";

		$episode = $this->episode;
		$season = $this->season;

		if (empty($o['pad'])){
			if ($this->twoparter)
				$episode = $episode.'-'.($episode+1);
			return "S{$season}E{$episode}";
		}

		$episode = CoreUtils::pad($episode).($this->twoparter ? '-'.CoreUtils::pad($episode+1) : '');
		$season = CoreUtils::pad($season);

		return "S{$season} E{$episode}";
	}

	/**
	 * Gets the number of posts bound to an episode
	 *
	 * @return int
	 */
	public function getPostCount():int {
		return DB::$instance->where('show_id', $this->id)->count('posts');
	}

	/**
	 * @return string
	 */
	public function movieSafeTitle():string {
		return (new RegExp('-{2,}'))->replace('-', (new RegExp('[^a-z]','i'))->replace('-', $this->title));
	}

	/**
	 * @param Show $ep
	 *
	 * @return bool
	 */
	public function is(Show $ep):bool {
		return $this->id === $ep->id;
	}

	private $latest_episode;
	public function isLatest():bool {
		if ($this->latest_episode === null)
			$this->latest_episode = ShowHelper::getLatest();
		return $this->is($this->latest_episode);
	}

	/**
	 * @param int $now Current time (for testing purposes)
	 *
	 * @return bool Indicates whether the episode is close enough to airing to be the home page
	 */
	public function isDisplayed($now = null):bool {
		$airtime = strtotime($this->airs);
		return strtotime('-24 hours', $airtime) < ($now ?? time());
	}

	/**
	 * @return int The timestamp after which the episode is considered to have aired & voting can be enabled
	 */
	public function willHaveAiredBy():int {
		$airtime = strtotime($this->airs);
		return strtotime('+'.($this->is_movie?'2 hours':((!$this->twoparter?30:60).' minutes')), $airtime);
	}

	/**
	 * @param int $now Current time (for testing purposes)
	 *
	 * @return bool True if willHaveAiredBy() is in the past
	 */
	public function hasAired($now = null):bool {
		return $this->willairts < ($now ?? time());
	}

	/**
	 * Turns an 'episode' database row into a readable title
	 *
	 * @param bool        $returnArray Whether to return as an array instead of string
	 * @param string      $arrayKey
	 *
	 * @return string|array
	 */
	public function formatTitle($returnArray = false, $arrayKey = null){
		if ($returnArray === AS_ARRAY) {
			$arr = [
				'id' => $this->getID(),
				'season' => $this->season ?? null,
				'episode' => $this->episode ?? null,
				'title' => isset($this->title) ? CoreUtils::escapeHTML($this->title) : null,
			];

			if (!empty($arrayKey))
				return $arr[$arrayKey] ?? null;
			else return $arr;
		}

		if ($this->is_movie)
			return $this->title;

		return $this->getID(['pad' => true]).': '.$this->title;
	}

	public function toURL():string {
		if (!$this->is_movie)
			return '/episode/'.$this->getID();
		return "/movie/{$this->episode}".(!empty($this->title)?'-'.$this->movieSafeTitle():'');
	}

	public function toAnchor(?string $text = null):string {
		if (empty($text))
			$text = $this->getID();
		return "<a href='{$this->toURL()}'>$text</a>";
	}

	public function updateScore(){
		$Score = DB::$instance->where('show_id', $this->id)->disableAutoClass()->getOne(ShowVote::$table_name,'AVG(vote) as score');
		$this->score = !empty($Score['score']) ? $Score['score'] : 0;
		$this->save();
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
	public static function parseID($id){
		if (empty($id))
			return null;

		if (preg_match(Regexes::$episode_id, $id, $match))
			return [
				'season' => \intval($match[1], 10),
				'episode' => \intval($match[2], 10),
				'twoparter' => !empty($match[3]),
			];
		else if (preg_match(Regexes::$movie_id, $id, $match))
			return [
				'season' => 0,
				'episode' => \intval($match[1], 10),
				'twoparter' => false,
			];
		else return null;
	}

	/**
	 * Gets the rating given to the episode by the user, or null if not voted
	 *
	 * @param User $user
	 *
	 * @return ShowVote|null
	 */
	public function getUserVote(?User $user = null):?ShowVote {
		if ($user === null && Auth::$signed_in)
			$user = Auth::$user;
		return ShowVote::find_for($this, $user);
	}

	public const
		PREVIOUS = '<',
		NEXT = '>';
	/**
	 * @param string $dir Expects self::PREVIOUS or self::NEXT
	 *
	 * @return Show|null
	 */
	private function _getAdjacent($dir):?Show {
		$is = $this->is_movie ? '=' : '!=';
		$col = $this->is_movie ? 'episode' : 'no';
		return Show::find('first', [
			'conditions' => [
				"season $is 0 AND $col $dir ?",
				$this->{$col}
			],
			'order' => "$col ".($dir === self::NEXT ? 'asc' : 'desc'),
			'limit' => 1,
		]);
	}

	/**
	 * Get the previous episode based on overall episode number
	 *
	 * @return Show|null
	 */
	public function getPrevious():?Show {
		return $this->_getAdjacent(self::PREVIOUS);
	}

	/**
	 * Get the previous episode based on overall episode number
	 *
	 * @return Show|null
	 */
	public function getNext():?Show {
		return $this->_getAdjacent(self::NEXT);
	}

	/**
	 * Get a list of IDs for tags related to the episode
	 *
	 * @deprecated Use getCGTag
	 *
	 * @return int[]
	 */
	public function getTagIDs():array {
		if ($this->is_movie){
			$MovieTagIDs = [];
			/** @var $MovieTag Tag */
			$MovieTag = DB::$instance->where('name',"movie{$this->episode}")->where('type','ep')->getOne('tags','id');
			if (!empty($MovieTag->id))
				$MovieTagIDs[] = $MovieTag->id;
			return $MovieTagIDs;
		}

		$sn = CoreUtils::pad($this->season);
		$en = CoreUtils::pad($this->episode);
		$tag_ids = [];
		/** @var $tag_pt1 array */
		$tag_pt1 = DB::$instance->disableAutoClass()->where('name',"s{$sn}e{$en}")->where('type','ep')->getOne('tags','id');
		if (!empty($tag_pt1))
			$tag_ids[] = $tag_pt1['id'];
		if ($this->twoparter){
			$next_en = CoreUtils::pad($this->episode+1);
			/** @var $tag_pt2 array */
			$tag_pt2 = DB::$instance
				->where('name', ["s{$sn}e{$next_en}", "s{$sn}e{$en}-{$next_en}"])
				->where('type', 'ep')
				->get('tags', null, 'id');
			foreach ($tag_pt2 as $t)
				$tag_ids[] = $t['id'];
		}
		return $tag_ids;
	}


	/**
	 * Get a user's vote for this episode
	 * Accepts a single array containing values
	 *  for the keys 'season' and 'episode'
	 * Return's the user's vote entry from the DB
	 *
	 * @param User $user
	 *
	 * @return ShowVote|null
	 */
	public function getVoteOf(?User $user = null):?ShowVote {
		if ($user === null) return null;
		return ShowVote::find_for($this, $user);
	}

	/**
	 * Get video embed HTML for an episode
	 *
	 * @return array
	 */
	public function getVideoEmbeds():array {
		$parts = 0;
		$embed = '';
		if (\count($this->videos) > 0){
			$Videos = [];
			foreach ($this->videos as $v)
				$Videos[$v->provider][$v->part] = $v;
			// YouTube embed preferred
			$Videos = !empty($Videos['yt']) ? $Videos['yt'] : ($Videos['dm'] ?? $Videos['sv'] ?? $Videos['mg']);
			/** @var $Videos ShowVideo[] */

			$parts = \count($Videos);
			foreach ($Videos as $v)
				$embed .= "<div class='responsive-embed".($this->twoparter && $v->part!==1?' hidden':'')."'>".VideoProvider::getEmbed($v).'</div>';
		}
		return [
			'parts' => $parts,
			'html' => $embed
		];
	}

	/**
	 * Get synopses for the episode from TMDb
	 *
	 * @return array[]
	 */
	public function getSynopses():array {
		$client = TMDBHelper::getClient();
		$show_id = TMDBHelper::getShowId();
		$ep_data = TMDBHelper::getEpisodes($client, $this);
		$synopses = [];
		foreach ($ep_data as $item){
			$append = [
				'id' => $item['id'],
				'overview' => $item['overview'],
				'url' => "https://www.themoviedb.org/tv/{$show_id}/season/{$item['season_number']}/episode/{$item['episode_number']}",
			];

			if (!empty($item['still_path'])){
				$append['image'] = TMDBHelper::getImageUrl($client, $item['still_path']);
			}

			$synopses[] = $append;
		}
		return $synopses;
	}

	public function getCGTagName(){
		return $this->type.$this->id;
	}

	public function getCGTag(){
		return Tag::find_by_name($this->getCGTagName());
	}

	public function createCGTag(){
		$tag = new Tag();
		$tag->name = $this->getCGTagName();
		$tag->type = 'ep';
		return $tag->save();
	}
}
