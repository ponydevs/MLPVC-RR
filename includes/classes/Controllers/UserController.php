<?php

namespace App\Controllers;
use App\CoreUtils;
use App\CSRFProtection;
use App\HTTP;
use App\Input;
use App\Logs;
use App\Permission;
use App\Posts;
use App\RegExp;
use App\Response;
use App\UserPrefs;
use App\Users;

class UserController extends Controller {
	public $do = 'user';

	function profile($params){
		global $signedIn, $currentUser, $USERNAME_REGEX, $Database, $User, $sameUser;

		$data = $params['name'] ?? null;

		if (empty($data)){
			if ($signedIn) $un = $currentUser->name;
			else $MSG = 'Sign in to view your settings';
		}
		else if (preg_match($USERNAME_REGEX, $data, $_match))
			$un = $_match[1];

		if (!isset($un)){
			if (!isset($MSG))
				$MSG = 'Invalid username';
		}
		else $User = Users::get($un, 'name');

		if (empty($User)){
			if (isset($User) && $User === false){
				$MSG = "User does not exist";
				$SubMSG = "Check the name for typos and try again";
			}
			else if (!isset($MSG)){
				$MSG = 'Local user data missing';
				if (!$signedIn){
					$exists = 'exists on DeviantArt';
					if (isset($un))
						$exists = "<a href='http://$un.deviantart.com/'>$exists</a>";
					$SubMSG = "If this user $exists, sign in to import their details.";
				}
			}
			$canEdit = $sameUser = false;
		}
		else {
			$sameUser = $signedIn && $User->id === $currentUser->id;
			$canEdit = !$sameUser && Permission::sufficient('staff') && Permission::sufficient($User->role);
			$pagePath = "/@{$User->name}";
			CoreUtils::fixPath($pagePath);
		}

		if (isset($MSG))
			HTTP::statusCode(404);
		else {
			if ($sameUser){
				$CurrentSession = $currentUser->Session;
				$Database->where('id != ?',array($CurrentSession['id']));
			}
			$Sessions = $Database
				->where('user',$User->id)
				->orderBy('lastvisit','DESC')
				->get('sessions',null,'id,created,lastvisit,platform,browser_name,browser_ver,user_agent,scope');
		}

		$settings = array(
			'title' => !isset($MSG) ? ($sameUser?'Your':CoreUtils::posess($User->name)).' '.($sameUser || $canEdit?'account':'profile') : 'Account',
			'no-robots',
			'do-css',
			'js' => array('user'),
			'import' => [
				'User' => $User,
				'canEdit' => $canEdit,
				'sameUser' => $sameUser,
				'Sessions' => $Sessions ?? null,
			],
		);
		if (isset($CurrentSession))
			$settings['import']['CurrentSession'] = $CurrentSession;
		if (isset($MSG))
			$settings['import']['MSG'] = $MSG;
		if (isset($SubMSG))
			$settings['import']['SubMSG'] = $SubMSG;
		if ($canEdit)
			$settings['js'][] = 'user-manage';
		$showSuggestions = !empty($User) && $User->getPendingReservationCount() < 4;
		if ($showSuggestions){
			$settings['js'][] = 'user-suggestion';
			$settings['css'][] = 'user-suggestion';
		}
		$settings['import']['showSuggestions'] = $showSuggestions;
		CoreUtils::loadPage($settings, $this);
	}

	function awaitingApproval($params){
		global $signedIn, $currentUser;

		CSRFProtection::protect();

		if (!isset($params['name']))
			Response::fail('Missing username');

		$targetUser = Users::get($params['name'], 'name');
		if (empty($targetUser))
			Response::fail('User not found');

		$sameUser = $signedIn && $currentUser->id === $targetUser->id;
		Response::done(['html' => Users::getAwaitingApprovalHTML($targetUser, $sameUser)]);
	}

