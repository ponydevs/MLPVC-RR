<?php

namespace App\Models;

use App\CoreUtils;
use App\DB;
use App\JSON;
use App\Notifications;
use App\Time;
use ElephantIO\Exception\ServerConnectionFailureException;

/**
 * @property int             $id
 * @property string          $recipient_id
 * @property string          $type
 * @property string          $data
 * @property string          $sent_at
 * @property string          $read_at
 * @property string          $read_action
 * @property User            $recipient
 * @property array           $actions    (Via magic method)
 * @property string          $view_name  (Via magic method)
 * @property Post|null       $post       (Via magic method)
 * @property Appearance|null $appearance (Via magic method)
 * @method static Notification find(int $id)
 */
class Notification extends NSModel {
	public static $table_name = 'notifications';

	public static $belongs_to = [
		['recipient', 'class' => 'User'],
	];

	public function get_actions(){
		return self::ACTIONABLE_NOTIF_OPTIONS[$this->type] ?? null;
	}

	public function get_view_name(){
		return 'notifications/_type_'.str_replace('-','_',$this->type).'.html.twig';
	}

	/** @var Post|null */
	private $post = false;
	public function get_post():?Post {
		if (!CoreUtils::startsWith($this->type, 'post-'))
			return null;

		if ($this->post !== false)
			return $this->post;

		$data = JSON::decode($this->data);
		$this->post = Post::find($data['id']);
		return $this->post;
	}

	/** @var Appearance|null */
	private $appearance = false;
	public function get_appearance():?Appearance {
		if (!CoreUtils::startsWith($this->type, 'sprite-'))
			return null;

		if ($this->appearance !== false)
			return $this->appearance;

		$data = JSON::decode($this->data);
		$this->appearance = Appearance::find($data['appearance_id']);
		return $this->appearance;
	}

	public function time_tag():string {
		return Time::tag($this->sent_at);
	}

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
				'color' => 'lavender',
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
				$post_type = $data['type'] ?? 'post';

				DB::$instance->query(
					"UPDATE notifications SET read_at = NOW() WHERE recipient_id = ? && type = ? && data->>'id' = ? && data->>'type' = ?",
					[$recipient_id, $type, $data['id'], $post_type]
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

	public function safeMarkRead(?string $action = null, bool $silent = true){
		Notifications::safeMarkRead($this->id, $action, $silent);
	}
}
