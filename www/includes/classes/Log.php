<?php

	class Log {
		static $LOG_DESCRIPTION = array(
			'junk' => 'Junk entry',
			'episodes' => 'Episode management',
			'episode_modify' => 'Episode modified',
			'rolechange' => 'User group change',
			'userfetch' => 'Fetch user details',
			'banish' => 'User banished',
			'un-banish' => 'User un-banished',
			'post_lock' => 'Post approved',
			'color_modify' => 'Major appearance update',
			'req_delete' => 'Request deleted',
			'img_update' => 'Post image updated',
			'res_overtake' => 'Overtook post reservation',
		);

		/**
		 * Logs a specific set of data (action) in the table belonging to the specified type
		 *
		 * @param string $reftype Log entry type
		 * @param array  $data    Data to be inserted
		 *
		 * @return bool
		 */
		static function Action($reftype, $data = null){
			global $Database, $signedIn, $currentUser;
			$central = array('ip' => $_SERVER['REMOTE_ADDR']);

			if (isset($data)){
				foreach ($data as $k => $v)
					if (is_bool($v))
						$data[$k] = $v ? 1 : 0;

				$refid = $Database->insert("log__$reftype",$data,'entryid');
				if (!$refid)
					throw new Exception('Logging failed: '.$Database->getLastError());
			}

			$central['reftype'] = $reftype;
			if (!empty($refid))
				$central['refid'] = $refid;
			else if (!empty($data)) return false;

			if ($signedIn)
				$central['initiator'] = $currentUser['id'];
			return (bool) $Database->insert("log",$central);
		}

		/**
		 * Format log entry details
		 *
		 * @param array $MainEntry Main log entry
		 * @param array $data      Data to process (sub-log entry)
		 *
		 * @return array
		 */
		static function FormatEntryDetails($MainEntry, $data){
			global $Database;
			$details = array();

			$reftype = $MainEntry['reftype'];
			switch ($reftype){
				case "rolechange":
					$target =  $Database->where('id',$data['target'])->getOne('users');

					$details = array(
						array('Target user', User::GetProfileLink($target)),
						array('Old group', Permission::$ROLES_ASSOC[$data['oldrole']]),
						array('New group', Permission::$ROLES_ASSOC[$data['newrole']])
					);
				break;
				case "episodes":
					$actions = array('add' => 'create', 'del' => 'delete');
					$details[] = array('Action', $actions[$data['action']]);
					$details[] = array('Name', Episode::FormatTitle($data));
					if (!empty($data['airs']))
						$details[] = array('Airs', Time::Tag($data['airs'], Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME));
				break;
				case "episode_modify":
					$details[] = array('Target episode', $data['target']);

					$newOld = array();
					unset($data['entryid'], $data['target']);
					foreach ($data as $k => $v){
						if (is_null($v)) continue;

						$thing = substr($k, 3);
						$type = substr($k, 0, 3);
						if (!isset($newOld[$thing]))
							$newOld[$thing] = array();
						$newOld[$thing][$type] = $thing === 'twoparter' ? !!$v : $v;
					}

					if (!empty($newOld['airs'])){
						$newOld['airs']['old'] =  Time::Tag($newOld['airs']['old'], Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME);
						$newOld['airs']['new'] =  Time::Tag($newOld['airs']['new'], Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME);
					}

					foreach ($newOld as $thing => $ver){
						$details[] = array("Old $thing",$ver['old']);
						$details[] = array("New $thing",$ver['new']);
					}
				break;
				case "userfetch":
					$details[] = array('User', User::GetProfileLink(User::Get($data['userid'])));
				break;
				case "banish":
				case "un-banish":
					$details[] = array('User', User::GetProfileLink(User::Get($data['target'])));
					$details[] = array('Reason', CoreUtils::EscapeHTML($data['reason']));
				break;
				case "post_lock":
					$Post = $Database->where('id', $data['id'])->getOne("{$data['type']}s");
					if (empty($Post))
						$details[] = array('Notice', 'The post has been deleted, some details are unavailable');
					$details[] = array('ID',$data['id']);
					$details[] = array('Type',$data['type']);
					if (!empty($Post)){
						$Fragment = "#{$data['type']}-{$data['id']}";
						if ($Post['season'] == 0){
							$IDstr = "EQG{$Post['episode']}";
							$Link = "/eqg/{$Post['episode']}";
						}
						else{
							$IDstr = "S{$Post['season']}E{$Post['episode']}";
							$Link = "/episode/$IDstr";
						}
						$details[] = array('Link',"<a href='$Link$Fragment'>$IDstr$Fragment</a>");
					}
				break;
				case "color_modify":
					$details[] = array('Appearance',"<a href='/cg/v/{$data['ponyid']}'>#{$data['ponyid']}</a>");
					$details[] = array('Reason',CoreUtils::EscapeHTML($data['reason']));
				break;
				case "req_delete":
					$details[] = array('Request ID', $data['id']);
					$typeNames = array(
						'chr' => 'Character',
						'obj' => 'Object',
						'bg' => 'Background',
					);
					$details[] = array('Description',CoreUtils::EscapeHTML($data['label']));
					$details[] = array('Type',$typeNames[$data['type']]);
					$IDstr = "S{$data['season']}E{$data['episode']}";
					$details[] = array('Episode', "<a href='/episode/$IDstr'>$IDstr</a>");
					$details[] = array('Posted', Time::Tag($data['posted'], Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME));
					if (!empty($data['requested_by']))
						$details[] = array('Requested by', User::GetProfileLink(User::Get($data['requested_by'])));
					if (!empty($data['reserved_by']))
						$details[] = array('Reserved by', User::GetProfileLink(User::Get($data['reserved_by'])));
					$details[] = array('Finished', !empty($data['deviation_id']));
					if (!empty($data['deviation_id'])){
						$DeviationURL = "http://fav.me/{$data['deviation_id']}";
						$details[] = array('Deviation', "<a href='$DeviationURL'>$DeviationURL</a>");

						$details[] = array('Approved', $data['lock']);
					}
				break;
				case "img_update":
					$Post = $Database->where('id', $data['id'])->getOne("{$data['thing']}s");
					if (empty($Post))
						$details[] = array('Notice', 'The post has been deleted, some details are unavailable');
					$details[] = array('Post ID',$data['id']);
					$details[] = array('Type',$data['thing']);
					if (!empty($Post)){
						$IDstr = "S{$Post['season']}E{$Post['episode']}#{$data['thing']}-{$data['id']}";
						$details[] = array('Link',"<a href='/episode/$IDstr'>$IDstr</a>");
					}
					$details[] = array('Old',"<a href='{$data['oldfullsize']}' target='_blank'>Full size</a><div><img src='{$data['oldpreview']}'></div>");
					$details[] = array('New',"<a href='{$data['newfullsize']}' target='_blank'>Full size</a><div><img src='{$data['newpreview']}'></div>");
				break;
				case "res_overtake":
					$Post = $Database->where('id', $data['id'])->getOne("{$data['type']}s");
					if (empty($Post))
						$details[] = array('Notice', 'The post has been deleted, some details are unavailable');
					$details[] = array('ID',$data['id']);
					$details[] = array('Type',$data['type']);
					if (!empty($Post)){
						$Fragment = "#{$data['type']}-{$data['id']}";
						if ($Post['season'] == 0){
							$IDstr = "EQG{$Post['episode']}";
							$Link = "/eqg/{$Post['episode']}";
						}
						else{
							$IDstr = "S{$Post['season']}E{$Post['episode']}";
							$Link = "/episode/$IDstr";
						}
						$details[] = array('Link',"<a href='$Link$Fragment'>$IDstr$Fragment</a>");
					}
					$details[] = array('Previous reserver',User::GetProfileLink(User::Get($data['reserved_by'])));
					$details[] = array('Previously reserved at', Time::Tag($data['reserved_at'], Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME));

					$diff_text = '';
					$diff = Time::Difference(strtotime($MainEntry['timestamp']), strtotime($data['reserved_at']));
					foreach (array_keys(Time::$IN_SECONDS) as $unit){
						if (empty($diff[$unit]))
							continue;
						$diff_text .= CoreUtils::MakePlural($unit, $diff[$unit], PREPEND_NUMBER).' ';
					}
					$details[] = array('In progress for', rtrim($diff_text));
				break;
				default:
					$details[] = array('Could not process details','No data processor defined for this entry type');
					$details[] = array('Raw details', '<pre>'.var_export($data, true).'</pre>');
				break;
			}

			return array('details' => $details);
		}

		/**
		 * Render log page <tbody> content
		 *
		 * @param $LogItems
		 *
		 * @return string
		 */
		static function GetTbody($LogItems){
			global $Database;

			$HTML = '';
			if (count($LogItems) > 0) foreach ($LogItems as $item){
				if (!empty($item['initiator'])){
					$inituser = User::Get($item['initiator'],'id');
					if (empty($inituser))
						$inituser = 'Deleted user';
					else $inituser = User::GetProfileLink($inituser);

					$ip = in_array($item['ip'], array('::1', '127.0.0.1')) ? "localhost" : $item['ip'];

					if ($item['ip'] === $_SERVER['REMOTE_ADDR'])
						$ip .= ' <span class="self">(from your IP)</span>';
				}
				else {
					$inituser = null;
					$ip = '<a class="server-init" title="Search for all entries by Web server"><span class="typcn typcn-zoom"></span>&nbsp;Web server</a>';
				}

				$event = Log::$LOG_DESCRIPTION[$item['reftype']] ?? $item['reftype'];
				if (isset($item['refid']))
					$event = '<span class="expand-section typcn typcn-plus">'.$event.'</span>';
				$ts = Time::Tag($item['timestamp'], Time::TAG_EXTENDED);

				if (!empty($inituser)) $ip = "$inituser<br>$ip";

				$HTML .= <<<HTML
			<tr>
				<td class='entryid'>{$item['entryid']}</td>
				<td class='timestamp'>$ts</td>
				<td class='ip'>$ip</td>
				<td class='reftype'>$event</td>
			</tr>
HTML;
			}
			else $HTML = '<tr><td colspan="4"><div class="notice info align-center"><label>No log items found</label></td></tr>';

			return $HTML;
		}
		
		static function ValidateRefType($key, $optional = false, $method_get = false){
			return (new Input($key,function($value){
				if (!isset(self::$LOG_DESCRIPTION[$value]))
					return Input::ERROR_INVALID;
			},array(
				Input::IS_OPTIONAL => $optional,
				Input::METHOD_GET => $method_get,
			)))->out();
		}
	}
