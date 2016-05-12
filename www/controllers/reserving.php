<?php

	if (!POST_REQUEST) CoreUtils::NotFound();
	$match = array();
	if (empty($data) || !regex_match(new RegExp('^(requests?|reservations?)(?:/(\d+))?$'),$data,$match))
		CoreUtils::Respond('Invalid request');

	$noaction = true;
	$canceling = $finishing = $unfinishing = $adding = $deleteing = $locking = false;
	foreach (array('cancel','finish','unfinish','add','delete','lock') as $k){
		if (isset($_REQUEST[$k])){
			$var = "{$k}ing";
			$$var = true;
			$noaction = false;
			break;
		}
	}
	$type = rtrim($match[1],'s');

	// TODO Untangle this 3-course meal's worth of spaghetti
	if (!$adding){
		if (!isset($match[2]))
			 CoreUtils::Respond("Missing $type ID");
		$ID = intval($match[2], 10);
		$Thing = $Database->where('id', $ID)->getOne("{$type}s");
		if (empty($Thing)) CoreUtils::Respond("There's no $type with that ID");

		if (!empty($Thing['lock']) && !Permission::Sufficient('developer'))
			CoreUtils::Respond('This post has been approved and cannot be edited or removed.'.(Permission::Sufficient('inspector') && !Permission::Sufficient('developer')?' If a change is necessary please ask the developer to do it for you.':''));

		if ($deleteing && $type === 'request'){
			if (!Permission::Sufficient('inspector')){
				if (!$signedIn || $Thing['requested_by'] !== $currentUser['id'])
					CoreUtils::Respond();

				if (!empty($Thing['reserved_by']))
					CoreUtils::Respond('You cannot delete a request that has already been reserved by a group member');
			}

			if (!$Database->where('id', $Thing['id'])->delete('requests'))
				CoreUtils::Respond(ERR_DB_FAIL);

			Log::Action('req_delete',array(
				'season' => $Thing['season'],
				'episode' => $Thing['episode'],
				'id' => $Thing['id'],
				'label' => $Thing['label'],
				'type' => $Thing['type'],
				'requested_by' => $Thing['requested_by'],
				'posted' => $Thing['posted'],
				'reserved_by' => $Thing['reserved_by'],
				'deviation_id' => $Thing['deviation_id'],
				'lock' => $Thing['lock'],
			));

			CoreUtils::Respond(true);
		}

		if (!Permission::Sufficient('member')) CoreUtils::Respond();
		$update = array(
			'reserved_by' => null,
			'reserved_at' => null
		);

		if (!empty($Thing['reserved_by'])){
			$isUserReserver = $Thing['reserved_by'] === $currentUser['id'];
			if ($noaction){
				if ($isUserReserver)
					CoreUtils::Respond("You've already reserved this $type");
				else CoreUtils::Respond("This $type has already been reserved by somepony else");
			}
			if ($locking){
				if (empty($Thing['deviation_id']))
					CoreUtils::Respond('Only finished {$type}s can be locked');

				$Status = CoreUtils::IsDeviationInClub($Thing['deviation_id']);
				if ($Status !== true)
					CoreUtils::Respond(
						$Status === false
						? "The deviation has not been submitted to/accepted by the group yet"
						: "There was an issue while checking the acceptance status (Error code: $Status)"
					);

				if (!$Database->where('id', $Thing['id'])->update("{$type}s", array('lock' => 1)))
					CoreUtils::Respond("This $type is already approved", 1);

				Log::Action('post_lock',array(
					'type' => $type,
					'id' => $Thing['id']
				));

				$message = "The image appears to be in the group gallery and as such it is now marked as approved.";
				if ($isUserReserver)
					$message .= " Thank you for your contribution!<div class='align-center'><apan class='sideways-smiley-face'>;)</span></div>";
				CoreUtils::Respond($message, 1, array('canedit' => Permission::Sufficient('developer')));
			}
			if ($canceling)
				$unfinishing = true;
			if ($unfinishing){
				if (($canceling && !$isUserReserver) && Permission::Insufficient('inspector'))
					CoreUtils::Respond();

				if (!$canceling && !isset($_REQUEST['unbind'])){
					if ($type === 'reservation' && empty($Thing['preview']))
						CoreUtils::Respond('This reservation was added directly and cannot be marked un-finished. To remove it, check the unbind from user checkbox.');
					unset($update['reserved_by']);
				}

				if (($canceling || isset($_REQUEST['unbind'])) && $type === 'reservation'){
					if (!$Database->where('id', $Thing['id'])->delete('reservations'))
						CoreUtils::Respond(ERR_DB_FAIL);

					if (!$canceling)
						CoreUtils::Respond('Reservation deleted', 1);
				}
				if (!$canceling){
					if (isset($_REQUEST['unbind']) && $type === 'request'){
						if (Permission::Insufficient('inspector') && !$isUserReserver)
							CoreUtils::Respond('You cannot remove the reservation from this post');
					}
					else $update = array();
					$update['deviation_id'] = null;
				}
			}
			else if ($finishing){
				if (!$isUserReserver && !Permission::Sufficient('inspector'))
					CoreUtils::Respond();
				$update = Posts::CheckRequestFinishingImage($Thing['reserved_by']);
			}
		}
		else if ($finishing) CoreUtils::Respond("This $type has not yet been reserved");
		else if (!$canceling){
			User::ReservationLimitCheck();

			if (!empty($_POST['post_as'])){
				if (!Permission::Sufficient('developer'))
					CoreUtils::Respond('Reserving as other users is only allowed to the developer');

				$post_as = CoreUtils::Trim($_POST['post_as']);
				if (!$USERNAME_REGEX->match($post_as))
					CoreUtils::Respond('Username format is invalid');

				$User = User::Get($post_as, 'name');
				if (empty($User))
					CoreUtils::Respond('User does not exist');
				if (!Permission::Sufficient('member', $User['role']))
					CoreUtils::Respond('User does not have permission to reserve posts');

				$update['reserved_by'] = $User['id'];
			}
			else $update['reserved_by'] = $currentUser['id'];
			$update['reserved_at'] = date('c');
		}

		if ((!$canceling || $type !== 'reservation') && !$Database->where('id', $Thing['id'])->update("{$type}s",$update))
			CoreUtils::Respond('Nothing has been changed');

		if (!$canceling && ($finishing || $unfinishing)){
			if (isset($update['requested_by']))
				$Thing['requested_by'] = $update['requested_by'];
			$out = array();
			if ($finishing && $type === 'request'){
				$u = User::Get($Thing['requested_by'],'id','name');
				if (!empty($u) && $Thing['requested_by'] !== $currentUser['id'])
					$out['message'] = "<p class='align-center'>You may want to mention <strong>{$u['name']}</strong> in the deviation description to let them know that their request has been fulfilled.</p>";
			}
			CoreUtils::Respond($out);
		}

		if ($type === 'request' || ($type === 'reservation' && $canceling)){
			$ReserverID = $Thing['reserved_by'];
			if ($type === 'request'){
				foreach ($update as $k => $v)
					$Thing[$k] = $v;
				$r = array('li' => Posts::GetLi($Thing, true));
			}
			else $r = array('remove' => true);
			if (isset($_POST['FROM_PROFILE'])){
				$sameUser = $signedIn && $ReserverID === $currentUser['id'];
				$r['pendingReservations'] = User::GetPendingReservationsHTML($ReserverID, $sameUser);
			}
			CoreUtils::Respond($r);
		}
		else CoreUtils::Respond(true);
	}
	else if ($type === 'reservation'){
		if (!Permission::Sufficient('inspector'))
			CoreUtils::Respond();
		$_POST['allow_overwrite_reserver'] = true;
		$insert = Posts::CheckRequestFinishingImage();
		if (empty($insert['reserved_by']))
			$insert['reserved_by'] = $currentUser['id'];
		$epdata = Episode::ParseID($_GET['add']);
		if (empty($epdata))
			CoreUtils::Respond('Invalid episode');
		$epdata = Episode::GetActual($epdata['season'], $epdata['episode']);
		if (empty($epdata))
			CoreUtils::Respond('The specified episode does not exist');
		$insert['season'] = $epdata['season'];
		$insert['episode'] = $epdata['episode'];

		if (!$Database->insert('reservations', $insert))
			CoreUtils::Respond(ERR_DB_FAIL);
		CoreUtils::Respond('Reservation added',1);
	}
	else CoreUtils::NotFound();
