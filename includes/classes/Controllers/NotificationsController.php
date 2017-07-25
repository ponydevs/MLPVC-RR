<?php

namespace App\Controllers;

use App\Appearances;
use App\Auth;
use App\CSRFProtection;
use App\DB;
use App\Input;
use App\JSON;
use App\Logs;
use App\Models\Appearance;
use App\Models\Notification;
use App\Notifications;
use App\Posts;
use App\Response;
use App\Models\Post;

class NotificationsController extends Controller {
	public $do = 'notifications';

	public function __construct(){
		parent::__construct();

		if (!Auth::$signed_in)
			Response::fail();
		CSRFProtection::protect();
	}

	public function get(){
		try {
			$Notifications = Notifications::getHTML(Notifications::get(Notifications::UNREAD_ONLY),NOWRAP);
			Response::done(['list' => $Notifications]);
		}
		catch (\Throwable $e){
			error_log('Exception caught when fetching notifications: '.$e->getMessage()."\n".$e->getTraceAsString());
			Response::fail('An error prevented the notifications from appearing. If this persists, <a class="send-feedback">let us know</a>.');
		}
	}

	public function markRead($params){
		$nid = intval($params['id'], 10);
		/** @var $Notif Notification */
		$Notif = Notification::find($nid);
		if (empty($Notif) || $Notif->recipient_id !== Auth::$user->id)
			Response::fail("The notification (#$nid) does not exist");

		$read_action = (new Input('read_action','string', [
			Input::IS_OPTIONAL => true,
			Input::IN_RANGE => [null,10],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_INVALID => 'Action (@value) is invalid',
				Input::ERROR_RANGE => 'Action cannot be longer than @max characters',
			]
		]))->out();
		if (!empty($read_action)){
			if (empty(Notification::$ACTIONABLE_NOTIF_OPTIONS[$Notif->type][$read_action]))
				Response::fail("Invalid read action ($read_action) specified for notification type {$Notif->type}");
			/** @var $data array */
			$data = !empty($Notif->data) ? JSON::decode($Notif->data) : null;
			/** @noinspection DegradedSwitchInspection */
			switch ($Notif->type){
				case 'post-passon':
					/** @var $Post Post */
					$Post = DB::$instance->where('id', $data['id'])->getOne("{$data['type']}s");
					if (empty($Post)){
						Posts::clearTransferAttempts($Post, $data['type'], 'del');
						Response::fail("The {$data['type']} doesn’t exist or has been deleted");
					}
					if ($read_action === 'true'){
						if ($Post->reserved_by !== Auth::$user->id){
							Posts::clearTransferAttempts($Post, 'perm', Auth::$user->id);
							Response::fail('You are not allowed to transfer this reservation');
						}

						Notifications::safeMarkRead($Notif->id, $read_action);
						Notification::send($data['user'], 'post-passallow', [
							'id' => $data['id'],
							'type' => $data['type'],
							'by' => Auth::$user->id,
						]);
						DB::$instance->where('id', $data['id'])->update("{$data['type']}s", [
							'reserved_by' => $data['user'],
							'reserved_at' => date('c'),
						]);

						Posts::clearTransferAttempts($Post, $data['type'], 'deny');

						Logs::logAction('res_transfer', [
							'id' => $data['id'],
							'type' => $data['type'],
							'to' => $data['user'],
						]);
					}
					else {
						Notifications::safeMarkRead($Notif->id, $read_action);
						Notification::send($data['user'], 'post-passdeny', [
							'id' => $data['id'],
							'type' => $data['type'],
							'by' => Auth::$user->id,
						]);
					}

					Response::done();
				break;
				case 'sprite-colors':
					$Appearance = Appearance::find($data['appearance_id']);
					if (empty($Appearance)){
						Appearances::clearSpriteColorIssueNotifications($data['appearance_id'], 'appdel', $Notif->recipient_id);
						Response::fail("Appearance #{$data['appearance_id']} doesn’t exist or has been deleted");
					}

					if ($read_action === 'recheck' && $Appearance->spriteHasColorIssues())
						Response::fail("The <a href='/cg/sprite/{$Appearance->id}'>sprite</a> still has color issues");

					Appearances::clearSpriteColorIssueNotifications($Appearance->id, $read_action, $Notif->recipient_id);
					if ($read_action === 'deny')
						Response::success('The notification has been cleared, but it will reappear if the sprite image or the colors are updated.');
					Response::done();
				break;
				default:
					Notifications::safeMarkRead($Notif->id, $read_action);
			}
		}
		else Notifications::safeMarkRead($Notif->id);

		Response::done();
	}
}
