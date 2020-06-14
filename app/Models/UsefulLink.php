<?php

namespace App\Models;

/**
 * @inheritDoc
 * @property int    $id
 * @property string $url
 * @property string $label
 * @property string $title
 * @property string $minrole
 * @method static UsefulLink|UsefulLink[] find(...$args)
 */
class UsefulLink extends OrderedModel {
  public function assign_order() {
    if ($this->order !== null)
      return;

    $LastLink = self::find('first', [
      'order' => '"order" desc',
    ]);
    $this->order = !empty($LastLink->order) ? $LastLink->order + 1 : 1;
  }

  /**
   * @inheritdoc
   * @return UsefulLink[]
   */
  public static function in_order(array $opts = []) {
    self::addOrderOption($opts);

    return self::find('all', $opts);
  }
}
