<?php

use DB\Episode;

class Log {
		static $LOG_DESCRIPTION = array(
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
			'appearances' => 'Appearance management',
			'res_transfer' => 'Reservation transferred',
			'cg_modify' => 'Color group modified',
			'cgs' => 'Color group management',
			'cg_order' => 'Color groups re-ordered',
			'appearance_modify' => 'Appearance modified',
			'da_namechange' => 'Username change detected',
		);

		const FORCE_INITIATOR_WEBSERVER = true;

		/**
		 * Logs a specific set of data (action) in the table belonging to the specified type
		 *
		 * @param string $reftype Log entry type
		 * @param array  $data    Data to be inserted
		 * @param bool   $forcews Force initiator to be null
		 *
		 * @return bool
		 */
		static function Action($reftype, $data = null, $forcews = false){
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

			if ($signedIn && !$forcews)
				$central['initiator'] = $currentUser['id'];
			return (bool) $Database->insert("log",$central);
		}

		static $ACTIONS = array(
			'add' => '<span class="color-green"><span class="typcn typcn-plus"></span> Create</span>',
			'del' => '<span class="color-red"><span class="typcn typcn-trash"></span> Delete</span>'
		);

		const
			KEYCOLOR_INFO = 'blue',
			KEYCOLOR_ERROR = 'red';

