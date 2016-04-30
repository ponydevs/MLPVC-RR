<?php

	if (strtolower($data) === 'immortalsexgod')
		$data = 'DJDavid98';

	if (POST_REQUEST){
		if (!Permission::Sufficient('inspector')) CoreUtils::Respond();
		CSRFProtection::Protect();

		if (empty($data)) CoreUtils::NotFound();

		if (regex_match(new RegExp('^newgroup/'.USERNAME_PATTERN.'$'),$data,$_match)){
			$targetUser = User::Get($_match[1], 'name');
			if (empty($targetUser))
				CoreUtils::Respond('User not found');

			if ($targetUser['id'] === $currentUser['id'])
				CoreUtils::Respond("You cannot modify your own group");
			if (!Permission::Sufficient($targetUser['role']))
				CoreUtils::Respond('You can only modify the group of users who are in the same or a lower-level group than you');
			if ($targetUser['role'] === 'ban')
				CoreUtils::Respond('This user is banished, and must be un-banished before changing their group.');

			if (!isset($_POST['newrole']))
				CoreUtils::Respond('The new group is not specified');
			$newgroup = trim($_POST['newrole']);
			if (empty(Permission::$ROLES[$newgroup]))
				CoreUtils::Respond('The specified group does not exist');
			if ($targetUser['role'] === $newgroup)
				CoreUtils::Respond(array('already_in' => true));

			User::UpdateRole($targetUser,$newgroup);

			CoreUtils::Respond(true);
		}
		else if (regex_match(new RegExp('^sessiondel/(\d+)$'),$data,$_match)){
			$Session = $Database->where('id', $_match[1])->getOne('sessions');
			if (empty($Session))
				CoreUtils::Respond('This session does not exist');
			if ($Session['user'] !== $currentUser['id'] && !Permission::Sufficient('inspector'))
				CoreUtils::Respond('You are not allowed to delete this session');

			if (!$Database->where('id', $Session['id'])->delete('sessions'))
				CoreUtils::Respond('Session could not be deleted');
			CoreUtils::Respond('Session successfully removed',1);
		}
		else if (regex_match(new RegExp('^(un-)?banish/'.USERNAME_PATTERN.'$'), $data, $_match)){
			$Action = (empty($_match[1]) ? 'Ban' : 'Un-ban').'ish';
			$action = strtolower($Action);
			$un = $_match[2];

			$targetUser = User::Get($un, 'name');
			if (empty($targetUser)) CoreUtils::Respond('User not found');

			if ($targetUser['id'] === $currentUser['id']) CoreUtils::Respond("You cannot $action yourself");
			if (Permission::Sufficient('inspector', $targetUser['role']))
				CoreUtils::Respond("You cannot $action people within the inspector or any higher group");
			if ($action == 'banish' && $targetUser['role'] === 'ban' || $action == 'un-banish' && $targetUser['role'] !== 'ban')
				CoreUtils::Respond("This user has already been {$action}ed");

			if (empty($_POST['reason']))
				CoreUtils::Respond('Please specify a reason');
			$reason = trim($_POST['reason']);
			$rlen = strlen($reason);
			if ($rlen < 5 || $rlen > 255)
				CoreUtils::Respond('Reason length must be between 5 and 255 characters');

			$changes = array('role' => $action == 'banish' ? 'ban' : 'user');
			$Database->where('id', $targetUser['id'])->update('users', $changes);
			Log::Action($action,array(
				'target' => $targetUser['id'],
				'reason' => $reason
			));
			$changes['role'] = Permission::$ROLES_ASSOC[$changes['role']];
			$changes['badge'] = Permission::LabelInitials($changes['role']);
			if ($action == 'banish') CoreUtils::Respond($changes);
			else CoreUtils::Respond("We welcome {$targetUser['name']} back with open hooves!", 1, $changes);
		}
		else CoreUtils::StatusCode(404, AND_DIE);
	}

	if (empty($data)){
		if ($signedIn) $un = $currentUser['name'];
		else $MSG = 'Sign in to view your settings';
	}
	else if (regex_match($USERNAME_REGEX, $data, $_match))
		$un = $_match[1];

	if (!isset($un)){
		if (!isset($MSG)) $MSG = 'Invalid username';
	}
	else $User = User::Get($un, 'name');

	if (empty($User)){
		if (isset($User) && $User === false){
			$MSG = "User does not exist";
			$SubMSG = "Check the name for typos and try again";
		}
		if (!isset($MSG)){
			$MSG = 'Local user data missing';
			if (!$signedIn){
				$exists = 'exsists on DeviantArt';
				if (isset($un)) $exists = "<a href='http://$un.deviantart.com/'>$exists</a>";
				$SubMSG = "If this user $exists, sign in to import their details.";
			}
		}
		$canEdit = $sameUser = false;
	}
	else {
		$sameUser = $signedIn && $User['id'] === $currentUser['id'];
		$canEdit = !$sameUser && Permission::Sufficient('inspector') && Permission::Sufficient($User['role']);
		$pagePath = "/@{$User['name']}";
		CoreUtils::FixPath($pagePath);
	}

	if (isset($MSG)) CoreUtils::StatusCode(404);
	else {
		if ($sameUser){
			$CurrentSession = $currentUser['Session'];
			$Database->where('id != ?',array($CurrentSession['id']));
		}
		$Sessions = $Database
			->where('user',$User['id'])
			->orderBy('lastvisit','DESC')
			->get('sessions',null,'id,created,lastvisit,platform,browser_name,browser_ver,user_agent,scope');
	}

	$settings = array(
		'title' => !isset($MSG) ? ($sameUser?'Your':CoreUtils::Posess($User['name'])).' '.($sameUser || $canEdit?'account':'profile') : 'Account',
		'no-robots',
		'do-css',
		'js' => array('user'),
	);
	if ($canEdit) $settings['js'][] = 'user-manage';
	CoreUtils::LoadPage($settings);
