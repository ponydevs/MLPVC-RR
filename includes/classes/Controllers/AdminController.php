<?php

namespace App\Controllers;
use App\Auth;
use App\CoreUtils;
use App\CSRFProtection;
use App\HTTP;
use App\Input;
use App\Logs;
use App\Models\DiscordMember;
use App\Models\User;
use App\Pagination;
use App\Permission;
use App\Posts;
use App\RegExp;
use App\Response;
use App\Users;
use RestCord\DiscordClient;

class AdminController extends Controller {
	public $do = 'admin';

	function __construct(){
		parent::__construct();

		if (!Permission::sufficient('staff'))
			CoreUtils::notFound();
	}

	function index(){
		CoreUtils::loadPage(array(
			'title' => 'Admin Area',
			'do-css',
			'do-js',
		), $this);
	}

	function logs(){
		global $Database, $LogItems, $Pagination;

		$type = Logs::validateRefType('type', true, true);
		if (isset($_GET['type']) && preg_match(new RegExp('/^[a-z_]+$/'), $_GET['type']) && isset(Logs::$LOG_DESCRIPTION[$_GET['type']]))
			$type = $_GET['type'];

		if (!isset($_GET['by']))
			$by = null;
		else switch(strtolower(CoreUtils::trim($_GET['by']))){
			case 'me':
			case 'you':
				$initiator = Auth::$user->id;
				$by = 'you';
			break;
			case 'web server':
				$initiator = 0;
				$by = 'Web server';
			break;
			default:
				$by = Users::validateName('by', null, true);
				if (isset($by)){
					$by = Users::get($by, 'name', 'id,name');
					$initiator = $by->id;
					$by = $initiator === Auth::$user->id ? 'me' : $by->name;
				}
		};


		$title = '';
		$whereArgs = [];
		$q = array();
		if (isset($_GET['js']))
			$q[] = 'js='.$_GET['js'];
		if (isset($type)){
			$whereArgs[] = array('reftype', $type);
			if (isset($q)){
				$q[] = "type=$type";
				$title .= Logs::$LOG_DESCRIPTION[$type].' entries ';
			}
		}
		else if (isset($q))
			$q[] = 'type='.CoreUtils::FIXPATH_EMPTY;
		if (isset($initiator)){
			$_params = $initiator === 0 ? array('"initiator" IS NULL') : array('initiator',$initiator);
			$whereArgs[] = $_params;
			if (isset($q) && isset($by)){
				$q[] = "by=$by";
				$title .= (!isset($type)?'Entries ':'')."by $by ";
			}
		}
		else if (isset($q))
			$q[] = 'by='.CoreUtils::FIXPATH_EMPTY;

		foreach ($whereArgs as $arg)
			$Database->where(...$arg);
		$Pagination = new Pagination('admin/logs', 20, $Database->count('log'));
		$heading = 'Global logs';
		if (!empty($title))
			$title .= '- ';
		$title .= "Page {$Pagination->page} - $heading - Admin Area";
		CoreUtils::fixPath("/admin/logs/{$Pagination->page}".(!empty($q)?'?'.implode('&',$q):''));

		foreach ($whereArgs as $arg)
			$Database->where(...$arg);
		$LogItems = $Database
			->orderBy('timestamp')
			->orderBy('entryid')
			->get('log', $Pagination->getLimit());

		if (isset($_GET['js']))
			$Pagination->respond(Logs::getTbody($LogItems), '#logs tbody');

		CoreUtils::loadPage(array(
			'heading' => $heading,
			'title' => $title,
			'view' => "{$this->do}-logs",
			'css' => "{$this->do}-logs",
			'js' => array("{$this->do}-logs", 'paginate'),
			'import' => [
				'Pagination' => $Pagination,
				'LogItems' => $LogItems,
				'type' => $type,
				'by' => $by,
			],
		));
	}

