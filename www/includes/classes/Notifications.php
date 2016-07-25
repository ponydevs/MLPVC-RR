<?php

	class Notifications {
		private static $_notifTypes = array(
			'post-finished' => true,
			'post-approved' => true,
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
				$UserID = $currentUser['id'];
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
				switch ($n['type']){
					case "post-finished":
						$Post = $Database->where('id', $data['id'])->getOne("{$data['type']}s");
						$Episode = Episode::GetActual($Post['season'],$Post['episode'], Episode::ALLOW_SEASON_ZERO);
						$EpID = Episode::FormatTitle($Episode, AS_ARRAY, 'id');
						$url = "/episode/$EpID#{$data['type']}-{$data['id']}";
						$HTML .= self::_getNotifElem("Your <a href='$url'>request</a> under $EpID has been fulfilled", $n);
					break;
					case "post-approved":
						$Post = $Database->where('id', $data['id'])->getOne("{$data['type']}s");
						$Episode = Episode::GetActual($Post['season'],$Post['episode'], Episode::ALLOW_SEASON_ZERO);
						$EpID = Episode::FormatTitle($Episode, AS_ARRAY, 'id');
						$url = "/episode/$EpID#{$data['type']}-{$data['id']}";
						$HTML .= self::_getNotifElem("A <a href='$url'>post</a> you reserved under $EpID has been added do the club gallery", $n);
					break;
				}
			}
			
			return $HTML.($wrap?'</ul>':'');
		}
		
		private static function _getNotifElem($html,$n){
			return "<li>$html <span class='nobr'>&ndash; ".Time::Tag(strtotime($n['sent_at']))."<span class='mark-read typcn typcn-tick' title='Mark read' data-id='{$n['id']}'></span></span></li>";
		}
		
		static function Send($to, $type, $data){
			global $Database;

			if (empty(self::$_notifTypes[$type]))
				throw new Exception("Invalid notification type: $type");

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
			
			CoreUtils::SocketEvent('notify-pls',array('user' => $to));
		}
		
		static function MarkRead($nid){
			CoreUtils::SocketEvent('mark-read',array('nid' => $nid));
		}
	}
