<?php

namespace App\Controllers;
use App\Auth;
use App\CoreUtils;
use App\CSRFProtection;
use App\DB;
use App\Input;
use App\Logs;
use App\Models\DiscordMember;
use App\Models\UsefulLink;
use App\Models\User;
use App\Pagination;
use App\Permission;
use App\Posts;
use App\RegExp;
use App\Response;
use App\Users;
use IPTools\IP;
use RestCord\DiscordClient;

class AdminController extends Controller {
	public $do = 'admin';

	public function __construct(){
		parent::__construct();

		if (!Permission::sufficient('staff'))
			CoreUtils::notFound();
	}

	public function index(){
		CoreUtils::loadPage([
			'title' => 'Admin Area',
			'do-css',
			'do-js',
		], $this);
	}

	public function logs(){
		global $LogItems, $Pagination;

		$type = Logs::validateRefType('type', true, true);
		/** @noinspection NotOptimalIfConditionsInspection */
		if (isset($_GET['type']) && preg_match(new RegExp('/^[a-z_]+$/'), $_GET['type']) && isset(Logs::$LOG_DESCRIPTION[$_GET['type']]))
			$type = $_GET['type'];

		$ip = null;
		if (!isset($_GET['by']))
			$by = null;
		else {
			$_GET['by'] = strtolower(CoreUtils::trim($_GET['by']));
			switch($_GET['by']){
				case 'me':
				case 'you':
					$initiator = Auth::$user->id;
					$by = 'you';
				break;
				case 'my ip':
				case 'your ip':
					$ip = $_SERVER['REMOTE_ADDR'];
				break;
				case 'web server':
					$initiator = 0;
					$by = 'Web server';
				break;
				default:
					$by = Users::validateName('by', null, true, true);
					if ($by !== null){
						$by = Users::get($by, 'name');
						if (!empty($by)){
							$initiator = $by->id;
							$by = $initiator === Auth::$user->id ? 'me' : $by->name;
						}
						else $by = null;
					}
					else {
						try {
							$ip = IP::parse($_GET['by']);
						}
						catch (\Throwable $e){ }
						if ($ip !== null)
							$ip = (string)$ip;
					}
			}
		}

		$title = '';
		$whereArgs = [];
		$q = [];
		if ($type !== null){
			$whereArgs[] = ['reftype', $type];
			$q[] = "type=$type";
			$title .= Logs::$LOG_DESCRIPTION[$type].' entries ';
		}
		else if (isset($q))
			$q[] = 'type='.CoreUtils::FIXPATH_EMPTY;
		if (isset($initiator)){
			$_params = $initiator === 0 ? ['"initiator" IS NULL'] : ['initiator', $initiator];
			$whereArgs[] = $_params;
			if (isset($by)){
				$q[] = "by=$by";
				$title .= ($type === null?'Entries ':'')."by $by ";
			}
		}
		else if (isset($ip)){
			$whereArgs[] = ['ip', in_array($ip, Logs::LOCALHOST_IPS, true) ? Logs::LOCALHOST_IPS : $ip];
			$q[] = "by=$ip";
			$title .= ($type === null?'Entries ':'')."from $ip ";
		}
		else $q[] = 'by='.CoreUtils::FIXPATH_EMPTY;

		foreach ($whereArgs as $arg)
			DB::$instance->where(...$arg);
		$Pagination = new Pagination('admin/logs', 25, DB::$instance->count('log'));
		$heading = 'Global logs';
		if (!empty($title))
			$title .= '- ';
		$title .= "Page {$Pagination->page} - $heading - Admin Area";
		CoreUtils::fixPath("/admin/logs/{$Pagination->page}".(!empty($q)?'?'.implode('&',$q):''));

		foreach ($whereArgs as $arg)
			DB::$instance->where(...$arg);
		$LogItems = DB::$instance
			->orderBy('timestamp','DESC')
			->orderBy('entryid','DESC')
			->get('log', $Pagination->getLimit());

		$Pagination->respondIfShould(Logs::getTbody($LogItems), '#logs tbody');

		CoreUtils::loadPage([
			'heading' => $heading,
			'title' => $title,
			'view' => "{$this->do}-logs",
			'css' => "{$this->do}-logs",
			'js' => ["{$this->do}-logs", 'paginate'],
			'import' => [
				'Pagination' => $Pagination,
				'LogItems' => $LogItems,
				'type' => $type,
				'by' => $by,
				'ip' => $ip,
			],
		]);
	}