	function suggestion(){
		global $Database;

		CSRFProtection::protect();

		if (Permission::insufficient('user'))
			Response::fail('You must be signed in to use this feature.');

		$postIDs = $Database->rawQuery(
			'SELECT id FROM requests
			WHERE deviation_id IS NULL && (reserved_by IS NULL OR reserved_at < NOW() - INTERVAL \'3 WEEK\')');
		$drawArray = [];
		foreach ($postIDs as $post)
			$drawArray[] = $post['id'];
		$chosen = $drawArray[array_rand($drawArray)];
		/** @var $Request \App\Models\Request */
		$Request = $Database->where('id', $chosen)->getOne('requests');
		Response::done(array('suggestion' => Posts::getSuggestionLi($Request)));
	}

	function discordVerify(){
		global $Database, $currentUser;

		if (!empty($_GET['token'])){
			$targetUser = $Database->where('key','discord_token')->where('value',$_GET['token'])->getOne('user_prefs','user');
			if (empty($targetUser))
				Response::fail('Invalid token');

			$user = Users::get($targetUser['user']);
			UserPrefs::set('discord_token','true',$user->id);
			Response::done(array(
				'name' => $user->name,
				'role' => $user->role,
			));
		}

		$ismember = Permission::sufficient('member', $currentUser->role);
		$isstaff = Permission::sufficient('staff', $currentUser->role);
		if (!$ismember || $isstaff){
			UserPrefs::set('discord_token','');
			Response::fail(!$ismember ? 'You are not a club member' : 'Staff members cannot use this feature');
		}

		$token = UserPrefs::get('discord_token');
		if ($token === 'true')
			Response::fail("You have already been verified using this automated method. If - for yome reason - you still don’t have the Club Members role please ask for assistance in the <strong>#support</strong> channel.");

		if (empty($token)){
			$token = preg_replace(new RegExp('[^a-z\d]','i'),'',base64_encode(random_bytes(12)));
			UserPrefs::set('discord_token', $token);
		}

		Response::done(array('token' => $token));
	}

	function sessionDel($params){
		global $Database, $currentUser;

		CSRFProtection::protect();

		if (!isset($params['id']) || !is_numeric($params['id']))
			Response::fail('Missing session ID');

		$Session = $Database->where('id', $params['id'])->getOne('sessions');
		if (empty($Session))
			Response::fail('This session does not exist');
		if ($Session['user'] !== $currentUser->id && !Permission::sufficient('staff'))
			Response::fail('You are not allowed to delete this session');

		if (!$Database->where('id', $Session['id'])->delete('sessions'))
			Response::fail('Session could not be deleted');
		Response::success('Session successfully removed');
	}

	function setGroup($params){
		global $currentUser;

		CSRFProtection::protect();
		if (Permission::insufficient('staff'))
			Response::fail();

		if (!isset($params['name']))
			Response::fail('Missing username');

		$targetUser = Users::get($params['name'], 'name');
		if (empty($targetUser))
			Response::fail('User not found');

		if ($targetUser->id === $currentUser->id)
			Response::fail("You cannot modify your own group");
		if (!Permission::sufficient($targetUser->role))
			Response::fail('You can only modify the group of users who are in the same or a lower-level group than you');
		if ($targetUser->role === 'ban')
			Response::fail('This user is banished, and must be un-banished before changing their group.');

		$newgroup = (new Input('newrole',function($value){
			if (empty(Permission::ROLES_ASSOC[$value]))
				return Input::ERROR_INVALID;
		},array(
			Input::CUSTOM_ERROR_MESSAGES => array(
				Input::ERROR_MISSING => 'The new group is not specified',
				Input::ERROR_INVALID => 'The specified group (@value) does not exist',
			)
		)))->out();
		if ($targetUser->role === $newgroup)
			Response::done(array('already_in' => true));

		$targetUser->updateRole($newgroup);

		Response::done();
	}

	private function _banishAction($params, bool $banish){
		global $Database, $currentUser;

		CSRFProtection::protect();
		if (Permission::insufficient('staff'))
			Response::fail();

		if (!isset($params['name']))
			Response::fail('Missing username');

		$Action = ($banish ? 'Ban' : 'Un-ban').'ish';
		$action = strtolower($Action);

		$targetUser = Users::get($params['name'], 'name');
		if (empty($targetUser)) Response::fail('User not found');

		if ($targetUser->id === $currentUser->id)
			Response::fail("You cannot $action yourself");
		if (Permission::sufficient('staff', $targetUser->role))
			Response::fail("You cannot $action people within the assistant or any higher group");
		if ($action == 'banish' && $targetUser->role === 'ban' || $action == 'un-banish' && $targetUser->role !== 'ban')
			Response::fail("This user has already been {$action}ed");

		$reason = (new Input('reason','string',array(
			Input::IN_RANGE => [5,255],
			Input::CUSTOM_ERROR_MESSAGES => array(
				Input::ERROR_MISSING => 'Please specify a reason',
				Input::ERROR_RANGE => 'Reason length must be between @min and @max characters'
			)
		)))->out();

		$changes = array('role' => $action == 'banish' ? 'ban' : 'user');
		$Database->where('id', $targetUser->id)->update('users', $changes);
		Logs::logAction($action,array(
			'target' => $targetUser->id,
			'reason' => $reason
		));
		$changes['role'] = Permission::ROLES_ASSOC[$changes['role']];

		if ($action == 'banish')
			Response::done();

		Response::success("We welcome {$targetUser->name} back with open hooves!");
	}

	function banish($params){
		$this->_banishAction($params, true);
	}

	function unbanish($params){
		$this->_banishAction($params, false);
	}

	function checkCGSlots($params){
		CSRFProtection::protect();

		if (!isset($params['name']))
			Response::fail('Missing username');

		$targetUser = Users::get($params['name'], 'name');
		if (empty($targetUser))
			Response::fail('User not found');

		$avail = $targetUser->getPCGAvailableSlots(false);
		if ($avail === 0)
			Response::fail('You do not have any available slots left. You can always fulfill additional requests to get more.');
		Response::done();
	}
}
