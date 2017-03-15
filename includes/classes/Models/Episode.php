<?php

namespace App\Models;

use App\Models\AbstractFillable;
use App\RegExp;
use App\Episodes;
use App\CoreUtils;
use GuzzleHttp\Tests\Ring\CoreTest;

class Episode extends AbstractFillable {
	/** @var int */
	public
		$season,
		$episode,
		$no,
		$score,
		$willairts,
		$isMovie;
	/** @var string */
	public
		$title,
		$posted,
		$posted_by,
		$airs,
		$willair;
	/** @var bool */
	public
		$twoparter,
		$displayed,
		$aired;

	/** @param array|object */
	public function __construct($iter = null){
		parent::__construct($this, $iter);

		$this->isMovie = $this->season === 0 ? 1 : 0;
		$this->twoparter = !empty($this->twoparter);
		$this->formatScore();
	}

	/**
	 * @param array $o
	 *
	 * @return string
	 */
	public function getID($o = array()):string {
		if ($this->isMovie)
			return 'Movie'.(!empty($o['append_num'])?'#'.$this->episode:'');

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
		global $Database;

		return (int) $Database->rawQuerySingle(
			'SELECT SUM(cnt) as postcount FROM (
				SELECT count(*) as cnt FROM requests WHERE season = :season && episode = :episode
				UNION ALL
				SELECT count(*) as cnt FROM reservations WHERE season = :season && episode = :episode
			) t',
			array(':season' => $this->season, ':episode' => $this->episode)
		)['postcount'];
	}

	/**
	 * @return string
	 */
	public function movieSafeTitle():string {
		return (new RegExp('-{2,}'))->replace('-', (new RegExp('[^a-z]','i'))->replace('-', $this->title));
	}

	/**
	 * @param Episode $ep
	 *
	 * @return bool
	 */
	public function is($ep):bool {
		return $this->season === $ep->season
			&& $this->episode === $ep->episode;
	}

	public function isLatest():bool {
		$latest = Episodes::getLatest();
		return $this->is($latest);
	}

	/**
	 * @param int $now Current time (for testing purposes)
	 *
	 * @return self
	 */
	public function addAiringData($now = null){
		if (!empty($this->airs)){
			if (!isset($now))
				$now = time();

			$airtime = strtotime($this->airs);
			$this->displayed = strtotime('-24 hours', $airtime) < $now;
			$this->willairts = strtotime('+'.($this->isMovie?'2 hours':((!$this->twoparter?30:60).' minutes')), $airtime);
			$this->aired = $this->willairts < $now;
			$this->willair = gmdate('c', $this->willairts);
		}

		return $this;
	}

	/**
	 * Turns an 'episode' database row into a readable title
	 *
	 * @param bool        $returnArray Whether to return as an array instead of string
	 * @param string      $arrayKey
	 * @param bool        $append_num  Append overall # to ID
	 *
	 * @return string|array
	 */
	public function formatTitle($returnArray = false, $arrayKey = null, $append_num = true){
		if ($returnArray === AS_ARRAY) {
			$arr = array(
				'id' => $this->getID(array('append_num' => $append_num)),
				'season' => $this->season ?? null,
				'episode' => $this->episode ?? null,
				'title' => isset($this->title) ? CoreUtils::escapeHTML($this->title) : null,
			);

			if (!empty($arrayKey))
				return isset($arr[$arrayKey]) ? $arr[$arrayKey] : null;
			else return $arr;
		}

		if ($this->isMovie)
			return $this->title;

		return $this->getID(array('pad' => true)).': '.$this->title;
	}

	public function toURL(){
		if (!$this->isMovie)
			return '/episode/'.$this->formatTitle(AS_ARRAY,'id');
		return "/movie/{$this->episode}".(!empty($this->title)?'-'.$this->movieSafeTitle():'');
	}

	public function formatScore(){
		if (isset($this->score))
			$this->score = number_format($this->score,1);
	}

	public function updateScore(){
		global $Database;

		$Score = $Database->whereEp($this)->disableAutoClass()->getOne('episodes__votes','AVG(vote) as score');
		$this->score = !empty($Score['score']) ? $Score['score'] : 0;
		$this->formatScore();

		$Database->whereEp($this)->update('episodes', array('score' => $this->score));
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
	static function parseID($id){
		if (empty($id))
			return null;

		global $EPISODE_ID_REGEX, $MOVIE_ID_REGEX;
		if (preg_match($EPISODE_ID_REGEX, $id, $match))
			return array(
				'season' => intval($match[1], 10),
				'episode' => intval($match[2], 10),
				'twoparter' => !empty($match[3]),
			);
		else if (preg_match($MOVIE_ID_REGEX, $id, $match))
			return array(
				'season' => 0,
				'episode' => intval($match[1], 10),
				'twoparter' => false,
			);
		else return null;
	}
}
