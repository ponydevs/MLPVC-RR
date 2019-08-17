<?php

namespace App;

use App\Models\Notification;
use ElephantIO\Exception\ServerConnectionFailureException;

class Notifications {
  public const
    ALL = 0,
    UNREAD_ONLY = 1,
    READ_ONLY = 2;

  /**
   * Gets a list of notifications for the current user
   *
   * @param int $only Expects self::UNREAD_ONLY or self::READ_ONLY
   *
   * @return Notification[]
   */
  public static function get($only = self::ALL) {
    if (!Auth::$signed_in)
      return null;
    $UserID = Auth::$user->id;

    switch ($only){
      case self::UNREAD_ONLY:
        DB::$instance->where('read_at IS NULL');
      break;
      case self::READ_ONLY:
        DB::$instance->where('read_at IS NOT NULL');
      break;
    }

    return DB::$instance->where('recipient_id', $UserID)->get('notifications');
  }

  /**
   * @param Notification[] $Notifications
   * @param bool           $wrap
   *
   * @return string
   */
  public static function getHTML($Notifications, bool $wrap = WRAP):string {
    return Twig::$env->render('notifications/_list.html.twig', [
      'notifications' => $Notifications,
      'wrap' => $wrap,
    ]);
  }

  public static function markRead(int $nid, ?string $action = null) {
    CoreUtils::socketEvent('mark-read', ['nid' => $nid, 'action' => $action]);
  }

  public static function safeMarkRead(int $NotifID, ?string $action = null, bool $silent = false) {
    try {
      self::markRead($NotifID, $action);
    }
    catch (ServerConnectionFailureException $e){
      CoreUtils::error_log("Notification server down!\n".$e->getMessage()."\n".$e->getTraceAsString());

      // Attempt to mark as read if exists since users won't get a live update anyway if the server is down
      $notif = Notification::find($NotifID);
      if (!empty($notif)){
        $notif->read_at = date('c');
        $notif->save();
      }

      if (!$silent)
        Response::fail('Notification server is down! Please <a class="send-feedback">let us know</a>.');
    }
    catch (\Exception $e){
      CoreUtils::error_log("SocketEvent Error\n".$e->getMessage()."\n".$e->getTraceAsString());
      if (!$silent)
        Response::fail('SocketEvent Error: '.$e->getMessage());
    }
  }
}
