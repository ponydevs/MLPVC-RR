<?php

namespace App\Models;
use ActiveRecord\DateTime;

/**
 * @property string      $receiver_id
 * @property string      $sender_id
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
		['sender', 'class' => '\App\Models\User', 'foreign_key' => 'sender_id'],
		['receiver', 'class' => '\App\Models\User', 'foreign_key' => 'receiver_id'],
	];

	/**
	 * @param string      $to
	 * @param string      $by
	 * @param int         $amount
	 * @param null|string $comment
	 *
	 * @return PCGPointGrant
	 */
	public static function grant(string $to, string $by, int $amount, ?string $comment = null){
		$instance = new self();
		$instance->receiver_id = $to;
		$instance->sender_id = $by;
		$instance->amount = $amount;
		$instance->comment = $comment;
		$instance->save();
		return $instance;
	}

	public function make_related_entries(bool $sync = true){
		$action = $this->amount > 0 ? 'give' : 'take';
		PCGSlotHistory::makeRecord($this->receiver_id, "manual_$action", abs($this->amount), [
			'comment' => $this->comment,
			'by' => $this->sender_id,
		], $this->created_at);
		if ($sync)
			$this->receiver->syncPCGSlotCount();
	}
}
