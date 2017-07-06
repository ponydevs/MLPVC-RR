<?php

namespace App\Models;

use ActiveRecord\Model;
use App\Time;
use App\RegExp;
use App\CoreUtils;

/**
 * @property int $id
 * @property int $season
 * @property int $episode
 * @property string $preview
 * @property string $fullsize
 * @property string $label
 * @property string $posted
 * @property string $reserved_by
 * @property string $deviation_id
 * @property string $reserved_at
 * @property string $finished_at
 * @property bool $lock
 * @property bool $broken
 * @property bool $isFinished
 * @property bool $isRequest
 * @property bool $isReservation
 */
abstract class Post extends Model {
	static $belongs_to;

	function get_lock(){
		return $this->read_attribute('lock') !== 'false';
	}

	function get_isFinished(){
		return !empty($this->deviation_id) && !empty($this->reserved_by);
	}

	abstract function get_isRequest():bool;
	abstract function get_isReservation():bool;

	public function getID():string {
		return ($this->isRequest ? 'request' : 'reservation').'-'.$this->id;
	}

	public function toLink(Episode &$Episode = null):string {
		if (empty($Episode))
			$Episode = new Episode([
				'season' => $this->season,
				'episode' => $this->episode,
			]);
		return $Episode->toURL().'#'.$this->getID();
	}

	public function toLinkWithPreview(){
		$haslabel = !empty($this->label);
		$alt = $haslabel ? CoreUtils::escapeHTML($this->label) : 'No label';
		$slabel = $haslabel ? $this->processLabel() : "<em>$alt</em>";
		return "<a class='post-link with-preview' href='{$this->toLink()}'><img src='{$this->preview}' alt='$alt'><span>$slabel</span></a>";
	}

	public function toAnchor(string $label = null, Episode $Episode = null, $newtab = false):string {
		/** @var $Episode Episode */
		$link = $this->toLink($Episode);
		if (empty($label))
			$label = $Episode->formatTitle(AS_ARRAY, 'id');
		else $label = htmlspecialchars($label);
		$target = $newtab ? 'target="_blank"' : '';
		return "<a href='$link' {$target}>$label</a>";
	}

	public function isTransferable($now = null):bool {
		if (!isset($this->reserved_by))
			return true;
		if (!isset($now))
			$now = time();
		$ts = $this->isRequest ? $this->reserved_at : $this->posted;
		return $now - strtotime($ts) >= Time::IN_SECONDS['day']*5;
	}

	public function isOverdue($now = null):bool {
		if (!isset($now))
			$now = time();
		return $this->isRequest && empty($this->deviation_id) && isset($this->reserved_by) && $now - strtotime($this->reserved_at) >= Time::IN_SECONDS['week']*3;
	}

	public function processLabel():string {
		$label = CoreUtils::escapeHTML($this->label);
		$label = preg_replace(new RegExp("(\\w)'(\\w)"), '$1&rsquo;$2', $label);
		$label = preg_replace(new RegExp("''"), '"', $label);
		$label = preg_replace(new RegExp('"([^"]+)"'), '&ldquo;$1&rdquo;', $label);
		$label = preg_replace(new RegExp('\.\.\.'), '&hellip;', $label);
		$label = preg_replace(new RegExp('(?:(f)ull[- ](b)od(?:y|ied)( version)?)','i'),'<strong class="color-darkblue">$1ull $2ody</strong>$3', $label);
		$label = preg_replace(new RegExp('(?:(f)ace[- ](o)nly( version)?)','i'),'<strong class="color-darkblue">$1ace $2nly</strong>$3', $label);
		$label = preg_replace(new RegExp('(?:(f)ull (s)cene?)','i'),'<strong class="color-darkblue">$1ull $2cene</strong>$3', $label);
		$label = preg_replace(new RegExp('(?:(e)ntire (s)cene?)','i'),'<strong class="color-darkblue">$1ntire $2cene</strong>$3', $label);
		$label = preg_replace(new RegExp('\[([\w\s]+ intensifies)\]','i'),'<span class="intensify">$1</span>', $label);
		return $label;
	}
}
