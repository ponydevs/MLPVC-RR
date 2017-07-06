<?php

namespace App\Models;

use ActiveRecord\Model;

/**
 * @property int $tid
 * @property int $uses
 * @property int $synonym_of
 * @property string $name
 * @property string $title
 * @property string $type
 * @property Tag $synonym
 * @property Appearance[] $appearances
 */
class Tag extends Model {
	static $primary_key = 'tid';

	static $has_many = [
		['appearances', 'through' => 'tagged', 'source' => 'tid', 'primary_key' => 'ponyid'],
	];

	static $has_one = [
		['synonym', 'class' => 'Tag', 'foreign_key' => 'synonym_of'],
	];
}
