<?php

namespace App\Controllers;

use App\Appearances;
use App\Auth;
use App\CoreUtils;
use App\Input;
use App\JSON;
use App\Logs;
use App\Models\Appearance;
use App\Models\Notification;
use App\Models\Post;
use App\Notifications;
use App\Posts;
use App\Response;
use Throwable;

class NotificationsController extends Controller {
  public function __construct() {
    parent::__construct();

    if (!Auth::$signed_in)
      Response::fail();
  }

  public function get() {
    if ($this->action !== 'GET')
      CoreUtils::notAllowed();

    try {
      $notifs = Notifications::getHTML(Notifications::get(Notifications::UNREAD_ONLY), NOWRAP);
      Response::done(['list' => $notifs]);
    }
    catch (Throwable $e){
      CoreUtils::error_log('Exception caught when fetching notifications: '.$e->getMessage()."\n".$e->getTraceAsString());
      Response::fail('An error prevented the notifications from appearing. If this persists, <a class="send-feedback">let us know</a>.');
    }
  }

  public function markRead($params) {
    if ($this->action !== 'POST')
      CoreUtils::notAllowed();

    $nid = (int)$params['id'];
    $notif = Notification::find($nid);
    if (empty($notif) || $notif->recipient_id !== Auth::$user->id)
      Response::fail("The notification (#$nid) does not exist");

    $read_action = (new Input('read_action', 'string', [
      Input::IS_OPTIONAL => true,
      Input::IN_RANGE => [null, 10],
      Input::CUSTOM_ERROR_MESSAGES => [
        Input::ERROR_INVALID => 'Action (@value) is invalid',
        Input::ERROR_RANGE => 'Action cannot be longer than @max characters',
      ],
    ]))->out();
    if (!empty($read_action)){
      if (empty(Notification::ACTIONABLE_NOTIF_OPTIONS[$notif->type][$read_action]))
        Response::fail("Invalid read action ($read_action) specified for notification type {$notif->type}");
      /** @var $data array */
      $data = !empty($notif->data) ? JSON::decode($notif->data) : null;
      switch ($notif->type){
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

            $notif->safeMarkRead($read_action);
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
            $notif->safeMarkRead($read_action);
            Notification::send($data['user'], 'post-passdeny', [
              'id' => $data['id'],
              'by' => Auth::$user->id,
            ]);
          }

          Response::done();
        break;
        case 'sprite-colors':
          $appearance = Appearance::find($data['appearance_id']);
          if (empty($appearance)){
            Appearances::clearSpriteColorIssueNotifications($data['appearance_id'], 'appdel', $notif->recipient_id);
            Response::fail("Appearance #{$data['appearance_id']} doesn't exist or has been deleted");
          }

          if ($read_action === 'recheck' && $appearance->spriteHasColorIssues())
            Response::fail("The <a href='/cg/sprite/{$appearance->id}'>sprite</a> is (still) missing some colors that are in the guide");

          Appearances::clearSpriteColorIssueNotifications($appearance->id, $read_action, $notif->recipient_id);
          if ($read_action === 'deny')
            Response::success('The notification has been cleared, but it will reappear if the sprite image or the colors are updated.');
          Response::done();
        break;
        default:
          $notif->safeMarkRead($read_action);
      }
    }
    else $notif->safeMarkRead();

    Response::done();
  }
}
