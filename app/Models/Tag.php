<?php

namespace App\Models;

use App\Tags;
use App\Twig;
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
	/** For Twig */
	public function getSynonym():Tag {
		return $this->synonym;
	}

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

	public function getHTML(bool $EQG):string {
		return Twig::$env->render('appearances/_tag.html.twig', [
			'tag' => $this,
			'eqg' => $EQG,
		]);
	}

	public function getSearchUrl(bool $eqg):string {
		return '/cg/'.($eqg?'eqg':'pony').'?q='.urlencode($this->name);
	}

	public function updateUses(){
		if ($this->synonym_of !== null)
			return;

		return Tags::updateUses($this->id);
	}
}