	public function logDetail($params){
		CSRFProtection::protect();

		if (!isset($params['id']) || !is_numeric($params['id']))
			Response::fail('Entry ID is missing or invalid');

		$entry = intval($params['id'], 10);

		$MainEntry = DB::$instance->where('entryid', $entry)->getOne('log');
		if (empty($MainEntry))
			Response::fail('Log entry does not exist');
		if (empty($MainEntry['refid']))
			Response::fail('There are no details to show', ['unlickable' => true]);

		$Details = DB::$instance->where('entryid', $MainEntry['refid'])->getOne("log__{$MainEntry['reftype']}");
		if (empty($Details)){
			error_log("Could not find details for entry {$MainEntry['reftype']}#{$MainEntry['refid']}, NULL-ing refid of Main#{$MainEntry['entryid']}");
			DB::$instance->where('entryid', $MainEntry['entryid'])->update('log', ['refid' => null]);
			Response::fail('Failed to retrieve details', ['unlickable' => true]);
		}

		Response::done(Logs::formatEntryDetails($MainEntry,$Details));
	}

	public function usefulLinks(){
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

		if (!$creating){
			if (!isset($_GET['linkid']) || !is_numeric($_GET['linkid']))
				CoreUtils::notFound();
			$linkid = intval($_GET['linkid'],10);
			$Link = UsefulLink::find($linkid);
			if (empty($Link))
				Response::fail('The specified link does not exist');
		}

		switch ($action){
			case 'get':
				Response::done([
					'label' => $Link->label,
					'url' => $Link->url,
					'title' => $Link->title,
					'minrole' => $Link->minrole,
				]);
			case 'del':
				if (!DB::$instance->where('id', $Link->id)->delete('useful_links'))
					Response::dbError();

				Response::done();
			break;
			case 'make':
			case 'set':
				$data = [];

				$label = (new Input('label','string', [
					Input::IN_RANGE => [3,35],
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Link label is missing',
						Input::ERROR_RANGE => 'Link label must be between @min and @max characters long',
					]
				]))->out();
				if ($creating || $Link->label !== $label){
					CoreUtils::checkStringValidity($label, 'Link label', INVERSE_PRINTABLE_ASCII_PATTERN);
					$data['label'] = $label;
				}

				$url = (new Input('url','url', [
					Input::IN_RANGE => [3,255],
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Link URL is missing',
						Input::ERROR_RANGE => 'Link URL must be between @min and @max characters long',
					]
				]))->out();
				if ($creating || $Link->url !== $url)
					$data['url'] = $url;

