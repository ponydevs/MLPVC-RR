<?php

namespace App;

use ActiveRecord\RecordNotFound;
use App\CoreUtils;
use App\Models\Appearance;
use App\Models\Notification;
use App\Models\Post;
use ElephantIO\Exception\ServerConnectionFailureException;

class Notifications {
	const
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
	public static function get($only = self::ALL){
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
	 * @throws \InvalidArgumentException
	 */
	public static function getHTML($Notifications, bool $wrap = WRAP):string {
		$HTML = '';

		foreach ($Notifications as $n){
			$data = !empty($n->data) ? JSON::decode($n->data) : null;
			if (preg_match(new RegExp('^post-'),$n->type)){
				$_postClass = '\App\Models\\'.CoreUtils::capitalize($data['type']);
				try {
					/** @var $Post Post */
					/** @noinspection PhpUndefinedMethodInspection */
					$Post = $_postClass::find($data['id']);
					$Episode = $Post->ep;
					$EpID = $Episode->getID();
					$url = $Post->toURL($Episode);
				}
				catch (RecordNotFound $e){
					$Episode = null;
					$EpID = null;
					$url = null;
				}
			}
			switch ($n->type){
				case 'post-finished':
					$HTML .= self::_getNotifElem("Your <a href='$url'>request</a> under $EpID has been fulfilled", $n);
				break;
				case 'post-approved':
					$HTML .= self::_getNotifElem("A <a href='$url'>post</a> you reserved under $EpID has been added to the club gallery", $n);
				break;
				case 'post-passon':
					$userlink = User::find($data['user'])->getProfileLink();
					$HTML .= self::_getNotifElem("$userlink is interested in finishing a <a href='$url'>post</a> you reserved under $EpID. Would you like to pass the reservation to them?", $n);
				break;
				case 'post-passdeny':
				case 'post-passallow':
				case 'post-passfree':
				case 'post-passdel':
				case 'post-passsnatch':
				case 'post-passperm':
					$userlink = User::find($data['by'])->getProfileLink();

					$passaction = str_replace('post-pass','',$n->type);
					switch($passaction){
						case 'allow':
							$HTML .= self::_getNotifElem("Reservation transfer status: $userlink <strong class='color-lightgreen'>transferred</strong> the reservation of <a href='$url'>this post</a> under $EpID to you!", $n);
						break;
						case 'deny':
							$HTML .= self::_getNotifElem("Reservation transfer status: $userlink <strong class='color-lightred'>denied</strong> transferring the reservation of <a href='$url'>this post</a> under $EpID to you.", $n);
						break;
						case 'free':
						case 'del':
						case 'snatch':
						case 'perm':
							$message = Posts::TRANSFER_ATTEMPT_CLEAR_REASONS[$passaction];
							$message = str_replace('post', "<a href='$url'>post</a>", $message);
							switch ($passaction){
								case 'del':
									$message .= " by $userlink";
								break;
								case 'perm':
									$message = str_replace('the previous reserver', $userlink, $message);
								break;
							}
							$HTML .= self::_getNotifElem("Reservation transfer status: $message", $n);
						break;
					}
				break;
				case 'sprite-colors':
					$Appearance = Appearance::find($data['appearance_id']);
					$suffix = CoreUtils::posess($Appearance->label, true);
					$HTML .= self::_getNotifElem("{$Appearance->toAnchor()}$suffix <a href='/cg/sprite/{$Appearance->id}'>sprite</a> is missing some colors", $n);
				break;
				default:
					$HTML .= "<li><code>Notification({$n->type})#{$n->id}</code> <span class='nobr'>&ndash; Missing handler</span></li>";
			}
		}

		return  $wrap ? "<ul class='notif-list'>$HTML</ul>" : $HTML;
	}

	/**
	 * @param string       $html
	 * @param Notification $n
	 *
	 * @return string
	 */
	private static function _getNotifElem(string $html, Notification $n):string {
		if (empty(Notification::$ACTIONABLE_NOTIF_OPTIONS[$n->type]))
			$actions = "<span class='mark-read variant-green typcn typcn-tick' title='Mark read' data-id='{$n->id}'></span>";
		else {
			$actions = '';
			foreach (Notification::$ACTIONABLE_NOTIF_OPTIONS[$n->type] as $value => $opt){
				$confirm = !isset($opt['confirm']) || $opt['confirm'] !== false ? 'data-confirm' :'';
				$action = isset($opt['action']) ? 'data-action="'.CoreUtils::aposEncode($opt['action']).'"' : '';
				$actions .= "<span class='mark-read variant-{$opt['color']} typcn typcn-{$opt['icon']}' title='{$opt['label']}' data-id='{$n->id}' data-value='$value' $confirm $action></span>";
			}
		}
		return "<li>$html <span class='nobr'>&ndash; ".Time::tag(strtotime($n->sent_at))."$actions</span></li>";
	}

	public static function markRead(int $nid, ?string $action = null){
		CoreUtils::socketEvent('mark-read', ['nid' => $nid, 'action' => $action]);
	}

	public static function safeMarkRead(int $NotifID, ?string $action = null, bool $silent = false){
		try {
			self::markRead($NotifID, $action);
		}
		catch (ServerConnectionFailureException $e){
			CoreUtils::error_log("Notification server down!\n".$e->getMessage()."\n".$e->getTraceAsString());
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