	function logDetail($params){
		CSRFProtection::protect();

		if (!isset($params['id']) || !is_numeric($params['id']))
			Response::fail('Entry ID is missing or invalid');

		$entry = intval($params['id'], 10);

		global $Database;
		$MainEntry = $Database->where('entryid', $entry)->getOne('log');
		if (empty($MainEntry))
			Response::fail('Log entry does not exist');
		if (empty($MainEntry['refid']))
			Response::fail('There are no details to show', array('unlickable' => true));

		$Details = $Database->where('entryid', $MainEntry['refid'])->getOne("log__{$MainEntry['reftype']}");
		if (empty($Details)){
			error_log("Could not find details for entry {$MainEntry['reftype']}#{$MainEntry['refid']}, NULL-ing refid of Main#{$MainEntry['entryid']}");
			$Database->where('entryid', $MainEntry['entryid'])->update('log', array('refid' => null));
			Response::fail('Failed to retrieve details', array('unlickable' => true));
		}

		Response::done(Logs::formatEntryDetails($MainEntry,$Details));
	}

	function usefulLinks(){
		if (!POST_REQUEST){
			$heading = 'Manage useful links';
			CoreUtils::loadPage([
				'heading' => $heading,
				'title' => "$heading - Admin Area",
				'view' => "{$this->do}-usefullinks",
				'js' => ['Sortable',"{$this->do}-usefullinks"],
				'css' => $this->do,
			]);
		}

		CSRFProtection::protect();

		$action = $_GET['action'];
		$creating = $action === 'make';

		global $Database;

		if (!$creating){
			if (!isset($_GET['linkid']) || !is_numeric($_GET['linkid']))
				CoreUtils::notFound();
			$linkid = intval($_GET['linkid'],10);
			$Link = $Database->where('id', $linkid)->getOne('usefullinks');
			if (empty($Link))
				Response::fail('The specified link does not exist');
		}

		switch ($action){
			case 'get':
				Response::done(array(
					'label' => $Link['label'],
					'url' => $Link['url'],
					'title' => $Link['title'],
					'minrole' => $Link['minrole'],
				));
			case 'del':
				if (!$Database->where('id', $Link['id'])->delete('usefullinks'))
					Response::dbError();

				Response::done();
			break;
			case 'make':
			case 'set':
				$data = array();

				$label = (new Input('label','string',array(
					Input::IN_RANGE => [3,35],
					Input::CUSTOM_ERROR_MESSAGES => array(
						Input::ERROR_MISSING => 'Link label is missing',
						Input::ERROR_RANGE => 'Link label must be between @min and @max characters long',
					)
				)))->out();
				if ($creating || $Link['label'] !== $label){
					CoreUtils::checkStringValidity($label, 'Link label', INVERSE_PRINTABLE_ASCII_PATTERN);
					$data['label'] = $label;
				}

				$url = (new Input('url','url',array(
					Input::IN_RANGE => [3,255],
					Input::CUSTOM_ERROR_MESSAGES => array(
						Input::ERROR_MISSING => 'Link URL is missing',
						Input::ERROR_RANGE => 'Link URL must be between @min and @max characters long',
					)
				)))->out();
				if ($creating || $Link['url'] !== $url)
					$data['url'] = $url;

				$title = (new Input('title','string',array(
					Input::IS_OPTIONAL => true,
					Input::IN_RANGE => [3,255],
					Input::CUSTOM_ERROR_MESSAGES => array(
						Input::ERROR_RANGE => 'Link title must be between @min and @max characters long',
					)
				)))->out();
				if (!isset($title))
					$data['title'] = '';
				else if ($creating || $Link['title'] !== $title){
					CoreUtils::checkStringValidity($title, 'Link title', INVERSE_PRINTABLE_ASCII_PATTERN);
					$data['title'] = $title;
				}

				$minrole = (new Input('minrole',function($value){
					if (empty(Permission::ROLES_ASSOC[$value]) || !Permission::sufficient('user', $value))
						Response::fail();
				},array(
					Input::CUSTOM_ERROR_MESSAGES => array(
						Input::ERROR_MISSING => 'Minumum role is missing',
						Input::ERROR_INVALID => 'Minumum role (@value) is invalid',
					)
				)))->out();
				if ($creating || $Link['minrole'] !== $minrole)
					$data['minrole'] = $minrole;

				if (empty($data))
					Response::fail('Nothing was changed');
				$query = $creating
					? $Database->insert('usefullinks', $data)
					: $Database->where('id', $Link['id'])->update('usefullinks', $data);
				if (!$query)
					Response::dbError();

				Response::done();
			break;
			default: CoreUtils::notFound();
		}
	}

