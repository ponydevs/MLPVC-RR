<?php

namespace App\Models;

/**
 * @property int        $source_id
 * @property int        $target_id
 * @property bool       $is_mutual
 * @property Appearance $source    (Via relations)
 * @property Appearance $target    (Via relations)
 */
class RelatedAppearance extends NSModel {
  public static $primary_key = ['source_id', 'target_id'];

  public static $belongs_to = [
    ['source', 'class' => 'Appearance', 'foreign_key' => 'source_id'],
    ['target', 'class' => 'Appearance', 'foreign_key' => 'target_id'],
  ];

  /**
   * For Twig
   *
   * @return Appearance
   */
  public function getTarget():Appearance {
    return $this->target;
  }

  private function _mutualArray():array {
    return ['source_id' => $this->target_id, 'target_id' => $this->source_id];
  }

  /**
   * For a relation to be mutual, a separate entry should exist with the IDs reversed
   *
   * @return bool
   */
  public function get_is_mutual():bool {
    return self::exists($this->_mutualArray());
  }

  public static function make(int $source, int $target, bool $mutual = false) {
    self::create([
      'source_id' => $source,
      'target_id' => $target,
    ]);
    if ($mutual)
      self::create([
        'source_id' => $target,
        'target_id' => $source,
      ]);
  }
}
