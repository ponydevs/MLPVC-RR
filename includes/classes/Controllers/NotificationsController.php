<?php

namespace App\Controllers;
use App\CSRFProtection;
use App\Input;
use App\JSON;
use App\Logs;
use App\Notifications;
use App\Posts;
use App\Response;
use App\Models\Post;

class NotificationsController extends Controller {
	public $do = 'notifications';

	function __construct(){
		parent::__construct();

		global $signedIn;
		if (!$signedIn)
			Response::fail();
		CSRFProtection::protect();
	}

	function get(){
		try {
			$Notifications = Notifications::getHTML(Notifications::get(null,Notifications::UNREAD_ONLY),NOWRAP);
			Response::done(array('list' => $Notifications));
		}
		catch (\Throwable $e){
			error_log('Exception caught when fetching notifications: '.$e->getMessage()."\n".$e->getTraceAsString());
			Response::fail('An error prevented the notifications from appearing. If this persists, <a class="send-feedback">let us know</a>.');
		}
	}

	function markRead($params){
		global $Database, $currentUser;

		$nid = intval($params['id'], 10);
		$Notif = $Database->where('id', $nid)->where('user', $currentUser->id)->getOne('notifications');
		if (empty($Notif))
			Response::fail("The notification (#$nid) does not exist");

		$read_action = (new Input('read_action','string',array(
			Input::IS_OPTIONAL => true,
			Input::IN_RANGE => [null,10],
			Input::CUSTOM_ERROR_MESSAGES => array(
				Input::ERROR_INVALID => 'Action (@value) is invalid',
				Input::ERROR_RANGE => 'Action cannot be longer than @max characters',
			)
		)))->out();
		if (!empty($read_action)){
			if (empty(Notifications::$ACTIONABLE_NOTIF_OPTIONS[$Notif['type']][$read_action]))
				Response::fail("Invalid read action ($read_action) specified for notification type {$Notif['type']}");
			/** @var $data array */
			$data = !empty($Notif['data']) ? JSON::decode($Notif['data']) : null;
			switch ($Notif['type']){
				case "post-passon":
					/** @var $Post Post */
					$Post = $Database->where('id', $data['id'])->getOne("{$data['type']}s");
					if (empty($Post)){
						Posts::clearTransferAttempts($Post, $data['type'], 'del');
						Response::fail("The {$data['type']} doesn't exist or has been deleted");
					}
					if ($read_action === 'true'){
						if ($Post['reserved_by'] !== $currentUser->id){
							Posts::clearTransferAttempts($Post, $data['type'], 'perm', null, $currentUser->id);
							Response::fail('You are not allowed to transfer this reservation');
						}

						Notifications::safeMarkRead($Notif['id'], $read_action);
						Notifications::send($data['user'], "post-passallow", array(
							'id' => $data['id'],
							'type' => $data['type'],
							'by' => $currentUser->id,
						));
						$Database->where('id', $data['id'])->update("{$data['type']}s",array(
							'reserved_by' => $data['user'],
							'reserved_at' => date('c'),
						));

						Posts::clearTransferAttempts($Post, $data['type'], 'deny');

						Logs::logAction('res_transfer',array(
							'id' => $data['id'],
							'type' => $data['type'],
							'to' => $data['user'],
						));
					}
					else {
						Notifications::safeMarkRead($Notif['id'], $read_action);
						Notifications::send($data['user'], "post-passdeny", array(
							'id' => $data['id'],
							'type' => $data['type'],
							'by' => $currentUser->id,
						));
					}

					Response::done();
				break;
				default:
					Notifications::safeMarkRead($Notif['id'], $read_action);
			}
		}
		else Notifications::safeMarkRead($Notif['id']);

		Response::done();
	}
}
