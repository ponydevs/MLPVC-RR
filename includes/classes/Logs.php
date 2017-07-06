<?php

namespace App;

use App\Models\Appearance;
use App\Models\Episode;
use App\Models\EpisodeVideo;
use App\Models\Request;
use App\Models\Reservation;
use App\Models\User;
use cogpowered\FineDiff;

class Logs {
	static $LOG_DESCRIPTION = [
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
		'video_broken' => 'Broken video removed',
		'cm_modify' => 'Appearance CM edited',
		'post_break' => 'Post image broken',
		'post_fix' => 'Broken post restored',
	];

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
	static function logAction($reftype, $data = null, $forcews = false){
		global $Database;
		$central = ['ip' => $_SERVER['REMOTE_ADDR']];

		if (isset($data)){
			foreach ($data as $k => $v)
				if (is_bool($v))
					$data[$k] = $v ? 1 : 0;

			$refid = $Database->insert("log__$reftype",$data,'entryid');
			if (!$refid)
				throw new \Exception('Logging failed: '.$Database->getLastError());
		}

		$central['reftype'] = $reftype;
		if (!empty($refid))
			$central['refid'] = $refid;
		else if (!empty($data)) return false;

		if (Auth::$signed_in && !$forcews)
			$central['initiator'] = Auth::$user->id;
		return (bool) $Database->insert("log",$central);
	}

	static $ACTIONS = [
		'add' => '<span class="color-green"><span class="typcn typcn-plus"></span> Create</span>',
		'del' => '<span class="color-red"><span class="typcn typcn-trash"></span> Delete</span>'
	];

	const
		KEYCOLOR_INFO = 'blue',
		KEYCOLOR_ERROR = 'red',
		SKIP_VALUE = [];

