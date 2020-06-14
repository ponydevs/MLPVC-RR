<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

/**
 * @property int        $id
 * @property int        $tag_id
 * @property int        $appearance_id
 * @property Tag        $tag
 * @property Appearance $appearance
 * @method static Tagged[] find_all_by_tag_id(int $tag_id)
 * @method static Tagged[] find_all_by_appearance_id(int $appearance_id)
 */
class Tagged extends NSModel {
  public static $table_name = 'tagged';

  public static $belongs_to = [
    ['tag'],
    ['appearance'],
  ];

  /**
   * @param int $tag_id
   *
   * @return Tagged[]
   */
  public static function by_tag(int $tag_id):array {
    return self::find_all_by_tag_id($tag_id);
  }

  /**
   * @param int $appearance_id
   *
   * @return Tagged[]
   */
  public static function by_appearance(int $appearance_id):array {
    return self::find_all_by_appearance_id($appearance_id);
  }

  /**
   * Checks if a tag and appearance are related
   * The name reads better when called (Tagged::is)
   *
   * @param Tag        $tag
   * @param Appearance $appearance
   *
   * @return bool
   */
  public static function is(Tag $tag, Appearance $appearance):bool {
    return self::exists([
      'conditions' => [
        'appearance_id = ? AND tag_id = ?',
        $appearance->id,
        $tag->id,
      ],
    ]);
  }

  /**
   * Same as is() but allows for passing an id or an array of ids to both parameters
   * TIL numbers can be cast to arrays in PHP
   *
   * @param int|int[] $tag_ids
   * @param int|int[] $appearance_ids
   *
   * @return bool
   * @throws InvalidArgumentException
   * @see is
   */
  public static function multi_is($tag_ids, $appearance_ids):bool {
    if (empty($tag_ids) || empty($appearance_ids))
      throw new InvalidArgumentException("Both parameters must be arrays of integers with at least 1 element. Got:\n\$tag_ids = ".var_export($tag_ids, true)."\n\$appearance_ids = ".var_export($appearance_ids, false));

    return self::exists([
      'conditions' => [
        'tag_id IN (?) AND appearance_id IN (?)',
        (array)$tag_ids,
        (array)$appearance_ids,
      ],
    ]);
  }

  /**
   * @param int $tag_id
   * @param int $appearance_id
   *
   * @return Tagged
   */
  public static function make(int $tag_id, int $appearance_id):Tagged {
    return new self([
      'tag_id' => $tag_id,
      'appearance_id' => $appearance_id,
    ]);
  }
}
