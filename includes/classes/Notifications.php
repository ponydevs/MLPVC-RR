<?php

namespace App;

use App\Models\Post;
use ElephantIO\Exception\ServerConnectionFailureException;

class Notifications {
	private static $_notifTypes = array(
		'post-finished' => true,
		'post-approved' => true,
		'post-passon' => true,
			'post-passdeny' => true,
			'post-passallow' => true,
			'post-passfree' => true,
			'post-passdel' => true,
			'post-passsnatch' => true,
			'post-passperm' => true,
	);
	public static $ACTIONABLE_NOTIF_OPTIONS = array(
		'post-passon' => array(
			'true'  => array(
				'label' => 'Allow',
				'icon' => 'tick',
				'color' => 'green',
			),
			'false' => array(
				'label' => 'Deny',
				'icon' => 'times',
				'color' => 'red',
			),
		)
	);

	const
		UNREAD_ONLY = 0,
		READ_ONLY = 1;

	static function Get($UserID = null, $only = false){
		global $Database;

		if (empty($UserID)){
			global $signedIn, $currentUser;
			if (!$signedIn)
				return null;
			$UserID = $currentUser->id;
		}

		switch($only){
			case self::UNREAD_ONLY:
				$Database->where('read_at IS NULL');
			break;
			case self::READ_ONLY:
				$Database->where('read_at IS NOT NULL');
			break;
		}

		return $Database->where('user', $UserID)->get('notifications');
	}

	static function GetHTML($Notifications, $wrap = true){
		global $Database;
		$HTML = $wrap ? '<ul class="notif-list">' : '';

		foreach ($Notifications as $n){
			$data = !empty($n['data']) ? JSON::Decode($n['data']) : null;
			if (regex_match(new RegExp('^post-'),$n['type'])){
				/** @var $Post Post */
				$Post = $Database->where('id', $data['id'])->getOne("{$data['type']}s");
				$Episode = Episodes::GetActual($Post->season, $Post->episode, Episodes::ALLOW_MOVIES);
				$EpID = $Episode->formatTitle(AS_ARRAY, 'id');
				$url = $Post->toLink($Episode);
			}
			switch ($n['type']){
				case "post-finished":
					$HTML .= self::_getNotifElem("Your <a href='$url'>request</a> under $EpID has been fulfilled", $n);
				break;
				case "post-approved":
					$HTML .= self::_getNotifElem("A <a href='$url'>post</a> you reserved under $EpID has been added to the club gallery", $n);
				break;
				case "post-passon":
					$userlink = Users::Get($data['user'])->getProfileLink();
					$HTML .= self::_getNotifElem("$userlink is interested in finishing a <a href='$url'>post</a> you reserved under $EpID. Would you like to pass the reservation to them?", $n);
				break;
				case "post-passdeny":
				case "post-passallow":
				case "post-passfree":
				case "post-passdel":
				case "post-passsnatch":
				case "post-passperm":
					$userlink =Users::Get($data['by'])->getProfileLink();

					$passaction = str_replace('post-pass','',$n['type']);
					switch($passaction){
						case "allow":
							$HTML .= self::_getNotifElem("Reservation transfer status: $userlink <strong class='color-lightgreen'>transferred</strong> the reservation of <a href='$url'>this post</a> under $EpID to you!", $n);
						break;
						case "deny":
							$HTML .= self::_getNotifElem("Reservation transfer status: $userlink <strong class='color-lightred'>denied</strong> transferring the reservation of <a href='$url'>this post</a> under $EpID to you.", $n);
						break;
						case 'free':
						case 'del':
						case 'snatch':
						case 'perm':
							$message = Posts::$TRANSFER_ATTEMPT_CLEAR_REASONS[$passaction];
							$message = str_replace('post', "<a href='$url'>post</a>", $message);
							switch ($passaction){
								case 'del':
									$message .= " by $userlink";
								break;
								case "perm":
									$message = str_replace('the previous reserver', $userlink, $message);
								break;
							}
							$HTML .= self::_getNotifElem("Reservation transfer status: $message", $n);
						break;
					}
				break;
				default:
					$HTML .= "<li><code>Notification({$n['type']})#{$n['id']}</code> <span class='nobr'>&ndash; Missing handler</span></li>";
			}
		}

		return $HTML.($wrap?'</ul>':'');
	}

	private static function _getNotifElem($html,$n){
		if (empty(self::$ACTIONABLE_NOTIF_OPTIONS[$n['type']]))
			$actions = "<span class='mark-read variant-green typcn typcn-tick' title='Mark read' data-id='{$n['id']}'></span>";
		else {
			$actions = '';
			foreach (self::$ACTIONABLE_NOTIF_OPTIONS[$n['type']] as $value => $opt)
				$actions .= "<span class='mark-read variant-{$opt['color']} typcn typcn-{$opt['icon']}' title='{$opt['label']}' data-id='{$n['id']}' data-value='$value'></span>";
		}
		return "<li>$html <span class='nobr'>&ndash; ".Time::Tag(strtotime($n['sent_at']))."$actions</span></li>";
	}

	static function Send($to, $type, $data){
		global $Database;

		if (empty(self::$_notifTypes[$type]))
			throw new \Exception("Invalid notification type: $type");

		switch ($type){
			case 'post-finished':
			case 'post-approved':
				$Database->rawQuery(
					"UPDATE notifications SET read_at = NOW() WHERE \"user\" = ? && type = ? && data->>'id' = ? && data->>'type' = ?",
					array($to,$type,$data['id'],$data['type'])
				);
		}

		$Database->insert('notifications',array(
			'user' => $to,
			'type' => $type,
			'data' => JSON::Encode($data),
		));

		try {
			CoreUtils::SocketEvent('notify-pls',array('user' => $to));
		}
		catch (ServerConnectionFailureException $e){
			error_log("Error while notifying $to with type $type (data:".JSON::Encode($data).")\nError message: {$e->getMessage()}");
			return 'Notification server is down! Please <a class="send-feedback">let us know</a>.';
		}

		return 0;
	}

	static function MarkRead($nid, $action = null){
		CoreUtils::SocketEvent('mark-read',array('nid' => $nid, 'action' => $action));
	}

	static function SafeMarkRead($NotifID, $action = null){
		try {
			Notifications::MarkRead($NotifID, $action);
		}
		catch (ServerConnectionFailureException $e){
			error_log("Notification server down!\n".$e->getMessage());
			Response::Fail('Notification server is down! Please <a class="send-feedback">let us know</a>.');
		}
		catch (\Exception $e){
			error_log("SocketEvent Error\n".$e->getMessage());
			Response::Fail('SocketEvent Error: '.$e->getMessage());
		}
	}
}