	/**
	 * Format log entry details
	 *
	 * @param array $MainEntry Main log entry
	 * @param array $data      Data to process (sub-log entry)
	 *
	 * @return array
	 */
	static function formatEntryDetails($MainEntry, $data){
		global $Database, $Database;
		$details = [];

		$reftype = $MainEntry['reftype'];
		switch ($reftype){
			case "rolechange":
				/** @var $target User */
				$target =  $Database->where('id',$data['target'])->getOne('users');

				$details = [
					['Target user', $target->getProfileLink()],
					['Old group', Permission::ROLES_ASSOC[$data['oldrole']]],
					['New group', Permission::ROLES_ASSOC[$data['newrole']]]
				];
			break;
			case "episodes":
				$details[] = ['Action', self::$ACTIONS[$data['action']]];
				$details[] = ['Name', (new Episode($data))->formatTitle()];
				if ($data['season'] === 0)
					$details[] = ['Overall', "#{$data['episode']}"];
				if (!empty($data['airs']))
					$details[] = ['Air date', Time::tag($data['airs'], Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME)];
				$details[] = ['Two parts', !empty($data['twoparter'])];
			break;
			case "episode_modify":
				$link = $data['target'];
				$EpData = Episode::parseID($data['target']);
				if (!empty($EpData)){
					$Episode = Episodes::getActual($EpData['season'], $EpData['episode'], Episodes::ALLOW_MOVIES);
					if (!empty($Episode))
						$link = "<a href='".$Episode->toURL()."'>".$Episode->formatTitle(AS_ARRAY, 'id')."</a>";
				}
				$details[] = ['Episode', $link];
				if (empty($Episode))
					$details[] = ['Still exists', false];

				unset($data['entryid'], $data['target']);
				$newOld = self::_arrangeNewOld($data);

				if (!empty($newOld['airs'])){
					$newOld['airs']['old'] =  Time::tag($newOld['airs']['old'], Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME);
					$newOld['airs']['new'] =  Time::tag($newOld['airs']['new'], Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME);
				}
				if (isset($newOld['title']['old']) && isset($newOld['title']['new'])){
					$details[] = ['Title', self::diff($newOld['title']['old'], $newOld['title']['new'])];
					unset($newOld['title']);
				}

				foreach ($newOld as $thing => $ver){
					$details[] = ["Old $thing", $ver['old']];
					$details[] = ["New $thing", $ver['new']];
				}
			break;
			case "userfetch":
				$details[] = ['User', User::find($data['userid'])->getProfileLink()];
			break;
			case "banish":
			case "un-banish":
				$details[] = ['User', User::find($data['target'])->getProfileLink()];
				$details[] = ['Reason', CoreUtils::escapeHTML($data['reason'])];
			break;
			case "post_lock":
				/** @var $Post Request|Reservation */
				$Post = $Database->where('id', $data['id'])->getOne("{$data['type']}s");
				self::_genericPostInfo($Post, $data, $details);
			break;
			case "color_modify":
				$details[] = ['Appearance', self::_getAppearanceLink($data['ponyid'])];
				$details[] = ['Reason', CoreUtils::escapeHTML($data['reason'])];
			break;
			case "req_delete":
				$details[] = ['Request ID', $data['id']];
				$typeNames = [
					'chr' => 'Character',
					'obj' => 'Object',
					'bg' => 'Background',
				];
				$details[] = ['Description', CoreUtils::escapeHTML($data['label'])];
				$details[] = ['Type', $typeNames[$data['type']]];
				$IDstr = "S{$data['season']}E{$data['episode']}";
				$details[] = ['Episode', "<a href='/episode/$IDstr'>$IDstr</a>"];
				$details[] = ['Posted', Time::tag($data['posted'], Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME)];
				if (!empty($data['requested_by']))
					$details[] = ['Requested by', User::find($data['requested_by'])->getProfileLink()];
				if (!empty($data['reserved_by']))
					$details[] = ['Reserved by', User::find($data['reserved_by'])->getProfileLink()];
				$details[] = ['Finished', !empty($data['deviation_id'])];
				if (!empty($data['deviation_id'])){
					$details[] = ['Deviation', self::_link("http://fav.me/{$data['deviation_id']}")];
					$details[] = ['Approved', $data['lock']];
				}
			break;
			case "img_update":
				/** @var $Post Request|Reservation */
				$Post = $Database->where('id', $data['id'])->getOne("{$data['thing']}s");
				$data['type'] = $data['thing'];
				self::_genericPostInfo($Post, $data, $details);
				$details[] = ['Old image', "<a href='{$data['oldfullsize']}' target='_blank' rel='noopener'>Full size</a><div><img src='{$data['oldpreview']}'></div>"];
				$details[] = ['New image', "<a href='{$data['newfullsize']}' target='_blank' rel='noopener'>Full size</a><div><img src='{$data['newpreview']}'></div>"];
			break;
			case "res_overtake":
				/** @var $Post Request|Reservation */
				$Post = $Database->where('id', $data['id'])->getOne("{$data['type']}s");
				self::_genericPostInfo($Post, $data, $details);
				$details[] = ['Previous reserver', User::find($data['reserved_by'])->getProfileLink()];
				$details[] = ['Previously reserved at', Time::tag($data['reserved_at'], Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME)];

				$diff = Time::difference(strtotime($MainEntry['timestamp']), strtotime($data['reserved_at']));
				$diff_text = Time::differenceToString($diff);
				$details[] = ['In progress for', $diff_text];
			break;
			case "appearances":
				$details[] = ['Action', self::$ACTIONS[$data['action']]];

				$PonyGuide = empty($data['ishuman']);
				if (!is_null($data['ishuman']))
					$details[] = ['Guide', $PonyGuide ? 'Pony' : 'EQG'];
				$details[] = ['ID', self::_getAppearanceLink($data['id'])];
				$details[] = ['Label', $data['label']];
				if (!empty($data['order']))
					$details[] = ['Ordering index', $data['order']];
				if (!empty($data['notes']))
					$details[] = ['Notes', '<div>'.nl2br($data['notes']).'</div>'];
				if (!empty($data['cm_favme'])){
					$details[] = ['CM Submission', self::_link("http://fav.me/{$data['cm_favme']}")];
					$details[] = ['CM Orientation', CGUtils::$CM_DIR[$data['cm_dir']]];
					if (!empty($data['cm_preview']))
						$details[] = ['Custom CM Preview', "<img src='".CoreUtils::aposEncode($data['cm_preview'])."'>"];
				}
				if (!empty($data['usetemplate']))
					$details[] = ['Template applied', true];
				$details[] = ['Private', !empty($data['private'])];
				if (!empty($data['added']))
					$details[] = ['Added', Time::tag($data['added'], Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME)];
			break;
			case "res_transfer":
				/** @var $Post Request|Reservation */
				$Post = $Database->where('id', $data['id'])->getOne("{$data['type']}s");
				self::_genericPostInfo($Post, $data, $details);
				$details[] = ['New reserver', User::find($data['to'])->getProfileLink()];
			break;
			case "cg_modify":
				$details[] = ['Appearance', self::_getAppearanceLink($data['ponyid'])];
				$CG = $Database->where('groupid', $data['groupid'])->getOne('colorgroups');
				if (empty($CG)){
					$details[] = ['Color group ID', '#'.$data['groupid']];
					$details[] = ['Still exists', false];
				}
				else $details[] = ['Group', "{$CG['label']} (#{$data['groupid']})"];
				if (isset($data['newlabel']))
					$details[] = ['Label', self::diff($data['oldlabel'] ?? '', $data['newlabel'])];
				if (isset($data['newcolors']))
					$details[] = ['Colors', self::diff($data['oldcolors'] ?? '', $data['newcolors'], 'block')];
			break;
			case "cgs":
				$details[] = ['Action', self::$ACTIONS[$data['action']]];
				$details[] = ['Color group ID', '#'.$data['groupid']];
				$details[] = ['Label', $data['label']];
				$details[] = ['Appearance', self::_getAppearanceLink($data['ponyid'])];
				if (isset($data['order']))
					$details[] = ['Ordering index', $data['order']];
			break;
			case "cg_order":
				$details[] = ['Appearance', self::_getAppearanceLink($data['ponyid'])];
				$details[] = ['Order', self::diff($data['oldgroups'], $data['newgroups'], 'block', new FineDiff\Granularity\Paragraph())];
			break;
			case "appearance_modify":
				$details[] = ['Appearance', self::_getAppearanceLink($data['ponyid'])];
				$changes = JSON::decode($data['changes']);
				$newOld = self::_arrangeNewOld($changes);

				if (isset($newOld['label']['new']))
					$details[] = ['Label', self::diff($newOld['label']['old'], $newOld['label']['new'], 'block')];

				if (isset($newOld['notes']['new']) || isset($newOld['notes']['old']))
					$details[] = ['Notes', self::diff($newOld['notes']['old'] ?? '', $newOld['notes']['new'] ?? '', 'block smaller', new FineDiff\Granularity\Word())];

				if (isset($newOld['cm_favme']['old']))
					$details[] = ['Old CM Submission', self::_link('http://fav.me/'.$newOld['cm_favme']['old'])];
				else if (isset($newOld['cm_favme']['new']))
					$details[] = ['Old CM Submission', null];
				if (isset($newOld['cm_favme']['new']))
					$details[] = ['New CM Submission', self::_link('http://fav.me/'.$newOld['cm_favme']['new'])];
				else if (isset($newOld['cm_favme']['old']))
					$details[] = ['New CM Submission', null];

				$olddir = isset($newOld['cm_dir']['old']) ? CGUtils::$CM_DIR[$newOld['cm_dir']['old']] : '';
				$newdir = isset($newOld['cm_dir']['new']) ? CGUtils::$CM_DIR[$newOld['cm_dir']['new']] : '';
				if ($olddir || $newdir)
					$details[] = ['CM Orientation', self::diff($olddir, $newdir, 'inline', new FineDiff\Granularity\Paragraph())];

				if (isset($newOld['private']['new']))
					$details[] = ['<span class="typcn typcn-lock-'.($newOld['private']['new']?'closed':'open').'"></span> '.($newOld['private']['new'] ? 'Marked private' : 'No longer private'), self::SKIP_VALUE, self::KEYCOLOR_INFO];

				if (isset($newOld['cm_preview']['new']))
					$details[] = ['New Custom CM Preview', "<img src='".CoreUtils::aposEncode($newOld['cm_preview']['new'])."'>"];
				else if (isset($newOld['cm_preview']['old']))
					$details[] = ['New Custom CM Preview', null];
			break;
			case "da_namechange":
				$User = User::find($data['id']);
				$newIsCurrent = $User->name === $data['new'];
				$details[] = ['User', $User->getProfileLink()];
				if ($newIsCurrent)
					$details[] = ['Old name', $data['old']];
				else {
					$details[] = ['Name', Logs::diff($data['old'], $data['new'])];
				}
			break;
			case "video_broken":
				$IDstr = "S{$data['season']}E{$data['episode']}";
				$details[] = ['Episode', "<a href='/episode/$IDstr'>$IDstr</a>"];
				$url = VideoProvider::getEmbed(new EpisodeVideo([
					'provider' => $data['provider'],
					'id' => $data['id'],
				]), VideoProvider::URL_ONLY);
				$details[] = ['Link', "<a href='$url'>$url</a>"];
			break;
			case "cm_modify":
				$details[] = ['Appearance', self::_getAppearanceLink($data['ponyid'])];

				$keys = [];
				if (isset($data['olddata'])){
					$data['olddata'] = JSON::decode($data['olddata']);
					$keys[] = 'olddata';
				}
				if (isset($data['newdata'])){
					$data['newdata'] = JSON::decode($data['newdata']);
					$keys[] = 'newdata';
				}

				foreach($keys as $key){
					foreach ($data[$key] as $k => $_){
						foreach ($data[$key][$k] as $i => $v){
							if (!isset($v) || $i === 'cmid'){
								unset($data[$key][$k][$i]);
								continue;
							}
						}
					}
				}

				$olddata = !empty($data['olddata']) ? JSON::encode($data['olddata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
				$newdata = !empty($data['newdata']) ? JSON::encode($data['newdata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
				if ($olddata || $newdata){
					$diff = self::diff($olddata, $newdata, 'block', new FineDiff\Granularity\Sentence());
					$diff = preg_replace(new RegExp('(\S)(d[a-z\d]{6,})'),'$1<a href="http://fav.me/$2">$2</a>',$diff,1);
					$details[] = ['Metadata changes', $diff];
				}
			break;
			case "post_break":
			case "post_fix":
				/** @var $Post Request|Reservation */
				$Post = $Database->where('id', $data['id'])->getOne("{$data['type']}s");
				self::_genericPostInfo($Post, $data, $details);
			break;
			default:
				$details[] = ['<span class="typcn typcn-warning"></span> Couldn’t process details', 'No data processor defined for this entry type', self::KEYCOLOR_ERROR];
				$details[] = ['Raw details', '<pre>'.var_export($data, true).'</pre>'];
			break;
		}

		return ['details' => $details];
	}

	/**
	 * @param Request|Reservation $Post
	 * @param array               $data
	 * @param array               $details
	 */
	private static function _genericPostInfo($Post, array $data, array &$details){
		$label = CoreUtils::capitalize($data['type'])." #{$data['id']}";
		if (!empty($Post))
			$label = $Post->toAnchor($label);

		$details[] = ['Post', $label];
		if (empty($Post))
			$details[] = ['<span class="typcn typcn-info-large"></span> No longer exists', self::SKIP_VALUE, self::KEYCOLOR_INFO];
		else {
			$EpID = (new Episode($Post))->formatTitle(AS_ARRAY,'id');
			$EpData = Episode::parseID($EpID);
			$Episode = Episodes::getActual($EpData['season'], $EpData['episode'], Episodes::ALLOW_MOVIES);
			$details[] = ['Posted under', "<a href='".$Episode->toURL()."'>$EpID</a>"];
			$details[] = [
				($data['type'] === 'request'?'Requested':'Reserved').' by',
				User::find(
					$data['type'] === 'request'
					? $Post->requested_by
					: $Post->reserved_by
				)->getProfileLink()
			];
			if ($data['type'] === 'request'){
				if (!empty($Post->reserved_by))
					$details[] = ['Reserved by', User::find($Post->reserved_by)->getProfileLink()];
				else $details[] = ['Reserved', false];
			}
		}
	}

	/**
	 * @param int $id
	 *
	 * @return string
	 */
	private static function _getAppearanceLink(int $id):string {
		global $Database;

		$ID = "#$id";
		$Appearance = Appearance::find($id);
		if (!empty($Appearance))
			$ID = "<a href='{$Appearance->getLink()}'>".CoreUtils::escapeHTML($Appearance->label)."</a> ($ID)";

		return $ID;
	}

	private static function _arrangeNewOld($data){
		$newOld = [];
		unset($data['entryid'], $data['target']);
		foreach ($data as $k => $v){
			if (is_null($v)) continue;

			$thing = CoreUtils::substring($k, 3);
			$type = CoreUtils::substring($k, 0, 3);
			if (!isset($newOld[$thing]))
				$newOld[$thing] = [];
			$newOld[$thing][$type] = $thing === 'twoparter' ? !!$v : $v;
		}
		return $newOld;
	}

	private static function _link($url, $blank = false){
		return "<a href='".CoreUtils::aposEncode($url)."' ".($blank?'target="_blank" rel="noopener"':'').">$url</a>";
	}

	/**
	 * Render log page <tbody> content
	 *
	 * @param array $LogItems
	 *
	 * @return string
	 */
	static function getTbody($LogItems):string {
		global $Database;

		$HTML = '';
		if (count($LogItems) > 0) foreach ($LogItems as $item){
			if (!empty($item['initiator'])){
				$inituser = User::find($item['initiator']);
				if (empty($inituser))
					$inituser = 'Deleted user';
				else $inituser = $inituser->getProfileLink();

				$ip = in_array($item['ip'], ['::1', '127.0.0.1']) ? "localhost" : $item['ip'];

				if ($item['ip'] === $_SERVER['REMOTE_ADDR'])
					$ip .= ' <span class="self">(from your IP)</span>';
			}
			else {
				$inituser = null;
				$ip = '<a class="server-init" title="Search for all entries by Web server"><span class="typcn typcn-zoom"></span>&nbsp;Web server</a>';
			}

			$event = Logs::$LOG_DESCRIPTION[$item['reftype']] ?? $item['reftype'];
			if (isset($item['refid']))
				$event = '<span class="expand-section typcn typcn-plus">'.$event.'</span>';
			$ts = Time::tag($item['timestamp'], Time::TAG_EXTENDED);

			if (!empty($inituser)) $ip = "$inituser<br>$ip";

			$HTML .= <<<HTML
		<tr>
			<td class='entryid'>{$item['entryid']}</td>
			<td class='timestamp'>$ts<span class="dynt-el"></span></td>
			<td class='ip'>$ip</td>
			<td class='reftype'>$event</td>
		</tr>
HTML;
		}
		else $HTML = '<tr><td colspan="4"><div class="notice info align-center"><label>No log items found</label></td></tr>';

		return $HTML;
	}

	static function validateRefType($key, $optional = false, $method_get = false){
		return (new Input($key,function($value){
			if (!isset(self::$LOG_DESCRIPTION[$value]))
				return Input::ERROR_INVALID;
		}, [
			Input::IS_OPTIONAL => $optional,
			Input::METHOD_GET => $method_get,
		]))->out();
	}

	static function diff(string $old, string $new, $type = 'inline', FineDiff\Granularity\Granularity $gran = null):string {
		if (!isset($gran))
			$gran = new FineDiff\Granularity\Character;
		else if ($gran instanceof FineDiff\Granularity\Paragraph)
			$old .= "\n";
		$diff = str_replace('\n',"\n",(new FineDiff\Diff($gran))->render($old, $new));

		return "<span class='btn darkblue view-switch' title='Left/Right click to change view mode'>diff</span><div class='log-diff $type'>$diff</div>";
	}
}