	function reorderUsefulLinks(){
		global $Database;

		CSRFProtection::protect();

		$list = (new Input('list','int[]',array(
			Input::CUSTOM_ERROR_MESSAGES => array(
				Input::ERROR_MISSING => 'Missing ordering information',
			)
		)))->out();
		$order = 1;
		foreach ($list as $id){
			if (!$Database->where('id', $id)->update('usefullinks', array('order' => $order++)))
				Response::fail("Updating link #$id failed, process halted");
		}

		Response::done();
	}

	function discord(){
		global $Database;
		if (!$Database->has('discord-members'))
			$this->_getDiscordMemberList();

		$heading = 'Discord Server Connections';
		CoreUtils::loadPage([
			'heading' => $heading,
			'title' => "$heading - Admin Area",
			'view' => "{$this->do}-discord",
			'css' => "{$this->do}-discord",
			'js' => "{$this->do}-discord",
			'import' => ['nav_dsc' => true],
		], $this);
	}

	function discordMemberList(){
		CSRFProtection::protect();

		global $Database;

		if (isset($_POST['update']))
			$this->_getDiscordMemberList(true);

		$members = $Database->get('discord-members');
		usort($members, function(DiscordMember $a, DiscordMember $b){
			$comp1 = $a->name <=> $b->name;
			if ($comp1 !== 0)
				return $comp1;
			return $a->discriminator <=> $b->discriminator;
		});
		$HTML = '';
		/** @var \App\Models\DiscordMember[] $members */
		foreach ($members as $member){
			$avatar = $member->getAvatarURL();
			$avatar = isset($avatar) ? "<img src='{$avatar}' alt='user avatar' class='user-avatar'>" : '';
			$un = CoreUtils::escapeHTML($member->username);
			$bound = isset($member->userid) ? " class='bound'" : '';
			$udata = '<span>'.(isset($member->nick) ? $member->nick : $member->username)."</span><span>{$member->username}#{$member->discriminator}</span>";
			$HTML .= <<<HTML
<li id="member-{$member->id}"$bound>
	$avatar
	<div class='user-data'>
		$udata
	</div>
</li>
HTML;
		}

		Response::done(['list' => $HTML]);
	}

	/** @var DiscordMember */
	private $_member;
	private function _discordSetMember($params){
		global $Database;

		CSRFProtection::protect();

		$this->_member = $Database->where('id', $params['id'])->getOne('discord-members');
		if (empty($this->_member))
			Response::fail('There\'s no member with this ID on record.');
	}

	function discordMemberLinkGet($params){
		$this->_discordSetMember($params);

		$resp = [];
		if (isset($this->_member->userid))
			$resp['boundto'] = Users::get($this->_member->userid,'id','id,name,avatar_url')->getProfileLink(User::LINKFORMAT_FULL);

		Response::done($resp);
	}

	function discordMemberLinkSet($params){
		$this->_discordSetMember($params);

		global $Database;

		$to = (new Input('to','username',[
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Username is missing',
				Input::ERROR_INVALID => 'Username (@value) is invalid',
			]
		]))->out();
		$user = Users::get($to, 'name');
		if (empty($user))
			Response::fail('The specified user does not exist');

		if (!$Database->where('id', $this->_member->id)->update('discord-members',[
			'userid' => $user->id
		])) Response::fail('Nothing has been changed');

