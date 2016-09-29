<?php

namespace DB;
use \RegExp;
use \Episodes;
use \CoreUtils;

class Episode extends AbstractFillable {
	/** @var int */
	public
		$season,
		$episode,
		$no,
		$willairts;
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
		$aired,
		$isMovie;

	/** @param array|object */
	public function __construct($iter = null){
		parent::__construct($this, $iter);

		$this->isMovie = $this->season === 0;
		$this->twoparter = !empty($this->twoparter);
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

		if (!empty($o['pad'])){
			if ($this->twoparter)
				$episode = CoreUtils::Pad($episode).'-'.CoreUtils::Pad($episode+1);
			else $episode = CoreUtils::Pad($episode);

			return "S{$season} E{$episode}";
		}

		if ($this->twoparter)
			$episode = $episode.'-'.($episode+1);
		return "S{$season}E{$episode}";
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
		return regex_replace(new RegExp('-{2,}'), '-', regex_replace(new RegExp('[^a-z]','i'), '-', $this->title));
	}

	/**
	 * @param \DB\Episode $ep
	 *
	 * @return bool
	 */
	public function is($ep):bool {
		return $this->season === $ep->season
			&& $this->episode === $ep->episode;
	}

	public function isLatest():bool {
		$latest = Episodes::GetLatest();
		return $this->is($latest);
	}

	/**
	 * @return self
	 */
	public function addAiringData(){
		if (!empty($this->airs)){
			$airtime = strtotime($this->airs);
			$this->displayed = strtotime('-24 hours', $airtime) < time();
			$this->aired = strtotime('+'.($this->season===0?'2 hours':((!$this->twoparter?30:60).' minutes')), $airtime) < time();
			$this->willairts = strtotime('+'.(!$this->twoparter ? '30' : '60').' minutes',strtotime($this->airs));
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
				'title' => $this->title ?? null,
			);

			if (!empty($arrayKey))
				return isset($arr[$arrayKey]) ? $arr[$arrayKey] : null;
			else return $arr;
		}

		if ($this->isMovie)
			return $this->title;

		return $this->getID(array('pad' => true)).': '.$this->title;
	}

	public function formatURL(){
		if (!$this->isMovie)
			return '/episode/'.$this->formatTitle(AS_ARRAY,'id');
		return "/movie/{$this->episode}".(!empty($this->title)?'-'.$this->movieSafeTitle():'');
	}
}