		/**
		 * Format log entry details
		 *
		 * @param array $MainEntry Main log entry
		 * @param array $data      Data to process (sub-log entry)
		 *
		 * @return array
		 */
		static function FormatEntryDetails($MainEntry, $data){
			global $Database, $CGDb;
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
					$details[] = array('Action', self::$ACTIONS[$data['action']]);
					$details[] = array('Name', (new Episode($data))->formatTitle());
					if ($data['season'] === 0)
						$details[] = array('Overall', "#{$data['episode']}");
					if (!empty($data['airs']))
						$details[] = array('Air date', Time::Tag($data['airs'], Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME));
					$details[] = array('Two parts', !empty($data['twoparter']));
				break;
				case "episode_modify":
					$link = $data['target'];
					$EpData = Episodes::ParseID($data['target']);
					if (!empty($EpData)){
						$Episode = Episodes::GetActual($EpData['season'], $EpData['episode'], Episodes::ALLOW_MOVIES);
						if (!empty($Episode))
							$link = "<a href='".$Episode->formatURL()."'>".$Episode->formatTitle(AS_ARRAY, 'id')."</a>";
					}
					$details[] = array('Episode', $link);
					if (empty($Episode))
						$details[] = array('Still exists', false);

					unset($data['entryid'], $data['target']);
					$newOld = self::_arrangeNewOld($data);

					if (!empty($newOld['airs'])){
						$newOld['airs']['old'] =  Time::Tag($newOld['airs']['old'], Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME);
						$newOld['airs']['new'] =  Time::Tag($newOld['airs']['new'], Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME);
					}
					if (isset($newOld['title']['old']) && isset($newOld['title']['new'])){
						$details[] = array('Title', self::CharDiff($newOld['title']['old'], $newOld['title']['new']));
						unset($newOld['title']);
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
					self::_genericPostInfo($Post, $data, $details);
				break;
				case "color_modify":
					$details[] = array('Appearance',self::_getAppearanceLink($data['ponyid']));
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
						$details[] = array('Deviation', self::_link("http://fav.me/{$data['deviation_id']}"));
						$details[] = array('Approved', $data['lock']);
					}
				break;
				case "img_update":
					$Post = $Database->where('id', $data['id'])->getOne("{$data['thing']}s");
					$data['type'] = $data['thing'];
					self::_genericPostInfo($Post, $data, $details);
					$details[] = array('Old image',"<a href='{$data['oldfullsize']}' target='_blank'>Full size</a><div><img src='{$data['oldpreview']}'></div>");
					$details[] = array('New image',"<a href='{$data['newfullsize']}' target='_blank'>Full size</a><div><img src='{$data['newpreview']}'></div>");
				break;
				case "res_overtake":
					$Post = $Database->where('id', $data['id'])->getOne("{$data['type']}s");
					self::_genericPostInfo($Post, $data, $details);
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
				case "appearances":
					$details[] = array('Action', self::$ACTIONS[$data['action']]);

					$PonyGuide = empty($data['ishuman']);
					$details[] = array('Guide', $PonyGuide ? 'Pony' : 'EQG');
					$details[] = array('ID', self::_getAppearanceLink($data['id']));
					$details[] = array('Label', $data['label']);
					if (!empty($data['order']))
						$details[] = array('Ordering index', $data['order']);
					if (!empty($data['notes']))
						$details[] = array('Notes', '<div>'.nl2br($data['notes']).'</div>');
					if (!empty($data['cm_favme'])){
						$details[] = array('CM Submission', self::_link("http://fav.me/{$data['cm_favme']}"));
						$details[] = array('CM Orientation', CGUtils::$CM_DIR[$data['cm_dir']]);
						if (!empty($data['cm_preview']))
							$details[] = array('Custom CM Preview', "<img src='".CoreUtils::AposEncode($data['cm_preview'])."'>");
					}
					if (!empty($data['usetemplate']))
						$details[] = array('Template applied', true);
					if (!empty($data['added']))
						$details[] = array('Added', Time::Tag($data['added'], Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME));
				break;
				case "res_transfer":
					$Post = $Database->where('id', $data['id'])->getOne("{$data['type']}s");
					self::_genericPostInfo($Post, $data, $details);
					$details[] = array('New reserver',User::GetProfileLink(User::Get($data['to'])));
				break;
				case "cg_modify":
					$details[] = array('Appearance',self::_getAppearanceLink($data['ponyid']));
					$CG = $CGDb->where('groupid', $data['groupid'])->getOne('colorgroups');
					if (empty($CG)){
						$details[] = array('Color group ID', '#'.$data['groupid']);
						$details[] = array('Still exists', false);
					}
					else $details[] = array('Group', "{$CG['label']} (#{$data['groupid']})");
					if (isset($data['newlabel']))
						$details[] = array('Label', self::CharDiff($data['oldlabel'] ?? '', $data['newlabel']));
					if (isset($data['newcolors']))
						$details[] = array('Colors', self::CharDiff($data['oldcolors'] ?? '', $data['newcolors'], 'block'));
				break;
				case "cgs":
					$details[] = array('Action', self::$ACTIONS[$data['action']]);
					$details[] = array('Color group ID', '#'.$data['groupid']);
					$details[] = array('Label', $data['label']);
					$details[] = array('Appearance',self::_getAppearanceLink($data['ponyid']));
					if (isset($data['order']))
						$details[] = array('Ordering index', $data['order']);
				break;
				case "cg_order":
					$details[] = array('Appearance',self::_getAppearanceLink($data['ponyid']));
					$details[] = array('Order',self::CharDiff($data['oldgroups'], $data['newgroups'], 'block'));
				break;
				case "appearance_modify":
					$details[] = array('Appearance',self::_getAppearanceLink($data['ponyid']));
					$changes = JSON::Decode($data['changes']);
					$newOld = self::_arrangeNewOld($changes);

					if (isset($newOld['label']['new']))
						$details[] = array('Label', self::CharDiff($newOld['label']['old'], $newOld['label']['new'], 'block'));

					if (isset($newOld['notes']['new']) || isset($newOld['notes']['old']))
						$details[] = array('Notes', self::CharDiff($newOld['notes']['old'] ?? '', $newOld['notes']['new'] ?? '', 'block smaller'));

					if (isset($newOld['cm_favme']['old']))
						$details[] = array('Old CM Submission', self::_link('http://fav.me/'.$newOld['cm_favme']['old']));
					else if (isset($newOld['cm_favme']['new']))
						$details[] = array('Old CM Submission', null);
					if (isset($newOld['cm_favme']['new']))
						$details[] = array('New CM Submission', self::_link('http://fav.me/'.$newOld['cm_favme']['new']));
					else if (isset($newOld['cm_favme']['old']))
						$details[] = array('New CM Submission', null);

					$olddir = isset($newOld['cm_dir']['old']) ? CGUtils::$CM_DIR[$newOld['cm_dir']['old']] : '';
					$newdir = isset($newOld['cm_dir']['new']) ? CGUtils::$CM_DIR[$newOld['cm_dir']['new']] : '';
					if ($olddir || $newdir)
						$details[] = array('CM Orientation', self::CharDiff($olddir, $newdir, 'inline', FineDiff::$paragraphGranularity));

					if (isset($newOld['cm_preview']['new']))
						$details[] = array('New Custom CM Preview', "<img src='".CoreUtils::AposEncode($newOld['cm_preview']['new'])."'>");
					else if (isset($newOld['cm_preview']['old']))
						$details[] = array('New Custom CM Preview', null);
				break;
				case "da_namechange":
					$User = User::Get($data['id'], 'id', 'name');
					$newIsCurrent = $User['name'] === $data['new'];
					$details[] = array('User', User::GetProfileLink($User));
					if ($newIsCurrent)
						$details[] = array('Old name', $data['old']);
					else {
						$details[] = array('Name', Log::CharDiff($data['old'], $data['new']));
					}
				break;
				default:
					$details[] = array('<span class="typcn typcn-warning"></span> Couldn\'t process details','No data processor defined for this entry type',self::KEYCOLOR_ERROR);
					$details[] = array('Raw details', '<pre>'.var_export($data, true).'</pre>');
				break;
			}

