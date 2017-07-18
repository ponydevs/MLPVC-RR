<?php

namespace App\Models;

use ActiveRecord\Model;

/**
 * @property int $group_id
 * @property int $order
 * @property string $label
 * @property string $hex
 * @property ColorGroup $color_group
 * @method static Color|Color[] find(...$args)
 */
class Color extends OrderedModel {
	public static $primary_key = ['group_id', 'order'];

	public static $belongs_to = [
		['color_group', 'foreign_key' => 'grooup_id']
	];

	/** @inheritdoc */
	public function assign_order(){
		if ($this->order !== null)
			return;

		$LastColor = self::find('first',[
			'conditions' => [ 'group_id' => $this->group_id ],
			'order' => '"order" desc',
		]);
		$this->order = !empty($LastColor->order) ? $LastColor->order+1 : 1;
	}

	/**
	 * Make sure appearance_id is filtered somehow in the $opts array
	 *
	 * @inheritdoc
	 */
	 public static function in_order(array $opts = []){
		self::addOrderOption($opts);
		return self::find('all', $opts);
	 }
}
