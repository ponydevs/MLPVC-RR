<?php

namespace App\Models;

use ActiveRecord\DateTime;

/**
 * @property int         $receiver_id
 * @property int         $sender_id
 * @property int         $amount
 * @property null|string $comment
 * @property DateTime    $created_at
 * @property DateTime    $updated_at
 * @property User        $receiver    (Via relations)
 * @property User        $sender      (Via relations)
 * @method static PCGPointGrant create(array $attrs)
 * @method static PCGPointGrant|PCGPointGrant[] find(...$args)
 */
class PCGPointGrant extends NSModel {
  public static $table_name = 'pcg_point_grants';

  public static $after_create = ['make_related_entries'];

  public static $belongs_to = [
    ['sender', 'class' => 'User', 'foreign_key' => 'sender_id'],
    ['receiver', 'class' => 'User', 'foreign_key' => 'receiver_id'],
  ];

  /**
   * @param int         $receiver_id
   * @param int         $sender_id
   * @param int         $amount
   * @param null|string $comment
   *
   * @return self
   */
  public static function record(int $receiver_id, int $sender_id, int $amount, ?string $comment = null):self {
    $instance = new self();
    $instance->receiver_id = $receiver_id;
    $instance->sender_id = $sender_id;
    $instance->amount = $amount;
    $instance->comment = $comment;
    $instance->save();

    return $instance;
  }

  public function make_related_entries(bool $sync = true) {
    $action = $this->amount > 0 ? 'give' : 'take';
    PCGSlotHistory::record($this->receiver_id, "manual_$action", abs($this->amount), [
      'comment' => $this->comment,
      'by' => $this->sender_id,
    ], $this->created_at);
    if ($sync)
      $this->receiver->syncPCGSlotCount();
  }
}
