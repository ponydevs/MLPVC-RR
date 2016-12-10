<?php

use App\CoreUtils;
use App\CSRFProtection;
use App\Models\User;
use App\HTTP;
use App\Input;
use App\Logs;
use App\Permission;
use App\RegExp;
use App\Response;
use App\UserPrefs;
use App\Users;

if (POST_REQUEST){
	if ($data === 'discord-verify'){
		if (!empty($_GET['token'])){
			$targetUser = $Database->where('key','discord_token')->where('value',$_GET['token'])->getOne('user_prefs','user');
			if (empty($targetUser))
				Response::Fail('Invalid token');

			$user = Users::Get($targetUser['user']);
			UserPrefs::Set('discord_token','true',$user->id);
			Response::Done(array(
				'name' => $user->name,
				'role' => $user->role,
			));
		}

		$ismember = Permission::Sufficient('member', $currentUser->role);
		$isstaff = Permission::Sufficient('staff', $currentUser->role);
		if (!$ismember || $isstaff){
			UserPrefs::Set('discord_token','');
			Response::Fail(!$ismember ? 'You are not a club member' : 'Staff members cannot use this feature');
		}

		$token = UserPrefs::Get('discord_token');
		if ($token === 'true')
			Response::Fail("You have already been verified using this automated method. If - for yome reason - you still don't have the Club Members role please ask for assistance in the <strong>#support</strong> channel.");

		if (empty($token)){
			$token = preg_replace(new RegExp('[^a-z\d]','i'),'',base64_encode(random_bytes(12)));
			UserPrefs::Set('discord_token', $token);
		}

		Response::Done(array('token' => $token));
	}

	CSRFProtection::Protect();

	if (empty($data)) CoreUtils::notFound();

	if (preg_match(new RegExp('^sessiondel/(\d+)$'),$data,$_match)){
		$Session = $Database->where('id', $_match[1])->getOne('sessions');
		if (empty($Session))
			Response::Fail('This session does not exist');
		if ($Session['user'] !== $currentUser->id && !Permission::Sufficient('staff'))
			Response::Fail('You are not allowed to delete this session');

		if (!$Database->where('id', $Session['id'])->delete('sessions'))
			Response::Fail('Session could not be deleted');
		Response::Success('Session successfully removed');
	}

	if (!Permission::Sufficient('staff')) Response::Fail();

	if (preg_match(new RegExp('^newgroup/'.USERNAME_PATTERN.'$'),$data,$_match)){
		$targetUser = Users::Get($_match[1], 'name');
		if (empty($targetUser))
			Response::Fail('User not found');

		if ($targetUser->id === $currentUser->id)
			Response::Fail("You cannot modify your own group");
		if (!Permission::Sufficient($targetUser->role))
			Response::Fail('You can only modify the group of users who are in the same or a lower-level group than you');
		if ($targetUser->role === 'ban')
			Response::Fail('This user is banished, and must be un-banished before changing their group.');

		$newgroup = (new Input('newrole',function($value){
			if (!isset(Permission::$ROLES_ASSOC[$value]))
				return Input::ERROR_INVALID;
		},array(
			Input::CUSTOM_ERROR_MESSAGES => array(
				Input::ERROR_MISSING => 'The new group is not specified',
				Input::ERROR_INVALID => 'The specified group (@value) does not exist',
			)
		)))->out();
		if ($targetUser->role === $newgroup)
			Response::Done(array('already_in' => true));

		$targetUser->updateRole($newgroup);

		Response::Done();
	}
	else if (preg_match(new RegExp('^(un-)?banish/'.USERNAME_PATTERN.'$'), $data, $_match)){
		$Action = (empty($_match[1]) ? 'Ban' : 'Un-ban').'ish';
		$action = strtolower($Action);
		$un = $_match[2];

		$targetUser = Users::Get($un, 'name');
		if (empty($targetUser)) Response::Fail('User not found');

		if ($targetUser->id === $currentUser->id)
			Response::Fail("You cannot $action yourself");
		if (Permission::Sufficient('staff', $targetUser->role))
			Response::Fail("You cannot $action people within the assistant or any higher group");
		if ($action == 'banish' && $targetUser->role === 'ban' || $action == 'un-banish' && $targetUser->role !== 'ban')
			Response::Fail("This user has already been {$action}ed");

		$reason = (new Input('reason','string',array(
			Input::IN_RANGE => [5,255],
			Input::CUSTOM_ERROR_MESSAGES => array(
				Input::ERROR_MISSING => 'Please specify a reason',
				Input::ERROR_RANGE => 'Reason length must be between @min and @max characters'
			)
		)))->out();

		$changes = array('role' => $action == 'banish' ? 'ban' : 'user');
		$Database->where('id', $targetUser->id)->update('users', $changes);
		Logs::action($action,array(
			'target' => $targetUser->id,
			'reason' => $reason
		));
		$changes['role'] = Permission::$ROLES_ASSOC[$changes['role']];
		$changes['badge'] = Permission::LabelInitials($changes['role']);

		if ($action == 'banish')
			Response::Done($changes);

		Response::Success("We welcome {$targetUser->name} back with open hooves!", $changes);
	}
	else CoreUtils::notFound();
}

if (strtolower($data) === 'immortalsexgod')
	$data = 'DJDavid98';

if (empty($data)){
	if ($signedIn) $un = $currentUser->name;
	else $MSG = 'Sign in to view your settings';
}
else if (preg_match($USERNAME_REGEX, $data, $_match))
	$un = $_match[1];

if (!isset($un)){
	if (!isset($MSG)) $MSG = 'Invalid username';
}
else $User = Users::Get($un, 'name');

if (empty($User)){
	if (isset($User) && $User === false){
		$MSG = "User does not exist";
		$SubMSG = "Check the name for typos and try again";
	}
	if (!isset($MSG)){
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
	$canEdit = !$sameUser && Permission::Sufficient('staff') && Permission::Sufficient($User->role);
	$pagePath = "/@{$User->name}";
	CoreUtils::fixPath($pagePath);
}

if (isset($MSG)) HTTP::StatusCode(404);
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
);
if ($canEdit) $settings['js'][] = 'user-manage';
CoreUtils::loadPage($settings);