		Response::done();
	}

	function discordMemberLinkDel($params){
		$this->_discordSetMember($params);

		global $Database;

		if (!isset($this->_member->userid))
			Response::fail('Member is not bound to any user');

		if (!$Database->where('id', $this->_member->id)->update('discord-members',[
			'userid' => null
		])) Response::fail('Nothing has been changed');

		Response::done();
	}

	private function _getDiscordMemberList(bool $skip_binding = false){
		global $Database;

		// TODO If we ever surpass 1000 Discord server members this will bite me in the backside
		$discord = new DiscordClient(['token' => DISCORD_BOT_TOKEN]);
		$members = $discord->guild->listGuildMembers(['guild.id' => DISCORD_SERVER_ID,'limit' => 1000]);
		$usrids = [];

		foreach ($members as $member){
			$ins = new DiscordMember([
				'id' => $member['user']['id'],
				'username' => $member['user']['username'],
				'discriminator' => $member['user']['discriminator'],
				'nick' => $member['nick'] ?? null,
				'avatar_hash' => $member['user']['avatar'] ?? null,
				'joined_at' => $member['joined_at'],
			]);

			$usrids[] = $ins->id;

			if ((isset($member['roles']) && count($member['roles']) > 1) || !empty($ins->nick))
				$ins->guessDAUser();
			$ins = $ins->toArray();

			if ($Database->where('id', $ins['id'])->has('discord-members')){
				$insid = $ins['id'];
				unset($ins['id']);
				if ($skip_binding)
					unset($ins['userid']);
				$Database->where('id', $insid)->update('discord-members', $ins);
			}
			else $Database->insert('discord-members', $ins);
		}

		if (count($usrids) > 0)
			$Database->where("id NOT IN ('".implode("','",$usrids)."')");
		$Database->delete('discord-members');
	}

	function massApprove(){
		global $Database;

		CSRFProtection::protect();

		$ids = (new Input('ids','int[]',array(
			Input::CUSTOM_ERROR_MESSAGES => array(
			    Input::ERROR_MISSING => 'List of deviation IDs is missing',
			    Input::ERROR_INVALID => 'List of deviation IDs (@value) is invalid',
			)
		)))->out();

		$list = "";
		foreach ($ids as $id)
			$list .= "'d".base_convert($id, 10, 36)."',";
		$list = rtrim($list, ',');

		$Posts = $Database->rawQuery(
			"SELECT 'request' as type, id, deviation_id FROM requests WHERE deviation_id IN ($list) && lock = false
			UNION ALL
			SELECT 'reservation' as type, id, deviation_id FROM reservations WHERE deviation_id IN ($list) && lock = false"
		);

		if (empty($Posts))
			Response::success('There were no posts in need of marking as approved');

		$approved = 0;
		foreach ($Posts as $p){
			if (CoreUtils::isDeviationInClub($p['deviation_id']) !== true)
				continue;

			Posts::approve($p['type'], $p['id']);
			$approved++;
		}

		if ($approved === 0)
			Response::success('All identified posts have already been approved');

		Response::success('Marked '.CoreUtils::makePlural('post', $approved, PREPEND_NUMBER).' as approved. To see which ones, check the <a href="/admin/logs/1?type=post_lock&by=you">list of posts you\'ve approved</a>.',array('reload' => true));
	}

	function recentPosts(){
		CSRFProtection::protect();

		Response::done(['html' => Posts::getMostRecentList(WRAP)]);
	}

	function wsdiag(){
		if (Permission::insufficient('developer'))
			CoreUtils::notFound();

		$heading = 'WebSocket Server Diagnostics';
		CoreUtils::loadPage([
			'heading' => $heading,
			'title' => "$heading - Admin Area",
			'view' => "{$this->do}-wsdiag",
			'js' => "{$this->do}-wsdiag",
			'css' => "{$this->do}-wsdiag",
			'import' => ['nav_wsdiag' => true],
		]);
	}
}
