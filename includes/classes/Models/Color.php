<?php

namespace App\Models;

use ActiveRecord\Model;

/**
 * @property int        $id
 * @property int        $group_id
 * @property int        $order
 * @property string     $label
 * @property string     $hex
 * @property int        $linked_to
 * @property ColorGroup $color_group      (Via relations)
 * @property int        $appearance_id    (Via magic method)
 * @property Appearance $appearance       (Via magic method)
 * @property Color[]    $dependant_colors (Via magic method)
 * @method static Color|Color[] find(...$args)
 */
class Color extends OrderedModel {
	public static $belongs_to = [
		['color_group', 'foreign_key' => 'group_id'],
	];

	public static $after_save = ['update_dependant_colors'];

	public function get_appearance_id(){
		return $this->color_group->appearance_id;
	}

	public function get_appearance(){
		return $this->color_group->appearance;
	}

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

	public function get_dependant_colors(){
		return self::find('all', ['conditions' => ['linked_to = ?', $this->id]]);
	}

	public function update_dependant_colors(){
		foreach ($this->dependant_colors as $item){
			$item->hex = $this->hex;
			$item->save();
		}
	}
}
