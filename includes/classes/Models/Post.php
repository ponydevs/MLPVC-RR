<?php

namespace App\Models;

use App\Models\AbstractFillable;
use App\Models\Episode;
use App\Time;
use App\RegExp;
use App\CoreUtils;
use App\Models\User;

abstract class Post extends AbstractFillable {
	/** @var int */
	public
		$id,
		$season,
		$episode;
	/** @var string */
	public
		$preview,
		$fullsize,
		$label,
		$posted,
		$reserved_by,
		$deviation_id,
		$reserved_at,
		$finished_at,
		$type,
		$requested_by;
	/** @var bool */
	public
		$lock,
		$isFinished,
		$isRequest,
		$isReservation;
	/** @var User */
	public $Reserver;

	/**
	 * @param object       $obj
	 * @param array|object $iter
	 */
	public function __construct($obj, $iter = null){
		parent::__construct($obj, $iter);

		$this->lock = !empty($this->lock);
		$this->isFinished = !empty($this->deviation_id) && !empty($this->reserved_by);
	}

	public function getID():string {
		return ($this->isRequest ? 'request' : 'reservation').'-'.$this->id;
	}

	public function toLink(Episode &$Episode = null):string {
		if (empty($Episode))
			$Episode = new Episode($this);
		return $Episode->formatURL().'#'.$this->getID();
	}

	public function toAnchor(string $label = null, Episode $Episode = null, $newtab = false):string {
		/** @var $Episode Episode */
		$link = $this->toLink($Episode);
		if (empty($label))
			$label = $Episode->formatTitle(AS_ARRAY, 'id');
		else $label = htmlspecialchars($label);
		$target = $newtab ? ' target=\'_blank\'' : '';
		return "<a href='$link'{$target}>$label</a>";
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
		$label = preg_replace(new RegExp('(?:(f)ull[-\s](b)od(?:y|ied)(\sversion)?)','i'),'<strong class="color-darkblue">$1ull $2ody</strong>$3', $label);
		$label = preg_replace(new RegExp('(?:(f)ace[-\s](o)nly(\sversion)?)','i'),'<strong class="color-darkblue">$1ace $2nly</strong>$3', $label);
		$label = preg_replace(new RegExp('(?:(f)ull\s(s)cene?)','i'),'<strong class="color-darkblue">$1ull $2cene</strong>$3', $label);
		$label = preg_replace(new RegExp('(?:(e)ntire\s(s)cene?)','i'),'<strong class="color-darkblue">$1ntire $2cene</strong>$3', $label);
		$label = preg_replace(new RegExp('\[([\w\s]+ intensifies)\]','i'),'<span class="intensify">$1</span>', $label);
		return $label;
	}
}
