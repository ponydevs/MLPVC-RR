<?php

namespace App\Models;

use ActiveRecord\Model;
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
 */
class Notification extends Model {
	public static $belongs_to = [
		['recipient', 'class' => 'User'],
	];
	public static $ACTIONABLE_NOTIF_OPTIONS = [
		'post-passon' => [
			'true' => [
				'label' => 'Allow',
				'icon' => 'tick',
				'color' => 'green',
			],
			'false' => [
				'label' => 'Deny',
				'icon' => 'times',
				'color' => 'red',
			],
		]
	];
	public static $_notifTypes = [
		'post-finished' => true,
		'post-approved' => true,
		'post-passon' => true,
		'post-passdeny' => true,
		'post-passallow' => true,
		'post-passfree' => true,
		'post-passdel' => true,
		'post-passsnatch' => true,
		'post-passperm' => true,
	];

	public static function send($to, $type, $data){
		if (empty(self::$_notifTypes[$type]))
			throw new \Exception("Invalid notification type: $type");

		switch ($type) {
			case 'post-finished':
			case 'post-approved':
				DB::$instance->query(
					"UPDATE notifications SET read_at = NOW() WHERE recipient_id = ? && type = ? && data->>'id' = ? && data->>'type' = ?",
					[$to, $type, $data['id'], $data['type']]
				);
			break;
		}

		self::create([
			'recipient_id' => $to,
			'type' => $type,
			'data' => JSON::encode($data),
		]);

		try {
			CoreUtils::socketEvent('notify-pls', ['user' => $to]);
		}
		catch (ServerConnectionFailureException $e){
			error_log("Error while notifying $to with type $type (data:".JSON::encode($data).")\nError message: {$e->getMessage()}");

			return 'Notification server is down! Please <a class="send-feedback">let us know</a>.';
		}

		return 0;
	}
}
