<?php

namespace App;

use ActiveRecord\RecordNotFound;
use App\Models\Appearance;
use App\Models\ColorGroup;
use App\Models\Show;
use App\Models\ShowVideo;
use App\Models\Logs\Log;
use App\Models\PCGSlotGift;
use App\Models\Post;
use App\Models\User;
use cogpowered\FineDiff;

class Logs {
	public const LOG_DESCRIPTION = [
		#--------------------# (max length)
		'rolechange'           => 'User group change',
		'userfetch'            => 'Fetch user details',
		'post_lock'            => 'Post approved',
		'major_changes'        => 'Major appearance update',
		'req_delete'           => 'Request deleted',
		'img_update'           => 'Post image updated',
		'res_overtake'         => 'Overtook post reservation',
		'appearances'          => 'Appearance management',
		'res_transfer'         => 'Reservation transferred',
		'cg_modify'            => 'Color group modified',
		'cgs'                  => 'Color group management',
		'cg_order'             => 'Color groups re-ordered',
		'appearance_modify'    => 'Appearance modified',
		'da_namechange'        => 'Username change detected',
		'video_broken'         => 'Broken video removed',
		'cm_modify'            => 'Appearance CM edited',
		'post_break'           => 'Post image broken',
		'post_fix'             => 'Broken post restored',
		'staff_limits'         => 'Account limitation changed',
		'pcg_gift_refund'      => 'Gifted slots refunded',
		'failed_auth_attempts' => 'Failed authentication attempt',
	];

	public const FORCE_INITIATOR_WEBSERVER = true;

	/**
	 * Logs a specific set of data (action) in the table belonging to the specified type
	 *
	 * @param string $reftype Log entry type
	 * @param array  $data    Data to be inserted
	 * @param bool   $forcews Force initiator to be null
	 *
	 * @return bool
	 * @throws \RuntimeException
	 */
	public static function logAction($reftype, $data = null, $forcews = false){
		$central = ['ip' => $_SERVER['REMOTE_ADDR']];

		if (isset($data)){
			foreach ($data as $k => $v)
				if (\is_bool($v))
					$data[$k] = $v ? 1 : 0;

			$refid = DB::$instance->insert("log__$reftype",$data,'entryid');
			if (!$refid)
				throw new \RuntimeException('Logging failed: '.DB::$instance->getLastError());
		}

		$central['reftype'] = $reftype;
		if (!empty($refid))
			$central['refid'] = $refid;
		else if (!empty($data)) return false;

		if (Auth::$signed_in && !$forcews)
			$central['initiator'] = Auth::$user->id;
		return (new Log($central))->save();
	}

	public static $ACTIONS = [
		'add' => '<span class="color-green"><span class="typcn typcn-plus"></span> Create</span>',
		'del' => '<span class="color-red"><span class="typcn typcn-trash"></span> Delete</span>'
	];

	public const
		KEYCOLOR_INFO = 'blue',
		KEYCOLOR_ERROR = 'red',
		KEYCOLOR_SUCCESS = 'green',
		SKIP_VALUE = [];

