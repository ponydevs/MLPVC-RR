<?php

namespace App\Models;

/**
 * @property int        $id
 * @property int        $group_id
 * @property int        $order
 * @property string     $label
 * @property string     $hex
 * @property ColorGroup $color_group      (Via relations)
 * @property int        $appearance_id    (Via magic method)
 * @property Appearance $appearance       (Via magic method)
 * @method static Color|Color[] find(...$args)
 * @method static Color[] find_all_by_group_id(int $group_id)
 */
class Color extends OrderedModel {
  public static $table_name = 'colors';

  public static $belongs_to = [
    ['color_group', 'foreign_key' => 'group_id'],
  ];

  public function get_appearance_id() {
    return $this->color_group->appearance_id;
  }

  public function get_appearance() {
    return $this->color_group->appearance;
  }

  /** @inheritdoc */
  public function assign_order() {
    if ($this->order !== null)
      return;

    $LastColor = self::find('first', [
      'conditions' => ['group_id' => $this->group_id],
      'order' => '"order" desc',
    ]);
    $this->order = !empty($LastColor->order) ? $LastColor->order + 1 : 1;
  }

  /**
   * Make sure appearance_id is filtered somehow in the $opts array
   *
   * @inheritdoc
   */
  public static function in_order(array $opts = []) {
    self::addOrderOption($opts);

    return self::find('all', $opts);
  }
}
