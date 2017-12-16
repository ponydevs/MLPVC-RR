<?php

namespace App\Models;
use ActiveRecord\DateTime;
use App\Auth;

/**
 * @property int           $id
 * @property string        $sender_id
 * @property string        $receiver_id
 * @property int           $amount
 * @property bool          $claimed
 * @property bool          $rejected
 * @property string|null   $refunded_by
 * @property DateTime      $created_at
 * @property DateTime|null $updated_at
 * @property User          $sender
 * @property User          $receiver
 * @property User          $refunder
 * @method static PCGSlotGift create(array $data)
 * @method static PCGSlotGift find(int $id)
 */
class PCGSlotGift extends NSModel {
	public static $table_name = 'pcg_slot_gifts';

	public static $after_create = ['make_related_entries'];

	public static $belongs_to = [
		['sender', 'class' => '\App\Models\User', 'foreign_key' => 'sender_id'],
		['receiver', 'class' => '\App\Models\User', 'foreign_key' => 'receiver_id'],
		['refunder', 'class' => '\App\Models\User', 'foreign_key' => 'refunded_by']
	];

	public static function send(string $from, string $to, int $amount):PCGSlotGift {
		return self::create([
			'sender_id' => $from,
			'receiver_id' => $to,
			'amount' => $amount,
		]);
	}

	public function make_related_entries(){
		// Deduct slots from sender immediately
		PCGSlotHistory::makeRecord($this->sender_id, 'gift_sent', $this->amount*10, [ 'gift_id' => $this->id ]);
		Auth::$user->syncPCGSlotCount();

		// Send notification
		Notification::send($this->receiver_id, 'pcg-slot-gift', [ 'gift_id' => (int)$this->id ]);
	}
}
