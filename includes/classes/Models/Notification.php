<?php

namespace App\Models;

use App\CoreUtils;
use App\DB;
use App\JSON;
use ElephantIO\Exception\ServerConnectionFailureException;

/**
 * @property int    $id
 * @property string $recipient_id
 * @property string $type
 * @property string $data
 * @property string $sent_at
 * @property string $read_at
 * @property string $read_action
 * @property User   $recipient
 * @method static Notification find(int $id)
 */
class Notification extends NSModel {
	public static $table_name = 'notifications';

	public static $belongs_to = [
		['recipient', 'class' => 'User'],
	];
	public const ACTIONABLE_NOTIF_OPTIONS = [
		'post-passon' => [
			'true' => [
				'label' => 'Accept',
				'icon' => 'tick',
				'color' => 'green',
				'action' => 'Accept transfer offer',
			],
			'false' => [
				'label' => 'Deny',
				'icon' => 'times',
				'color' => 'red',
				'action' => 'Deny transfer offer',
			],
		],
		'sprite-colors' => [
			'recheck' => [
				'label' => 'Recheck',
				'icon' => 'refresh',
				'color' => 'lavander',
				'confirm' => false,
				'action' => 'Recheck sprite colors',
			],
			'deny' => [
				'label' => 'Ignore',
				'icon' => 'times',
				'color' => 'orange',
				'action' => 'Ignore color issues',
			],
		],
		'pcg-slot-gift' => [
			'accept' => [
				'label' => 'Accept',
				'icon' => 'tick',
				'color' => 'green',
				'confirm' => true,
				'action' => 'Accept gift',
			],
			'reject' => [
				'label' => 'Reject',
				'icon' => 'cancel',
				'color' => 'red',
				'confirm' => true,
				'action' => 'Reject gift',
			],
		],
	];
	public const NOTIF_TYPES = [
	    #---------------# (max length)
		'post-finished'   => true,
		'post-approved'   => true,
		'post-passon'     => true,
		'post-passdeny'   => true,
		'post-passallow'  => true,
		'post-passfree'   => true,
		'post-passdel'    => true,
		'post-passsnatch' => true,
		'post-passperm'   => true,
		'sprite-colors'   => true,
		'pcg-slot-gift'   => true,
		'pcg-slot-accept' => true,
		'pcg-slot-reject' => true,
		'pcg-slot-refund' => true,
	];

	public static function send(string $recipient_id, string $type, $data){
		if (empty(self::NOTIF_TYPES[$type]))
			throw new \RuntimeException("Invalid notification type: $type");

		switch ($type) {
			case 'post-finished':
			case 'post-approved':
				DB::$instance->query(
					"UPDATE notifications SET read_at = NOW() WHERE recipient_id = ? && type = ? && data->>'id' = ? && data->>'type' = ?",
					[$recipient_id, $type, $data['id'], $data['type']]
				);
			break;
		}

		self::create([
			'recipient_id' => $recipient_id,
			'type' => $type,
			'data' => JSON::encode($data),
		]);

		try {
			CoreUtils::socketEvent('notify-pls', ['user' => $recipient_id]);
		}
		catch (ServerConnectionFailureException $e){
			CoreUtils::error_log("Error while notifying $recipient_id with type $type (data:".JSON::encode($data).")\nError message: {$e->getMessage()}\nTrace:\n{$e->getTraceAsString()}");

			return 'Notification server is down! Please <a class="send-feedback">let us know</a>.';
		}

		return 0;
	}
}
