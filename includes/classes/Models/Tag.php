<?php

namespace App\Models;

use ActiveRecord\Model;

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
 */
class Tag extends Model {
	public static $primary_key = 'tid';

	public static $has_many = [
		['appearances', 'through' => 'tagged'],
		['tagged', 'class' => 'Tagged'],
	];

	public static $has_one = [
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
}
