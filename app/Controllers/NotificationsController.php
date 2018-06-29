<?php

namespace App\Controllers;

use App\Appearances;
use App\Auth;
use App\CoreUtils;
use App\CSRFProtection;
use App\DB;
use App\Input;
use App\JSON;
use App\Logs;
use App\Models\Appearance;
use App\Models\Notification;
use App\Models\PCGSlotGift;
use App\Models\PCGSlotHistory;
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
	}

	public function get(){
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		try {
			$Notifications = Notifications::getHTML(Notifications::get(Notifications::UNREAD_ONLY),NOWRAP);
			Response::done(['list' => $Notifications]);
		}
		catch (\Throwable $e){
			CoreUtils::error_log('Exception caught when fetching notifications: '.$e->getMessage()."\n".$e->getTraceAsString());
			Response::fail('An error prevented the notifications from appearing. If this persists, <a class="send-feedback">let us know</a>.');
		}
	}

	public function markRead($params){
		if ($this->action !== 'POST')
			CoreUtils::notAllowed();

		$nid = \intval($params['id'], 10);
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
			if (empty(Notification::ACTIONABLE_NOTIF_OPTIONS[$Notif->type][$read_action]))
				Response::fail("Invalid read action ($read_action) specified for notification type {$Notif->type}");
			/** @var $data array */
			$data = !empty($Notif->data) ? JSON::decode($Notif->data) : null;
			/** @noinspection DegradedSwitchInspection */
			switch ($Notif->type){
				case 'post-passon':
					/** @var $post Post */
					$post = Post::find($data['id']);
					if (empty($post)){
						$post = new Post([
							'id' => $data['id'],
						]);
						Posts::clearTransferAttempts($post, 'del');
						Response::fail("The post doesn't exist or has been deleted");
					}
					if ($read_action === 'true'){
						if ($post->reserved_by !== Auth::$user->id){
							Posts::clearTransferAttempts($post, 'perm', Auth::$user);
							Response::fail('You are not allowed to transfer this reservation');
						}

						$Notif->safeMarkRead($read_action);
						Notification::send($data['user'], 'post-passallow', [
							'id' => $data['id'],
							'by' => Auth::$user->id,
						]);
						$post->reserved_by = $data['user'];
						$post->reserved_at = date('c');
						$post->save();

						Posts::clearTransferAttempts($post, 'deny');

						Logs::logAction('res_transfer', [
							'id' => $data['id'],
							'to' => $data['user'],
						]);
					}
					else {
						$Notif->safeMarkRead($read_action);
						Notification::send($data['user'], 'post-passdeny', [
							'id' => $data['id'],
							'by' => Auth::$user->id,
						]);
					}

					Response::done();
				break;
				case 'sprite-colors':
					$Appearance = Appearance::find($data['appearance_id']);
					if (empty($Appearance)){
						Appearances::clearSpriteColorIssueNotifications($data['appearance_id'], 'appdel', $Notif->recipient_id);
						Response::fail("Appearance #{$data['appearance_id']} doesn't exist or has been deleted");
					}

					if ($read_action === 'recheck' && $Appearance->spriteHasColorIssues())
						Response::fail("The <a href='/cg/sprite/{$Appearance->id}'>sprite</a> is (still) missing some colors that are in the guide");

					Appearances::clearSpriteColorIssueNotifications($Appearance->id, $read_action, $Notif->recipient_id);
					if ($read_action === 'deny')
						Response::success('The notification has been cleared, but it will reappear if the sprite image or the colors are updated.');
					Response::done();
				break;
				case 'pcg-slot-gift':
					$gift = PCGSlotGift::find($data['gift_id']);
					if (empty($gift))
						Response::fail('The specified gift does not exist. If you believe this is an error, please <a class="send-feedback">let us know</a>.');
					if ($gift->receiver_id !== Auth::$user->id)
						Response::fail('Only the recipient can accept or reject this gift.');
					$giftArr = [ 'gift_id' => $gift->id ];
					if ($read_action === 'reject'){
						PCGSlotHistory::record($gift->sender_id, 'gift_rejected', $gift->amount, $giftArr);
						$gift->sender->syncPCGSlotCount();
						$gift->rejected = true;
						$gift->save();
						Notification::send($gift->sender_id, 'pcg-slot-reject', $giftArr);


						$Notif->safeMarkRead($read_action);
						Response::done();
					}
					else {
						PCGSlotHistory::record($gift->receiver_id, 'gift_accepted', $gift->amount, $giftArr);
						$gift->receiver->syncPCGSlotCount();
						$gift->claimed = true;
						$gift->save();

						Notification::send($gift->sender_id, 'pcg-slot-accept', $giftArr);

						$Notif->safeMarkRead($read_action);
						Response::success('You now have '.CoreUtils::makePlural('available slot', floor($gift->receiver->getPCGAvailablePoints(false)/10), PREPEND_NUMBER).". If you want to create an appearance you can <a href='{$gift->receiver->toURL()}/cg'>click here</a> to go directly to your personal color guide.");
					}
				break;
				default:
					$Notif->safeMarkRead($read_action);
			}
		}
		else $Notif->safeMarkRead();

		Response::done();
	}
}