				$title = (new Input('title','string', [
					Input::IS_OPTIONAL => true,
					Input::IN_RANGE => [3,255],
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_RANGE => 'Link title must be between @min and @max characters long',
					]
				]))->out();
				if (!isset($title))
					$data['title'] = '';
				else if ($creating || $Link->title !== $title){
					CoreUtils::checkStringValidity($title, 'Link title', INVERSE_PRINTABLE_ASCII_PATTERN);
					$data['title'] = $title;
				}

				$minrole = (new Input('minrole',function($value){
					if (empty(Permission::ROLES_ASSOC[$value]) || !Permission::sufficient('user', $value))
						Response::fail();
				}, [
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Minumum role is missing',
						Input::ERROR_INVALID => 'Minumum role (@value) is invalid',
					]
				]))->out();
				if ($creating || $Link->minrole !== $minrole)
					$data['minrole'] = $minrole;

				if (empty($data))
					Response::fail('Nothing was changed');
				$query = $creating
					? UsefulLink::create($data)
					: $Link->update_attributes($data);
				if (!$query)
					Response::dbError();

				Response::done();
			break;
			default: CoreUtils::notFound();
		}
	}

	public function reorderUsefulLinks(){
		CSRFProtection::protect();

		$list = (new Input('list','int[]', [
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Missing ordering information',
			]
		]))->out();
		$order = 1;
		foreach ($list as $id){
			if (!UsefulLink::find($id)->update_attributes(['order' => $order++]))
				Response::fail("Updating link #$id failed, process halted");
		}

		Response::done();
	}

	public function discord(){
		if (!DB::$instance->has('discord_members'))
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

	public function discordMemberList(){
		CSRFProtection::protect();

		if (isset($_POST['update']))
			$this->_getDiscordMemberList(true);

		$members = DiscordMember::all();
		usort($members, function(DiscordMember $a, DiscordMember $b){
			$comp1 = $a->name <=> $b->name;
			if ($comp1 !== 0)
				return $comp1;
			return $a->discriminator <=> $b->discriminator;
		});
		$HTML = '';
		/** @var \App\Models\DiscordMember[] $members */
		foreach ($members as $member){
			$avatar = $member->avatar_url;
			$avatar = isset($avatar) ? "<img src='{$avatar}' alt='user avatar' class='user-avatar'>" : '';
			$un = CoreUtils::escapeHTML($member->username);
			$bound = !empty($member->user_id) ? 'class="bound"' : '';
			$udata = "<span>{$member->name}</span><span>{$member->username}#{$member->discriminator}</span>";
			$HTML .= <<<HTML
<li id="member-{$member->id}" $bound>
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
		CSRFProtection::protect();

		$this->_member = DiscordMember::find($params['id']);
		if (empty($this->_member))
			Response::fail('There\'s no member with this ID on record.');
	}

	public function discordMemberLinkGet($params){
		$this->_discordSetMember($params);

		$resp = [];
		if ($this->_member->user_id !== null)
			$resp['boundto'] = $this->_member->user->toAnchor(User::WITH_AVATAR);

		Response::done($resp);
	}

	public function discordMemberLinkSet($params){
		$this->_discordSetMember($params);

		$to = (new Input('to','username',[
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Username is missing',
				Input::ERROR_INVALID => 'Username (@value) is invalid',
			]
		]))->out();
		$user = Users::get($to, 'name');
		if (empty($user))
			Response::fail('The specified user does not exist');

		if (!$this->_member->update_attributes([
			'userid' => $user->id
		])) Response::fail('Nothing has been changed');

		Response::done();
	}

	public function discordMemberLinkDel($params){
		$this->_discordSetMember($params);

		if ($this->_member->user_id === null)
			Response::fail('Member is not bound to any user');

		if (!$this->_member->update_attributes([
			'user_id' => null
		])) Response::fail('Nothing has been changed');

		Response::done();
	}

	private function _getDiscordMemberList(bool $skip_binding = false){
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

			if (!empty($ins->nick) || (isset($member['roles']) && count($member['roles']) > 1))
				$ins->guessDAUser();
			$ins = $ins->to_array([
				'except' => ['name','avatar_url'],
			]);

			if (DiscordMember::exists($ins['id'])){
				$insid = $ins['id'];
				unset($ins['id']);
				if ($skip_binding)
					unset($ins['user_id']);
				DiscordMember::find($insid)->update_attributes($ins);
			}
			else DiscordMember::create($ins);
		}

		if (count($usrids) > 0)
			DB::$instance->where("id NOT IN ('".implode("','",$usrids)."')");
		DB::$instance->delete('discord_members');
	}

	public function massApprove(){
		CSRFProtection::protect();

		$ids = (new Input('ids','int[]', [
			Input::CUSTOM_ERROR_MESSAGES => [
			    Input::ERROR_MISSING => 'List of deviation IDs is missing',
			    Input::ERROR_INVALID => 'List of deviation IDs (@value) is invalid',
			]
		]))->out();

		$list = '';
		foreach ($ids as $id)
			$list .= "'d".base_convert($id, 10, 36)."',";
		$list = rtrim($list, ',');

		$Posts = DB::$instance->query(
			"SELECT 'request' as type, id, deviation_id FROM requests WHERE deviation_id IN ($list) AND lock = false
			UNION ALL
			SELECT 'reservation' as type, id, deviation_id FROM reservations WHERE deviation_id IN ($list) AND lock = false"
		);

		if (empty($Posts))
			Response::success('There were no posts in need of marking as approved');

		$approved = 0;
		$notInCLub = 0;
		foreach ($Posts as $p){
			if (CoreUtils::isDeviationInClub($p['deviation_id']) !== true){
				$notInCLub++;
				continue;
			}

			Posts::approve($p['type'], $p['id']);
			$approved++;
		}

		if ($approved === 0){
			if ($notInCLub === 0)
				Response::success('All identified posts have already been approved');
			else Response::fail('None of the posts have been added to the gallery yet');
		}

		Response::success('Marked '.CoreUtils::makePlural('post', $approved, PREPEND_NUMBER).' as approved. To see which ones, check the <a href="/admin/logs/1?type=post_lock&by=you">list of posts you\'ve approved</a>.', ['html' => Posts::getMostRecentList(NOWRAP)]);
	}

	public function wsdiag(){
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
