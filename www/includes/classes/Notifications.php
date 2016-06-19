<?php

	class Notifications {
		private static $_notifTypes = array(
			'finish' => true,
		);
		
		static function Get($UserID = null, $only = false){
			global $Database;
			
			if (empty($UserID)){
				global $signedIn, $currentUser;
				if (!$signedIn)
					return null;
				$UserID = $currentUser['id'];
			}

			switch($only){
				case UNREAD_ONLY:
					$Database->where('read_at IS NULL');
				break;
				case READ_ONLY:
					$Database->where('read_at IS NOT NULL');
				break;
			}

			return $Database->where('user', $UserID)->get('notifications');
		}
		
		static function GetHTML($Notifications, $wrap = true){
			global $Database;
			$HTML = $wrap ? '<ul class="notif-list">' : '';
			
			foreach ($Notifications as $n){
				$data = JSON::Decode($n['data']);
				switch ($n['type']){
					case "finish":
						$Post = $Database->where('id', $data['id'])->getOne("{$data['type']}s");
						$Episode = Episode::GetActual($Post['season'],$Post['episode'], ALLOW_SEASON_ZERO);
						$EpID = Episode::FormatTitle($Episode, AS_ARRAY, 'id');
						$url = "/episode/$EpID#{$data['type']}-{$data['id']}";
						$HTML .= self::_getNotifElem("Your <a href='$url'>request</a> under $EpID has been fulfilled", $n['id']);
					break;
				}
			}
			
			return $HTML.($wrap?'</ul>':'');
		}
		
		private static function _getNotifElem($html,$id){
			return "<li>$html <span class='mark-read typcn typcn-tick' title='Mark read' data-id='$id'></span></li>";
		}
		
		static function Send($to, $type, $data){
			global $Database;

			if (empty(self::$_notifTypes[$type]))
				throw new Exception("Invalid notification type: $type");

			$Database->insert('notifications',array(
				'user' => $to,
				'type' => $type,
				'data' => JSON::Encode($data),
			));
			
			CoreUtils::SocketEvent('notify-pls',array('user' => $to));
		}
		
		static function MarkRead($nid){
			CoreUtils::SocketEvent('mark-read',array('nid' => $nid));
		}
	}
