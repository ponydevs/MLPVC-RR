<?php

namespace App\Models;

use App\CoreUtils;

/**
 * @inheritdoc
 * @property int    $id
 * @property string $url
 * @property string $label
 * @property string $title
 * @property string $minrole
 * @method static UsefulLink|UsefulLink[] find(...$args)
 */
class UsefulLink extends OrderedModel {
	public function assign_order(){
		if ($this->order !== null)
			return;

		$LastLink = self::find('first',[
			'order' => '"order" desc',
		]);
		$this->order =  !empty($LastLink->order) ? $LastLink->order+1 : 1;
	}

	/**
	 * @inheritdoc
	 * @return UsefulLink[]
	 */
	public static function in_order(array $opts = []){
		self::addOrderOption($opts);
		return self::find('all', $opts);
	}

	public function getLi():string {
		if (!empty($this->title)){
			$title = str_replace("'",'&apos;',$this->title);
			$title = "title='$title'";
		}
		else $title = '';

		$href = $this->url[0] === '#' ? "class='action--".mb_substr($this->url,1)."'" : "href='".CoreUtils::aposEncode($this->url)."'";

		return "<li id='s-ufl-{$this->id}'><a $href $title>{$this->label}</a></li>";
	}
}
