<?php

namespace App\Models;

/** @property int $order */
abstract class OrderedModel extends NSModel {
  public static $before_create = ['assign_order'];

  /** This method should attempt to get the order number of last item, or default to 1 */
  abstract public function assign_order();

  private const ORDERING_STR = '"order" asc';

  /**
   * Adds the 'order' key to the specified options array or prepends to the existing key
   *
   * @param array $opts
   */
  protected static function addOrderOption(array &$opts = []) {
    if (!empty($opts['order']))
      $opts['order'] = self::ORDERING_STR.', '.$opts['order'];
    else $opts['order'] = self::ORDERING_STR;
  }

  /**
   * This method must return instances of the class in the specified order
   *
   * @param array $opts
   */
  abstract public static function in_order(array $opts = []);
}