	/**
	 * Format log entry details
	 *
	 * @param array $MainEntry Main log entry
	 * @param array $data      Data to process (sub-log entry)
	 *
	 * @return array
	 * @throws \App\Exceptions\JSONParseException
	 */
	public static function formatEntryDetails($MainEntry, $data){
		$details = [];

		$reftype = $MainEntry['reftype'];
		switch ($reftype){
			case 'rolechange':
				/** @var $target User */
				$target =  DB::$instance->where('id',$data['target'])->getOne('users');

				$details = [
					['Target user', $target->toAnchor()],
					['Old group', Permission::ROLES_ASSOC[$data['oldrole']]],
					['New group', Permission::ROLES_ASSOC[$data['newrole']]]
				];
			break;
			case 'userfetch':
				$details[] = ['User', User::find($data['userid'])->toAnchor()];
			break;
			case 'post_lock':
				self::_genericPostInfo($data, $details);
			break;
			case 'major_changes':
				$details[] = ['Appearance', self::_getAppearanceLink($data['appearance_id'])];
				$details[] = ['Reason', CoreUtils::escapeHTML($data['reason'])];
			break;
			case 'req_delete':
				$details[] = self::_getReferenceForDeletedPost($data, 'Request');
				$details[] = ['Description', CoreUtils::escapeHTML($data['label'])];
				$details[] = ['Type', Post::REQUEST_TYPES[$data['type']]];
				$ep = Show::find($data['show_id']);
				$details[] = ['Posted under', !empty($ep) ? $ep->toAnchor() : "S{$data['season']}E{$data['episode']}"];
				$details[] = ['Requested on', Time::tag($data['requested_at'], Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME)];
				if (!empty($data['requested_by']))
					$details[] = ['Requested by', User::find($data['requested_by'])->toAnchor()];
				if (!empty($data['reserved_by']))
					$details[] = ['Reserved by', User::find($data['reserved_by'])->toAnchor()];
				$details[] = ['Finished', !empty($data['deviation_id'])];
				if (!empty($data['deviation_id'])){
					$details[] = ['Deviation', self::_link("http://fav.me/{$data['deviation_id']}")];
					$details[] = ['Approved', $data['lock']];
				}
			break;
			case 'img_update':
				self::_genericPostInfo($data, $details);
				$details[] = ['Old image', "<a href='{$data['oldfullsize']}' target='_blank' rel='noopener'>Full size</a><div><img src='{$data['oldpreview']}'></div>"];
				$details[] = ['New image', "<a href='{$data['newfullsize']}' target='_blank' rel='noopener'>Full size</a><div><img src='{$data['newpreview']}'></div>"];
			break;
			case 'res_overtake':
				self::_genericPostInfo($data, $details);
				$details[] = ['Previous reserver', User::find($data['reserved_by'])->toAnchor()];
				$details[] = ['Previously reserved at', Time::tag($data['reserved_at'], Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME)];

				$diff = Time::difference(strtotime($MainEntry['timestamp']), strtotime($data['reserved_at']));
				$diff_text = Time::differenceToString($diff);
				$details[] = ['In progress for', $diff_text];
			break;
			case 'appearances':
				$details[] = ['Action', self::$ACTIONS[$data['action']]];

				$PonyGuide = empty($data['ishuman']);
				if ($data['ishuman'] !== null)
					$details[] = ['Guide', $PonyGuide ? 'Pony' : 'EQG'];
				$details[] = ['ID', self::_getAppearanceLink($data['id'])];
				$details[] = ['Label', $data['label']];
				if (!empty($data['order']))
					$details[] = ['Ordering index', $data['order']];
				if (!empty($data['notes']))
					$details[] = ['Notes', '<div>'.nl2br($data['notes']).'</div>'];
				if (!empty($data['usetemplate']))
					$details[] = ['Template applied', true];
				$details[] = ['Private', !empty($data['private'])];
				if (!empty($data['added']))
					$details[] = ['Added', Time::tag($data['added'], Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME)];
			break;
			case 'res_transfer':
				self::_genericPostInfo($data, $details);
				$details[] = ['New reserver', User::find($data['to'])->toAnchor()];
			break;
			case 'cg_modify':
				$details[] = ['Appearance', self::_getAppearanceLink($data['appearance_id'])];
				$CG = ColorGroup::find($data['group_id']);
				if (empty($CG)){
					$details[] = ['Color group ID', '#'.$data['group_id']];
					$details[] = ['Still exists', false];
				}
				else $details[] = ['Group', "{$CG->label} (#{$data['group_id']})"];
				if (isset($data['newlabel']))
					$details[] = ['Label', self::diff($data['oldlabel'] ?? '', $data['newlabel'])];
				if (isset($data['newcolors']))
					$details[] = ['Colors', self::diff($data['oldcolors'] ?? '', $data['newcolors'], 'block')];
			break;
			case 'cgs':
				$details[] = ['Action', self::$ACTIONS[$data['action']]];
				$details[] = ['Color group ID', '#'.$data['group_id']];
				$details[] = ['Label', $data['label']];
				$details[] = ['Appearance', self::_getAppearanceLink($data['appearance_id'])];
				if (isset($data['order']))
					$details[] = ['Ordering index', $data['order']];
			break;
			case 'cg_order':
				$details[] = ['Appearance', self::_getAppearanceLink($data['appearance_id'])];
				$details[] = ['Order', self::diff($data['oldgroups'], $data['newgroups'], 'block', new FineDiff\Granularity\Paragraph())];
			break;
			case 'appearance_modify':
				$details[] = ['Appearance', self::_getAppearanceLink($data['appearance_id'])];
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
			case 'da_namechange':
				$User = User::find($data['user_id']);
				$newIsCurrent = $User->name === $data['new'];
				$details[] = ['User', $User->toAnchor()];
				if ($newIsCurrent)
					$details[] = ['Old name', $data['old']];
				else {
					$details[] = ['Name', Logs::diff($data['old'], $data['new'])];
				}
			break;
			case 'video_broken':
				$show = Show::find($data['show_id']);
				$details[] = ['Episode', $show->toAnchor()];
				$url = VideoProvider::getEmbed(new ShowVideo([
					'provider' => $data['provider'],
					'id' => $data['id'],
				]), VideoProvider::URL_ONLY);
				$details[] = ['Link', "<a href='$url'>$url</a>"];
			break;
			case 'cm_modify':
				$details[] = ['Appearance', self::_getAppearanceLink($data['appearance_id'])];

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
							if (!isset($v) || $i === 'id'){
								unset($data[$key][$k][$i]);
								continue;
							}
						}
					}
				}

				$olddata = !empty($data['olddata']) ? JSON::encode($data['olddata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
				$newdata = !empty($data['newdata']) ? JSON::encode($data['newdata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
				if ($olddata || $newdata){
					$diff = self::diff($olddata, $newdata, 'block', new FineDiff\Granularity\Sentence());
					$diff = preg_replace(new RegExp('([^/>])(d[a-z\d]{6,})'),'$1<a href="http://fav.me/$2">$2</a>',$diff);
					$details[] = ['Metadata changes', $diff];
				}
			break;
			case 'post_break':
			case 'post_fix':
				self::_genericPostInfo($data, $details);
			break;
			case 'staff_limits':
				$details[] = ['For', User::find($data['user_id'])->toAnchor()];
				$details[] = ['Limitation', UserSettingForm::INPUT_MAP[$data['setting']]['options']['desc']];
				$icon = $data['allow'] ? 'tick' : 'times';
				$text = $data['allow'] ? 'Now allowed' : 'Now disallowed';
				$keyc = $data['allow'] ? self::KEYCOLOR_SUCCESS : self::KEYCOLOR_ERROR;
				$details[] = ["<span class='typcn typcn-$icon'></span> $text", self::SKIP_VALUE, $keyc];
			break;
			case 'pcg_gift_refund':
				$gift = PCGSlotGift::find($data['gift_id']);
				$details[] = ['Sender', $gift->sender->toAnchor()];
				$details[] = ['Recipient', $gift->receiver->toAnchor()];
				$details[] = ['Amount', CoreUtils::makePlural('slot', $gift->amount, PREPEND_NUMBER).' ('.CoreUtils::makePlural('point', $gift->amount*10, PREPEND_NUMBER).')'];
				$details[] = ['Sent', Time::tag($gift->created_at)];
			break;
			case 'failed_auth_attempts':
				$browser = !empty($data['user_agent']) ? CoreUtils::detectBrowser($data['user_agent']) : null;
				$details[] = ['Browser', $browser === null ? 'Unknown' : "{$browser['browser_name']} {$browser['browser_ver']} on {$browser['platform']}"];
				if (!empty($data['user_agent']))
					$details[] = ['User Agent', $data['user_agent']];
			break;
			default:
				$details[] = ["<span class=\"typcn typcn-warning\"></span> Couldn't process details", 'No data processor defined for this entry type', self::KEYCOLOR_ERROR];
				$details[] = ['Raw details', '<pre>'.var_export($data, true).'</pre>'];
			break;
		}

		return ['details' => $details];
	}

	private static function get_post(array $data):?Post {
		if ($data['type'] === 'post')
			return Post::find($data['id']);
		else {
			if ($data['type'] === 'request')
				DB::$instance->where('requested_by IS NOT NULL');
			else DB::$instance->where('requested_by IS NULL');
			return DB::$instance->where('old_id', $data['old_id'])->getOne('posts');
		}
	}

	const REF_KEY = 'Reference';

	/**
	 * @param array     $data
	 * @param array     $details
	 *
	 * @throws \Exception
	 */
	private static function _genericPostInfo(array $data, array &$details){
		$post = self::get_post($data);

		if (empty($post)){
			$details[] = self::_getReferenceForDeletedPost($data);
			$details[] = ['<span class="typcn typcn-info-large"></span> No longer exists', self::SKIP_VALUE, self::KEYCOLOR_INFO];
		}
		else {
			$details[] = [self::REF_KEY, $post->toAnchor("Post #{$post->id}")];
			$details[] = ['Kind', CoreUtils::capitalize($post->kind)];
			$details[] = ['Posted under', $post->show->toAnchor()];
			$details[] = ['Posted by', $post->poster->toAnchor()];
			if ($post->reserved_by !== null)
				$details[] = ['Reserved by', $post->reserver->toAnchor()];
			else $details[] = ['Reserved', false];
		}
	}

	private static function _getReferenceForDeletedPost(array $data, ?string $force_type = null){
		$new_post = isset($data['id']);
		$type = $new_post ? 'Post' : ($force_type ?? $data['type']);
		$id = $new_post ? $data['id'] : $data['old_id'];
		return [self::REF_KEY, CoreUtils::capitalize($type)." #$id"];
	}

	/**
	 * @param int $id
	 *
	 * @return string
	 */
	private static function _getAppearanceLink(int $id):string {
		$ID = "#$id";
		try {
			$Appearance = Appearance::find($id);
		}
		catch(RecordNotFound $e){
			return $ID;
		}

		if (!empty($Appearance))
			$ID = "<a href='{$Appearance->toURL()}'>".CoreUtils::escapeHTML($Appearance->label)."</a> ($ID)";

		return $ID;
	}

	private static function _arrangeNewOld($data){
		$newOld = [];
		unset($data['entryid'], $data['target']);
		foreach ($data as $k => $v){
			if (\is_null($v))
				continue;

			$thing = mb_substr($k, 3);
			$type = mb_substr($k, 0, 3);
			if (!isset($newOld[$thing]))
				$newOld[$thing] = [];
			$newOld[$thing][$type] = $thing === 'twoparter' ? !!$v : $v;
		}
		return $newOld;
	}

	private static function _link($url, $blank = false){
		return "<a href='".CoreUtils::aposEncode($url)."' ".($blank?'target="_blank" rel="noopener"':'').">$url</a>";
	}

	public const LOCALHOST_IPS = ['::1', '127.0.0.1', '::ffff:127.0.0.1'];

	public const SEARCH_USER_LINK = '';

	/**
	 * Render log page <tbody> content
	 *
	 * @param Log[] $LogItems
	 *
	 * @return string
	 */
	public static function getTbody($LogItems):string {
		$HTML = '';
		if (\count($LogItems) > 0) foreach ($LogItems as $item){
			if (!empty($item->initiator)){
				$inituser = $item->actor;
				if (empty($inituser))
					$inituser = 'Deleted user';
				else {
					$sameUser = $inituser->id === Auth::$user->id;
					$me_class = $sameUser ? ' your-name' : '';
					$me_by = $sameUser ? ' you' : 'this user';
					$strongName = $sameUser ? "<strong title='You'>{$inituser->name}</strong>" : $inituser->name;
					$inituser = "<a class='search-user typcn typcn-zoom$me_class' title='Search for all entries by $me_by'></a> <a class='typcn typcn-user' href='{$inituser->toURL()}' title='Visit profile'></a> <span class='name'>$strongName</span>";
				}
			}
			else $inituser = '<a class="search-user typcn typcn-zoom" title="Search for all entries by the server"></a> <spac class="name">Web server</spac>';

			if ($item->ip !== GDPR_IP_PLACEHOLDER){
				$ip = \in_array(strtolower($item->ip), self::LOCALHOST_IPS, true) ? 'localhost' : $item->ip;
				$ownIP = $item->ip === $_SERVER['REMOTE_ADDR'];
				$strongIP = $ownIP ? "<strong title='Your current IP'>$ip</strong>" : $ip;
				$ip = "<a class='typcn typcn-zoom search-ip".($ownIP?' your-ip':'')."' title='Search for all entries from this IP'></a> <span class='address'>$strongIP</span>";
			}
			else $ip = '<em>IP wiped (GDPR)</em>';

			$event = self::LOG_DESCRIPTION[$item->reftype] ?? $item->reftype;
			if (isset($item->refid))
				$event = '<span class="expand-section typcn typcn-plus">'.$event.'</span>';
			$ts = Time::tag($item->timestamp, Time::TAG_EXTENDED);

			$HTML .= <<<HTML
		<tr>
			<td class='entryid'>{$item->entryid}</td>
			<td class='timestamp'>$ts<span class="dynt-el"></span></td>
			<td class='ip'>$inituser<br>$ip</td>
			<td class='reftype'>$event</td>
		</tr>
HTML;
		}
		else $HTML = '<tr><td colspan="4"><div class="notice info align-center"><label>No log items found</label></td></tr>';

		return $HTML;
	}

	public static function validateRefType($key, $optional = false, $method_get = false){
		return (new Input($key,function($value){
			if (!isset(self::LOG_DESCRIPTION[$value]))
				return Input::ERROR_INVALID;
		}, [
			Input::IS_OPTIONAL => $optional,
			Input::SOURCE => $method_get ? 'GET' : 'POST',
		]))->out();
	}

	public static function diff(string $old, string $new, $type = 'inline', FineDiff\Granularity\Granularity $gran = null):string {
		if (!isset($gran))
			$gran = new FineDiff\Granularity\Character;
		else if ($gran instanceof FineDiff\Granularity\Paragraph)
			$old .= "\n";
		$diff = str_replace('\n',"\n",(new FineDiff\Diff($gran))->render($old, $new));

		return "<span class='btn darkblue view-switch' title='Left/Right click to change view mode'>diff</span><div class='log-diff $type'>$diff</div>";
	}
}
