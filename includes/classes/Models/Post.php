<?php

namespace App\Models;

use ActiveRecord\Model;
use ActiveRecord\DateTime;
use App\Time;
use App\RegExp;
use App\CoreUtils;

/**
 * @property int      $id
 * @property int      $season
 * @property int      $episode
 * @property string   $preview
 * @property string   $fullsize
 * @property string   $label
 * @property string   $reserved_by
 * @property string   $deviation_id
 * @property DateTime $reserved_at
 * @property DateTime $finished_at
 * @property bool     $broken
 * @property DateTime $posted         (Via alias)
 * @property User     $reserver       (Via child relations)
 * @property Episode  $ep             (Via child relations)
 * @property bool     $lock           (Via magic method)
 * @property string   $kind           (Via magic method)
 * @property bool     $finished       (Via magic method)
 * @property bool     $is_request     (Via magic method)
 * @property bool     $is_reservation (Via magic method)
 */
abstract class Post extends Model {
	public static $belongs_to;

	/**
	 * Must link $posted to the timestamp associated with the creation of the post
	 */
	public static $alias_attribute;

	public function get_lock(){
		return $this->read_attribute('lock') !== 'false';
	}

	public function get_finished(){
		return $this->deviation_id !== null && $this->reserved_by !== null;
	}

	abstract public function get_is_request():bool;
	abstract public function get_is_reservation():bool;

	public function get_kind(){
		return $this->is_request ? 'request' : 'reservation';
	}

	public function getID():string {
		return $this->kind.'-'.$this->id;
	}

	public function toLink(Episode $Episode = null):string {
		if (empty($Episode))
			$Episode = $this->ep;
		return $Episode->toURL().'#'.$this->getID();
	}

	public function toLinkWithPreview(){
		$haslabel = !empty($this->label);
		$alt = $haslabel ? CoreUtils::escapeHTML($this->label) : 'No label';
		$slabel = $haslabel ? $this->processLabel() : "<em>$alt</em>";
		return "<a class='post-link with-preview' href='{$this->toLink()}'><img src='{$this->preview}' alt='$alt'><span>$slabel</span></a>";
	}

	public function toAnchor(string $label = null, Episode $Episode = null, $newtab = false):string {
		if ($Episode === null)
			$Episode = Episode::find_by_season_and_episode($this->season, $this->episode);
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
		return $now - strtotime($this->posted) >= Time::IN_SECONDS['day']*5;
	}

	/**
	 * A post is overdue when it has been reserved and left unfinished for over 3 weeks
	 *
	 * @param int|null $now
	 *
	 * @return bool
	 */
	public function isOverdue($now = null):bool {
		if ($now === null)
			$now = time();
		return $this->is_request && $this->deviation_id !== null && $this->reserved_by !== null && $now - $this->reserved_at->getTimestamp() > Time::IN_SECONDS['week']*3;
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
