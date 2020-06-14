<?php

namespace App\Models;

/**
 * @property int        $id
 * @property int        $show_id
 * @property int        $appearance_id
 * @property Show       $show          (Via relations)
 * @property Appearance $appearance    (Via relations)
 * @method static self find_by_show_id_and_appearance_id(int $show_id, int $appearance_id)
 */
class ShowAppearance extends NSModel {
  public static $table_name = 'show_appearances';

  public static $belongs_to = [
    ['show'],
    ['appearance'],
  ];

  public static function makeRelation(int $show_id, int $appearance_id):void {
    $relation = new self();
    $relation->show_id = $show_id;
    $relation->appearance_id = $appearance_id;
    $relation->save();
  }
}