			return array('details' => $details);
		}

		private static function _genericPostInfo(array $Post, array $data, array &$details){
			$label = CoreUtils::Capitalize($data['type'])." #{$data['id']}";
			if (!empty($Post)){
				list($link) = Posts::GetLink($Post, $data['type']);
				$label = "<a href='$link'>$label</a>";
			}
			$details[] = array('Post',$label);
			if (empty($Post))
				$details[] = array('Still exists', false);
			$EpID = (new Episode($Post))->formatTitle(AS_ARRAY,'id');
			$EpData = Episodes::ParseID($EpID);
			$Episode = Episodes::GetActual($EpData['season'], $EpData['episode'], Episodes::ALLOW_MOVIES);

			$details[] = array('Posted under', !empty($Episode) ? "<a href='".$Episode->formatURL()."'>$EpID</a>" : $EpID.' (now deleted/moved)');
			if (!empty($Post)){
				$details[] = array(($data['type'] === 'request'?'Requested':'Reserved').' by', User::GetProfileLink(User::Get($Post[$data['type'] === 'request' ? 'requested_by' : 'reserved_by'])));
				if ($data['type'] === 'request'){
					if (!empty($Post['reserved_by']))
						$details[] = array('Reserved by', User::GetProfileLink(User::Get($Post['reserved_by'])));
					else $details[] = array('Reserved', false);
				}
			}
		}

		private static function _getAppearanceLink($id){
			global $CGDb;

			$ID = "#$id";
			$Appearance = $CGDb->where('id', $id)->getOne('appearances');
			if (!empty($Appearance)){
				$EQGUrl = $Appearance['ishuman'] ? '/eqg' : '';
				$ID = "<a href='/cg/{$EQGUrl}v/$id'>".htmlspecialchars($Appearance['label'])."</a> ($ID)";
			}

			return $ID;
		}

		private static function _arrangeNewOld($data){
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
			return $newOld;
		}

		private static function _link($url, $blank = false){
			return "<a href='".CoreUtils::AposEncode($url)."'".($blank?' target="_blank"':'').">$url</a>";
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

		static function CharDiff(string $old, string $new, $type = 'inline', $gran = null):string {
			if (!isset($gran))
				$gran = FineDiff::$characterGranularity;
			$opcodes = FineDiff::getDiffOpcodes($old, $new, $gran);
			$diff = FineDiff::renderDiffToHTMLFromOpcodes($old, $opcodes);

			return "<span class='btn darkblue view-switch' title='Left/Right click to change view mode'>diff</span><div class='log-diff $type'>$diff</div>";
		}
	}
