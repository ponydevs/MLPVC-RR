<?php

namespace App\Models;

use ActiveRecord\DateTime;

/**
 * @property int        $id
 * @property int        $appearance_id
 * @property string     $guide
 * @property DateTime   $created_at
 * @property DateTime   $updated_at
 * @property Appearance $appearance    (Via relations)
 * @method static PinnedAppearance find(...$args)
 * @method static PinnedAppearance find_by_appearance_id(int $appearance_id)
 */
class PinnedAppearance extends NSModel {
  public static $table_name = 'pinned_appearances';

  public static $belongs_to = [
    ['appearance'],
  ];

  public static $after_create = ['reindexAppearance'];
  public static $after_destroy = ['reindexAppearance'];

  /**
   * Checks if a specific appearance is pinned
   *
   * @param int $appearance_id
   *
   * @return bool
   */
  public static function existsForAppearance(int $appearance_id):bool {
    return self::exists(['appearance_id' => $appearance_id]);
  }

  /**
   * Returns an array of all pinned appearance ids
   *
   * @return int[]
   */
  public static function getAllIds():array {
    return array_map(static fn(PinnedAppearance $a) => $a->appearance_id, self::all(['order' => '"order" asc']));
  }

  /**
   * Returns an array of all pinned appearance ids
   *
   * @return Appearance[]
   */
  public static function getGuideAppearances(string $guide):array {
    return array_map(static fn(PinnedAppearance $a) => $a->appearance, self::all([
      'conditions' => ['guide' => $guide],
      'order' => '"order" asc',
    ]));
  }

  public function reindexAppearance() {
    $this->appearance->updateIndex();
  }
}
