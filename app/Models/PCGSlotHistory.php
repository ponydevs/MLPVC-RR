<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\DB;
use App\JSON;
use RuntimeException;
use function is_int;

/**
 * @property int      $id
 * @property int      $user_id
 * @property string   $change_type
 * @property string   $change_data
 * @property float    $change_amount
 * @property DateTime $created_at
 * @property User     $user          (Via relations)
 */
class PCGSlotHistory extends NSModel {
  public static $table_name = 'pcg_slot_history';

  // true = +, false = -
  public const VALID_CHANGE_TYPES = [
    'post_approved' => true,
    'post_unapproved' => false,
    'staff_member' => true, // Assigned on initial import to avoid showing a "joined" entry out of nowhere
    'staff_join' => true,
    'staff_leave' => false,
    'appearance_del' => true,
    'appearance_add' => false,
    'free_trial' => true,
    'manual_give' => true,
    'manual_take' => false,
  ];

  public const CHANGE_DESC = [
    'post_approved' => 'Post approved',
    'post_unapproved' => 'Post un-approved',
    'staff_member' => 'Being staff',
    'staff_join' => 'Joined staff',
    'staff_leave' => 'Left staff',
    'appearance_del' => 'Appearance deleted',
    'appearance_add' => 'Appearance created',
    'free_trial' => 'Free slot',
    'manual_give' => 'Manually given',
    'manual_take' => 'Manually taken',
  ];

  public const DEFAULT_CHANGE = [
    'post' => 1,
    'staff' => 10,
    'appearance' => 10,
    'free' => 10,
  ];

  public static $belongs_to = [
    ['user'],
  ];

  /**
   * @param string            $user_id
   * @param string            $change_type
   * @param int|null          $change_amount
   * @param array|null        $change_data
   * @param int|DateTime|null $created
   *
   * @return self
   */
  public static function record(string $user_id, string $change_type, ?int $change_amount = null, ?array $change_data = null, $created = null):self {
    $entry = new self();
    $entry->user_id = $user_id;

    if (!isset(self::VALID_CHANGE_TYPES[$change_type]))
      throw new RuntimeException("Invalid change type: $change_type");

    if ($change_amount !== null)
      $entry->change_amount = $change_amount;
    else {
      $key = strtok($change_type, '_');
      if (!isset(self::DEFAULT_CHANGE[$key]))
        throw new RuntimeException("No default change amount specified for type: $change_type");
      $entry->change_amount = self::DEFAULT_CHANGE[$key];
      if ($key === 'post' && $change_data !== null)
        $change_data['type'] = 'post';
    }
    if (self::VALID_CHANGE_TYPES[$change_type] === false)
      $entry->change_amount *= -1;

    $entry->change_type = $change_type;
    if ($change_data !== null)
      $entry->change_data = JSON::encode($change_data);

    if ($created !== null){
      $entry->created_at = is_int($created) ? date('c', $created) : $created;
    }

    $entry->save();

    return $entry;
  }

  public static function sum(string $user_id):float {
    $data = DB::$instance->disableAutoClass()->where('user_id', $user_id)->getOne(self::$table_name, 'SUM(change_amount) as slots');

    return (float)($data['slots'] ?? 0);
  }
}
