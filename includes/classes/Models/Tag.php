<?php

namespace App\Models;

use App\Tags;
use HtmlGenerator\HtmlTag;

/**
 * @property int          $id
 * @property int          $uses
 * @property int          $synonym_of
 * @property string       $name
 * @property string       $title
 * @property string       $type
 * @property Tag          $synonym
 * @property Appearance[] $appearances
 * @method static Tag find(...$args)
 * @method static Tag create(...$args)
 */
class Tag extends NSModel {
	public static $has_many = [
		['appearances', 'through' => 'tagged'],
		['tagged', 'class' => 'Tagged'],
	];

	public static $belongs_to = [
		['synonym', 'class' => 'Tag', 'foreign_key' => 'synonym_of'],
	];

	/**
	 * @param Appearance $appearance
	 *
	 * @return bool Indicates whether the passed appearance has this tag
	 */
	public function is_used_on(Appearance $appearance):bool {
		return Tagged::is($this, $appearance);
	}

	public function add_to(int $appearance_id):bool {
		return Tagged::make($this->id, $appearance_id)->save();
	}

	public function to_html(bool $EQG):string {
		$class = "tag id-{$this->id}";
		$tag = HtmlTag::createElement('a');
		$tag->set('href', '/cg/'.($EQG?'eqg/':'').'?q='.urlencode($this->name));
		$tag->text($this->name);
		if (!empty($this->type))
			$class .= " typ-{$this->type}";
		if (!empty($this->title))
			$tag->set('title', $this->title);
		if ($this->synonym_of !== null){
			$tag->set('data-syn-of', $this->synonym_of);
		}
		$tag->set('class', $class);
		return (string)$tag;
	}

	public function updateUses(){
		if ($this->synonym_of !== null)
			return;

		return Tags::updateUses($this->id);
	}
}
