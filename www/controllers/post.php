<?php

	if (!POST_REQUEST) CoreUtils::NotFound();
	if (!$signedIn) CoreUtils::Respond();
	CSRFProtection::Protect();

	$_match = array();
	if (regex_match(new RegExp('^([gs]et)-(request|reservation)/(\d+)$'), $data, $_match)){
		$thing = $_match[2];
		$Post = $Database->where('id', $_match[3])->getOne("{$thing}s");
		if (empty($Post))
			CoreUtils::Respond("The specified $thing does not exist");

		if (!(Permission::Sufficient('staff') || ($thing === 'request' && empty($Post['reserved_by']) && $Post['requested_by'] === $currentUser['id'])))
			CoreUtils::Respond();

		if ($_match[1] === 'get'){
			$response = array(
				'label' => $Post['label'],
			);
			if ($thing === 'request'){
				$response['type'] = $Post['type'];

				if (Permission::Sufficient('developer')){
					if (!empty($Post['reserved_at']))
						$response['reserved_at'] = date('c', strtotime($Post['reserved_at']));
					if (!empty($Post['finished_at']))
						$response['finished_at'] = date('c', strtotime($Post['finished_at']));
				}
			}
			if (Permission::Sufficient('developer'))
				$response['posted'] = date('c', strtotime($Post['posted']));
			CoreUtils::Respond($response);
		}

		$update = array();
		Posts::CheckPostDetails($thing, $update, $Post);

		if (empty($update))
			CoreUtils::Respond('Nothing was changed', 1);

		if (!$Database->where('id', $Post['id'])->update("{$thing}s", $update))
			CoreUtils::Respond(ERR_DB_FAIL);
		$Post = array_merge($Post, $update);
		CoreUtils::Respond(array('li' => Posts::GetLi($Post, $thing === 'request')));
	}
	else if (regex_match(new RegExp('^((?:un)?(?:finish|lock|reserve)|add|delete)-(request|reservation)s?/(\d+)$'),$data,$_match)){
		$type = $_match[2];
		$action = $_match[1];

		if (empty($_match[3]))
			 CoreUtils::Respond("Missing $type ID");
		$Post = $Database->where('id', $_match[3])->getOne("{$type}s");
		if (empty($Post)) CoreUtils::Respond("There's no $type with that ID");

		if (!empty($Post['lock']) && Permission::Insufficient('developer') && $action !== 'unlock')
			CoreUtils::Respond('This post has been approved and cannot be edited or removed.');

		if ($type === 'request' && $action === 'delete'){
			if (!Permission::Sufficient('staff')){
				if (!$signedIn || $Post['requested_by'] !== $currentUser['id'])
					CoreUtils::Respond();

				if (!empty($Post['reserved_by']))
					CoreUtils::Respond('You cannot delete a request that has already been reserved by a group member');
			}

			if (!$Database->where('id', $Post['id'])->delete('requests'))
				CoreUtils::Respond(ERR_DB_FAIL);

			Log::Action('req_delete',array(
				'season' => $Post['season'],
				'episode' => $Post['episode'],
				'id' => $Post['id'],
				'label' => $Post['label'],
				'type' => $Post['type'],
				'requested_by' => $Post['requested_by'],
				'posted' => $Post['posted'],
				'reserved_by' => $Post['reserved_by'],
				'deviation_id' => $Post['deviation_id'],
				'lock' => $Post['lock'],
			));

			CoreUtils::Respond(true);
		}

		if (Permission::Insufficient('member'))
			CoreUtils::Respond();

		$isUserReserver = $Post['reserved_by'] === $currentUser['id'];
		if (!empty($Post['reserved_by'])){
			switch ($action){
				case 'reserve':
					if (Posts::IsOverdue($Post)){
						$overdue = array(
							'reserved_by' => $Post['reserved_by'],
							'reserved_at' => $Post['reserved_at'],
						);
						$Post['reserved_by'] = null;
						break;
					}
					if ($isUserReserver)
						CoreUtils::Respond("You've already reserved this $type");
					CoreUtils::Respond("This $type has already been reserved by somepony else");
				break;
				case 'lock':
					if (empty($Post['deviation_id']))
						CoreUtils::Respond("Only finished {$type}s can be locked");

					$Status = CoreUtils::IsDeviationInClub($Post['deviation_id']);
					if ($Status !== true)
						CoreUtils::Respond(
							$Status === false
							? "The deviation has not been submitted to/accepted by the group yet"
							: "There was an issue while checking the acceptance status (Error code: $Status)"
						);

					if (!$Database->where('id', $Post['id'])->update("{$type}s", array('lock' => true)))
						CoreUtils::Respond(ERR_DB_FAIL);

					Log::Action('post_lock',array(
						'type' => $type,
						'id' => $Post['id']
					));

					$Post['lock'] = true;
					$response = array(
						'message' => "The image appears to be in the group gallery and as such it is now marked as approved.",
						'li' => Posts::GetLi($Post, $type === 'request'),
					);
					if ($isUserReserver)
						$response['message'] .= " Thank you for your contribution!<div class='align-center'><apan class='sideways-smiley-face'>;)</span></div>";
					CoreUtils::Respond($response);
				break;
				case 'unlock':
					if (Permission::Insufficient('staff'))
						CoreUtils::Respond();
					if (empty($Post['lock']))
						CoreUtils::Respond("This $type has not been approved yet");

					if (Permission::Insufficient('developer') && CoreUtils::IsDeviationInClub($Post['deviation_id']))
						CoreUtils::Respond("<a href='http://fav.me/{$Post['deviation_id']}' target='_blank'>This deviation</a> is part of the group gallery, which prevents the post from being unlocked.");

					$Database->where('id', $Post['id'])->update("{$type}s", array('lock' => false));
					$Post['lock'] = false;

					CoreUtils::Respond(true);
				break;
				case 'unreserve':
					if (!$isUserReserver && Permission::Insufficient('staff'))
						CoreUtils::Respond();

					if ($type === 'request'){
						$update = array(
							'reserved_by' => null,
							'reserved_at' => null,
						);
						break;
					}
					else $_REQUEST['unbind'] = true;
				case 'unfinish':
					if (!$isUserReserver && Permission::Insufficient('staff'))
						CoreUtils::Respond();

					if (isset($_REQUEST['unbind'])){
						if ($type === 'reservation'){
							if (!$Database->where('id', $Post['id'])->delete('reservations'))
								CoreUtils::Respond(ERR_DB_FAIL);

							CoreUtils::Respond('Reservation deleted', 1);
						}
						else if ($type === 'request' && Permission::Insufficient('staff') && !$isUserReserver)
							CoreUtils::Respond('You cannot remove the reservation from this post');

						$update = array(
							'reserved_by' => null,
							'reserved_at' => null,
							'finished_at' => null,
						);
					}
					else if ($type === 'reservation' && empty($Post['preview']))
						CoreUtils::Respond('This reservation was added directly and cannot be marked unfinished. To remove it, check the unbind from user checkbox.');

					$update['deviation_id'] = null;
					$update['finished_at'] = null;

					if (!$Database->where('id', $Post['id'])->update("{$type}s",$update))
						CoreUtils::Respond(ERR_DB_FAIL);

					CoreUtils::Respond(true);
				break;
				case 'finish':
					if (!$isUserReserver && Permission::Insufficient('staff'))
						CoreUtils::Respond();

					$update = Posts::CheckRequestFinishingImage($Post['reserved_by']);
					$update['finished_at'] = date('c');

					if (!$Database->where('id', $Post['id'])->update("{$type}s",$update))
						CoreUtils::Respond(ERR_DB_FAIL);

					$message = '';
					if (isset($update['lock'])){
						$message .= "<p>The image appears to be in the group gallery already, so we marked it as approved.</p>";

						Log::Action('post_lock',array(
							'type' => $type,
							'id' => $Post['id']
						));
					}
					if ($type === 'request'){
						$u = User::Get($Post['requested_by'],'id','name');
						if (!empty($u) && $Post['requested_by'] !== $currentUser['id'])
							$message .= "<p>You may want to let <strong>{$u['name']}</strong> know that their request has been fulfilled.</p>";
					}
					CoreUtils::Respond($message ?? true, 1);
				break;
			}
		}
		else if ($action === 'finish')
			CoreUtils::Respond("This $type has not been reserved by anypony yet");

		if (empty($Post['reserved_by']) && $type === 'request' && $action === 'reserve'){
			User::ReservationLimitCheck();

			$update['reserved_by'] = $currentUser['id'];

			if (Permission::Sufficient('developer')){
				$post_as = Posts::ValidatePostAs();
				if (isset($post_as)){
					$User = User::Get($post_as, 'name');
					if (empty($User))
						CoreUtils::Respond('User does not exist');
					if (!Permission::Sufficient('member', $User['role']))
						CoreUtils::Respond('User does not have permission to reserve posts');

					$update['reserved_by'] = $User['id'];
				}
			}
			$update['reserved_at'] = date('c');
		}

		if (empty($update) || !$Database->where('id', $Post['id'])->update("{$type}s",$update))
			CoreUtils::Respond('Nothing has been changed<br>If you tried to do something, then this is actually an error, which you should <a href="#feedback" class="send-feedback">tell us</a> about.');

		if (!empty($overdue))
			Log::Action('res_overtake',array_merge(
				array(
					'id' => $Post['id'],
					'type' => $type
				),
				$overdue
			));

		if ($type === 'request'){
			$Post = array_merge($Post, $update);
			$response = array('li' => Posts::GetLi($Post, true));
			if (isset($_POST['FROM_PROFILE']))
				$response['pendingReservations'] = User::GetPendingReservationsHTML($Post['reserved_by'], $isUserReserver);
			CoreUtils::Respond($response);
		}
		else CoreUtils::Respond(true);
	}
	else if ($data === 'add-reservation'){
		if (!Permission::Sufficient('staff'))
			CoreUtils::Respond();
		$_POST['allow_overwrite_reserver'] = true;
		$insert = Posts::CheckRequestFinishingImage();
		if (empty($insert['reserved_by']))
			$insert['reserved_by'] = $currentUser['id'];

		$epdata = (new Input('epid','epid',array(
			'errors' => array(
				Input::$ERROR_MISSING => 'Episode identifier is missing',
				Input::$ERROR_INVALID => 'Episode identifier (@value) is invalid',
			)
		)))->out();
		$epdata = Episode::GetActual($epdata['season'], $epdata['episode']);
		if (empty($epdata))
			CoreUtils::Respond('The specified episode does not exist');
		$insert['season'] = $epdata['season'];
		$insert['episode'] = $epdata['episode'];

		if (!$Database->insert('reservations', $insert))
			CoreUtils::Respond(ERR_DB_FAIL);
		CoreUtils::Respond('Reservation added',1);
	}
	else if (regex_match(new RegExp('^set-(request|reservation)-image/(\d+)$'), $data, $_match)){
		$thing = $_match[1];
		$Post = $Database->where('id', $_match[2])->getOne("{$thing}s");
		if (empty($Post))
			CoreUtils::Respond("The specified $thing does not exist");
		if ($Post['lock'])
			CoreUtils::Respond('This post is locked, its image cannot be changed.');

		if (Permission::Insufficient('staff'))
			switch ($thing){
				case 'request':
					if ($Post['requested_by'] !== $currentUser['id'] || !empty($Post['reserved_by']))
						CoreUtils::Respond();
				break;
				case 'reservation':
					if ($Post['reserved_by'] !== $currentUser['id'])
						CoreUtils::Respond();
				break;
			};

		$image_url = (new Input('image_url','string',array(
			'errors' => array(
				Input::$ERROR_MISSING => 'Image URL is missing',
			)
		)))->out();
		$Image = Posts::CheckImage($image_url, $Post);

		// Check image availability
		if (!@getimagesize($Image->preview)){
			sleep(1);
			if (!@getimagesize($Image->preview))
				CoreUtils::Respond("<p class='align-center'>The specified image doesn't seem to exist. Please verify that you can reach the URL below and try again.<br><a href='{$Image->preview}' target='_blank'>{$Image->preview}</a></p>");
		}

		if (!$Database->where('id', $Post['id'])->update("{$thing}s",array(
			'preview' => $Image->preview,
			'fullsize' => $Image->fullsize,
		))) CoreUtils::Respond(ERR_DB_FAIL);

		Log::Action('img_update',array(
			'id' => $Post['id'],
			'thing' => $thing,
			'oldpreview' => $Post['preview'],
			'oldfullsize' => $Post['fullsize'],
			'newpreview' => $Image->preview,
			'newfullsize' => $Image->fullsize,
		));

		CoreUtils::Respond(array('preview' => $Image->preview));
	}
	else if (regex_match(new RegExp('^fix-(request|reservation)-stash/(\d+)$'), $data, $_match)){
		if (!Permission::Sufficient('staff'))
			CoreUtils::Respond();

		$thing = $_match[1];
		$Post = $Database->where('id', $_match[2])->getOne("{$thing}s");
		if (empty($Post))
			CoreUtils::Respond("The specified $thing does not exist");

		// Link is already full size, we're done
		if (regex_match($FULLSIZE_MATCH_REGEX, $Post['fullsize']))
			CoreUtils::Respond(array('fullsize' => $Post['fullsize']));

		// Reverse submission lookup
		$StashItem = $Database
			->where('fullsize', $Post['fullsize'])
			->orWhere('preview', $Post['preview'])
			->getOne('deviation_cache','id,fullsize,preview');
		if (empty($StashItem['id']))
			CoreUtils::Respond('Stash URL lookup failed');

		try {
			$fullsize = CoreUtils::GetStashFullsizeURL($StashItem['id']);
			if (!is_string($fullsize)){
				if ($fullsize === 404){
					$Database->where('provider', 'sta.sh')->where('id', $StashItem['id'])->delete('deviation_cache');
					$Database->where('preview', $StashItem['preview'])->orWhere('fullsize', $StashItem['fullsize'])->update('requests',array(
						'fullsize' => null,
						'preview' => null,
					));
					$Database->where('preview', $StashItem['preview'])->orWhere('fullsize', $StashItem['fullsize'])->update('reservations',array(
						'fullsize' => null,
						'preview' => null,
					));
					CoreUtils::Respond('The original image has been deleted from Sta.sh',0,array('rmdirect' => true));
				}
				else throw new Exception("Code $fullsize; Could not find the URL");
			}
		}
		catch (Exception $e){
			CoreUtils::Respond('Error while finding URL: '.$e->getMessage());
		}
		// Check image availability
		if (!@getimagesize($fullsize)){
			sleep(1);
			if (!@getimagesize($fullsize))
				CoreUtils::Respond("The specified image doesn't seem to exist. Please verify that you can reach the URL below and try again.<br><a href='$fullsize' target='_blank'>$fullsize</a>");
		}

		if (!$Database->where('id', $Post['id'])->update("{$thing}s",array(
			'fullsize' => $fullsize,
		))) CoreUtils::Respond(ERR_DB_FAIL);

		CoreUtils::Respond(array('fullsize' => $fullsize));
	}

	$type = (new Input('what',function($value){
		if (!in_array($value,Posts::$TYPES))
			return Input::$ERROR_INVALID;
	},array(
		'optional' => true,
		'errors' => array(
			Input::$ERROR_INVALID => 'Post type (@value) is invalid',
		)
	)))->out();
	if (empty($type) && $type === 'reservation'){
		if (Permission::Insufficient('member'))
			CoreUtils::Respond();
		User::ReservationLimitCheck();
	}

	$Image = Posts::CheckImage(Posts::ValidateImageURL());

	if (empty($type))
		CoreUtils::Respond(array(
			'preview' => $Image->preview,
			'title' => $Image->title,
		));

	$insert = array(
		'preview' => $Image->preview,
		'fullsize' => $Image->fullsize,
	);

	$season = Episode::ValidateSeason();
	$episode = Episode::ValidateEpisode();
	$epdata = Episode::GetActual($season, $episode, ALLOW_SEASON_ZERO);
	if (empty($epdata))
		CoreUtils::Respond("The specified episode (S{$season}E$episode) does not exist");
	$insert['season'] = $epdata['season'];
	$insert['episode'] = $epdata['episode'];

	$ByID = $currentUser['id'];
	if (Permission::Sufficient('developer')){
		$username = Posts::ValidatePostAs();
		if (isset($username)){
			$PostAs = User::Get($username, 'name', 'id,role');

			if (empty($PostAs))
				CoreUtils::Respond('The user you wanted to post as does not exist');

			if ($type === 'reservation' && !Permission::Sufficient('member', $PostAs['role']) && !isset($_POST['allow_nonmember']))
				CoreUtils::Respond('The user you wanted to post as is not a club member, so you want to post as them anyway?',0,array('canforce' => true));

			$ByID = $PostAs['id'];
		}
	}

	$insert[$type === 'reservation' ? 'reserved_by' : 'requested_by'] = $ByID;
	Posts::CheckPostDetails($type, $insert);

	$PostID = $Database->insert("{$type}s",$insert,'id');
	if (!$PostID)
		CoreUtils::Respond(ERR_DB_FAIL);
	CoreUtils::Respond(array('id' => $PostID));
