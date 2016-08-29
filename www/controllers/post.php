<?php

	if (!POST_REQUEST) CoreUtils::NotFound();
	if (!$signedIn) Response::Fail();
	CSRFProtection::Protect();

	$_match = array();
	if (regex_match(new RegExp('^([gs]et)-(request|reservation)/(\d+)$'), $data, $_match)){
		$thing = $_match[2];
		$Post = $Database->where('id', $_match[3])->getOne("{$thing}s");
		if (empty($Post))
			Response::Fail("The specified $thing does not exist");

		if (!(Permission::Sufficient('staff') || ($thing === 'request' && empty($Post['reserved_by']) && $Post['requested_by'] === $currentUser['id'])))
			Response::Fail();

		if ($_match[1] === 'get'){
			$response = array(
				'label' => $Post['label'],
			);
			if ($thing === 'request'){
				$response['type'] = $Post['type'];

				if (Permission::Sufficient('developer') && !empty($Post['reserved_by']))
					$response['reserved_at'] = !empty($Post['reserved_at']) ? date('c', strtotime($Post['reserved_at'])) : '';
			}
			if (Permission::Sufficient('developer')){
				$response['posted'] = date('c', strtotime($Post['posted']));
				if (!empty($Post['reserved_by']) && !empty($Post['deviation_id']))
						$response['finished_at'] = !empty($Post['finished_at']) ? date('c', strtotime($Post['finished_at'])) : '';
			}
			Response::Done($response);
		}

		$update = array();
		Posts::CheckPostDetails($thing, $update, $Post);

		if (empty($update))
			Response::Success('Nothing was changed');

		if (!$Database->where('id', $Post['id'])->update("{$thing}s", $update))
			Response::DBError();
		$Post = array_merge($Post, $update);
		Response::Done(array('li' => Posts::GetLi($Post, $thing === 'request')));
	}
	if (regex_match(new RegExp('^reload-(request|reservation)/(\d+)$'), $data, $_match)){
		$thing = $_match[1];
		$Post = $Database->where('id', $_match[2])->getOne("{$thing}s");
		if (empty($Post))
			Response::Fail("The specified $thing does not exist");

		Response::Done(array('li' => Posts::GetLi($Post, $thing === 'request', isset($_POST['FROM_PROFILE']), true)));
	}
	else if (regex_match(new RegExp('^((?:un)?(?:finish|lock|reserve)|add|delete|pls-transfer)-(request|reservation)s?/(\d+)$'),$data,$_match)){
		$type = $_match[2];
		$action = $_match[1];

		if (empty($_match[3]))
			 Response::Fail("Missing $type ID");
		$Post = $Database->where('id', $_match[3])->getOne("{$type}s");
		if (empty($Post)) Response::Fail("There's no $type with the ID {$_match[3]}");

		if (!empty($Post['lock']) && Permission::Insufficient('developer') && $action !== 'unlock')
			Response::Fail('This post has been approved and cannot be edited or removed.');

		if ($type === 'request' && $action === 'delete'){
			if (!Permission::Sufficient('staff')){
				if (!$signedIn || $Post['requested_by'] !== $currentUser['id'])
					Response::Fail();

				if (!empty($Post['reserved_by']))
					Response::Fail('You cannot delete a request that has already been reserved by a group member');
			}

			if (!$Database->where('id', $Post['id'])->delete('requests'))
				Response::DBError();

			if (!empty($Post['reserved_by']))
				Posts::ClearTransferAttempts($Post, $type, 'del');

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

			Response::Done();
		}

		if (Permission::Insufficient('member'))
			Response::Fail();

		if ($action === 'pls-transfer'){
			$reserved_by = $Post['reserved_by'] ?? null;
			$checkIfUserCanReserve = function(&$message, &$data) use ($Post, $reserved_by, $type, $currentUser){
				Posts::ClearTransferAttempts($Post, $type, 'free', $currentUser['id'], $reserved_by);
				if (!User::ReservationLimitExceeded(RETURN_AS_BOOL)){
					$message .= '<br>Would you like to reserve it now?';
					$data = array('canreserve' => true);
				}
				else {
					$message .= "<br>However, you have 4 reservations already which means you can't reserve any more posts. Please review your pending reservations on your <a href='/user'>Account page</a> and cancel/finish at least one before trying to take on another.";
					$data = array();
				}
			};

			if (empty($reserved_by)){
				$message = 'This post is not reserved by anyone so there no need to ask for anyone\'s confirmation.';
				$checkIfUserCanReserve($message, $data, 'overdue');
				Response::Fail($message, $data);
			}
			if ($reserved_by === $currentUser['id'])
				Response::Fail("You've already reserved this $type");
			if (Posts::IsOverdue($Post)){
				$message = "This post was reserved ".Time::Tag($Post['reserved_at'])." so anyone's free to reserve it now.";
				$checkIfUserCanReserve($message, $data, 'overdue');
				Response::Fail($message, $data);
			}

			User::ReservationLimitExceeded();

			if (!Posts::IsTransferable($Post))
				Response::Fail("This $type was reserved recently, please allow up to 5 days before asking for a transfer");

			$ReserverLink = User::GetProfileLink(User::Get($reserved_by, 'id', 'name'));

			$PreviousAttempts = Posts::GetTransferAttempts($Post, $type, $currentUser['id'], $reserved_by);

			if (!empty($PreviousAttempts[0]) && empty($PreviousAttempts[0]['read_at']))
				Response::Fail("You already expressed your interest in this post to $ReserverLink ".Time::Tag($PreviousAttempts[0]['sent_at']).', please wait for them to respond.');

			$notifSent = Notifications::Send($Post['reserved_by'],'post-passon',array(
				'type' => $type,
				'id' => $Post['id'],
				'user' => $currentUser['id'],
			));

			Response::Success("A notification has been sent to $ReserverLink, please wait for them to react.<br>If they don't visit the site often, it'd be a good idea to send them a note asking him to consider your inquiry.");
		}

		$isUserReserver = $Post['reserved_by'] === $currentUser['id'];
		if (!empty($Post['reserved_by'])){
			switch ($action){
				case 'reserve':
					if ($isUserReserver)
						Response::Fail("You've already reserved this $type", array('li' => Posts::GetLi($Post, true)));
					if (Posts::IsOverdue($Post)){
						$overdue = array(
							'reserved_by' => $Post['reserved_by'],
							'reserved_at' => $Post['reserved_at'],
						);
						$Post['reserved_by'] = null;
						break;
					}
					Response::Fail("This $type has already been reserved by ".User::GetProfileLink(User::Get($Post['reserved_by'])), array('li' => Posts::GetLi($Post, true)));
				break;
				case 'lock':
					if (empty($Post['deviation_id']))
						Response::Fail("Only finished {$type}s can be locked");

					CoreUtils::CheckDeviationInClub($Post['deviation_id']);

					if (!$Database->where('id', $Post['id'])->update("{$type}s", array('lock' => true)))
						Response::DBError();

					$postdata = Posts::Approve($type, $Post['id'], !$isUserReserver ? $Post['reserved_by'] : null);

					$Post['lock'] = true;
					$response = array(
						'message' => "The image appears to be in the group gallery and as such it is now marked as approved.",
						'li' => Posts::GetLi($Post, $type === 'request'),
					);
					if ($isUserReserver)
						$response['message'] .= " Thank you for your contribution!<div class='align-center'><apan class='sideways-smiley-face'>;)</span></div>";
					Response::Done($response);
				break;
				case 'unlock':
					if (Permission::Insufficient('staff'))
						Response::Fail();
					if (empty($Post['lock']))
						Response::Fail("This $type has not been approved yet");

					if (Permission::Insufficient('developer') && CoreUtils::IsDeviationInClub($Post['deviation_id']) === true)
						Response::Fail("<a href='http://fav.me/{$Post['deviation_id']}' target='_blank'>This deviation</a> is part of the group gallery, which prevents the post from being unlocked.");

					$Database->where('id', $Post['id'])->update("{$type}s", array('lock' => false));
					$Post['lock'] = false;

					Response::Done();
				break;
				case 'unreserve':
					if (!$isUserReserver && Permission::Insufficient('staff'))
						Response::Fail();

					if ($type === 'request'){
						$update = array(
							'reserved_by' => null,
							'reserved_at' => null,
							'finished_at' => null,
						);
						break;
					}
					else $_REQUEST['unbind'] = true;
				case 'unfinish':
					if (!$isUserReserver && Permission::Insufficient('staff'))
						Response::Fail();

					if (isset($_REQUEST['unbind'])){
						if ($type === 'reservation'){
							if (!$Database->where('id', $Post['id'])->delete('reservations'))
								Response::DBError();

							Response::Success('Reservation deleted');
						}
						else if ($type === 'request' && Permission::Insufficient('staff') && !$isUserReserver)
							Response::Fail('You cannot remove the reservation from this post');

						$update = array(
							'reserved_by' => null,
							'reserved_at' => null,
						);
					}
					else if ($type === 'reservation' && empty($Post['preview']))
						Response::Fail('This reservation was added directly and cannot be marked unfinished. To remove it, check the unbind from user checkbox.');

					$update['deviation_id'] = null;
					$update['finished_at'] = null;

					if (!$Database->where('id', $Post['id'])->update("{$type}s",$update))
						Response::DBError();

					Response::Done();
				break;
				case 'finish':
					if (!$isUserReserver && Permission::Insufficient('staff'))
						Response::Fail();

					$update = Posts::CheckRequestFinishingImage($Post['reserved_by']);

					$finished_at = Posts::ValidateFinishedAt();
					$update['finished_at'] = isset($finished_at) ? date('c', $finished_at) : date('c');

					if (!$Database->where('id', $Post['id'])->update("{$type}s",$update))
						Response::DBError();

					$postdata = array(
						'type' => $type,
						'id' => $Post['id']
					);
					$message = '';
					if (isset($update['lock'])){
						$message .= "<p>The image appears to be in the group gallery already, so we marked it as approved.</p>";

						Log::Action('post_lock',$postdata);
						if ($Post['reserved_by'] !== $currentUser['id'])
							Notifications::Send($Post['reserved_by'], 'post-approved', $postdata);
					}
					if ($type === 'request'){
						$u = User::Get($Post['requested_by'],'id','name,id');
						if (!empty($u) && $Post['requested_by'] !== $currentUser['id']){
							$notifSent = Notifications::Send($u['id'], 'post-finished', $postdata);
							$message .= "<p><strong>{$u['name']}</strong> ".($notifSent === 0?'has been notified':'will receive a notification shortly').'.'.(is_string($notifSent)?"</p><div class='notice fail'><strong>Error:</strong> $notifSent":'')."</div>";
						}
					}
					if (!empty($message))
						Response::Success($message);
					Response::Done();
				break;
			}
		}
		else if ($action === 'unreserve')
			Response::Done(array('li' => Posts::GetLi($Post, true)));
		else if ($action === 'finish')
			Response::Fail("This $type has not been reserved by anypony yet");

		if (empty($Post['reserved_by']) && $type === 'request' && $action === 'reserve'){
			User::ReservationLimitExceeded();

			$update['reserved_by'] = $currentUser['id'];
			if (Permission::Sufficient('developer')){
				$reserve_as = Posts::ValidatePostAs();
				if (isset($reserve_as)){
					$User = User::Get($reserve_as, 'name');
					if (empty($User))
						Response::Fail('User does not exist');
					if (!Permission::Sufficient('member', $User['role']) && !isset($_POST['screwit']))
						Response::Fail('User does not have permission to reserve posts, continue anyway?', array('retry' => true));

					$update['reserved_by'] = $User['id'];
				}
			}
			$update['reserved_at'] = date('c');
			if (Permission::Sufficient('developer')){
				$reserved_at = Posts::ValidateReservedAt();
				if (isset($reserved_at))
					$update['reserved_at'] = date('c', $reserved_at);
			}
		}

		if (empty($update) || !$Database->where('id', $Post['id'])->update("{$type}s",$update))
			Response::Fail('Nothing has been changed<br>If you tried to do something, then this is actually an error, which you should <a class="send-feedback">tell us</a> about.');

		if (!empty($overdue))
			Log::Action('res_overtake',array_merge(
				array(
					'id' => $Post['id'],
					'type' => $type
				),
				$overdue
			));

		if (!empty($update['reserved_by']))
			Posts::ClearTransferAttempts($Post, $type, 'snatch');
		else if (!empty($Post['reserved_by'])){
			Posts::ClearTransferAttempts($Post, $type, 'free');
		}

		if ($type === 'request'){
			$Post = array_merge($Post, $update);
			$response = array('li' => Posts::GetLi($Post, true));
			if (isset($_POST['FROM_PROFILE']))
				$response['pendingReservations'] = User::GetPendingReservationsHTML($Post['reserved_by'], $isUserReserver);
			Response::Done($response);
		}
		else Response::Done();
	}
	else if ($data === 'mass-approve'){
		if (!Permission::Sufficient('staff'))
			Response::Fail();

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
			Response::Success('There were no posts in need of marking as approved');

		$approved = 0;
		foreach ($Posts as $p){
			if (CoreUtils::IsDeviationInClub($p['deviation_id']) !== true)
				continue;

			Posts::Approve($p['type'], $p['id']);
			$approved++;
		}

		if ($approved === 0)
			Response::Success('There were no posts in need of marking as approved');

		Response::Success('Marked '.CoreUtils::MakePlural('post', $approved, PREPEND_NUMBER).' as approved. To see which ones, check the <a href="/admin/logs/1?type=post_lock&by=you">list of posts you\'ve approved</a>.',array('reload' => true));
	}
	else if ($data === 'add-reservation'){
		if (!Permission::Sufficient('staff'))
			Response::Fail();
		$_POST['allow_overwrite_reserver'] = true;
		$insert = Posts::CheckRequestFinishingImage();
		if (empty($insert['reserved_by']))
			$insert['reserved_by'] = $currentUser['id'];

		$epdata = (new Input('epid','epid',array(
			Input::CUSTOM_ERROR_MESSAGES => array(
				Input::ERROR_MISSING => 'Episode identifier is missing',
				Input::ERROR_INVALID => 'Episode identifier (@value) is invalid',
			)
		)))->out();
		$epdata = Episodes::GetActual($epdata['season'], $epdata['episode']);
		if (empty($epdata))
			Response::Fail('The specified episode does not exist');
		$insert['season'] = $epdata->season;
		$insert['episode'] = $epdata->episode;

		$insert['finished_at'] = date('c');

		$postid = $Database->insert('reservations', $insert, 'id');
		if (!is_int($postid))
			Response::DBError();

		if (!empty($insert['lock']))
			Log::Action('post_lock',array(
				'type' => 'reservation',
				'id' => $postid,
			));

		Response::Success('Reservation added');
	}
	else if (regex_match(new RegExp('^set-(request|reservation)-image/(\d+)$'), $data, $_match)){
		$thing = $_match[1];
		$Post = $Database->where('id', $_match[2])->getOne("{$thing}s");
		if (empty($Post))
			Response::Fail("The specified $thing does not exist");
		if ($Post['lock'])
			Response::Fail('This post is locked, its image cannot be changed.');

		if (Permission::Insufficient('staff'))
			switch ($thing){
				case 'request':
					if ($Post['requested_by'] !== $currentUser['id'] || !empty($Post['reserved_by']))
						Response::Fail();
				break;
				case 'reservation':
					if ($Post['reserved_by'] !== $currentUser['id'])
						Response::Fail();
				break;
			};

		$image_url = (new Input('image_url','string',array(
			Input::CUSTOM_ERROR_MESSAGES => array(
				Input::ERROR_MISSING => 'Image URL is missing',
			)
		)))->out();
		$Image = Posts::CheckImage($image_url, $Post);

		// Check image availability
		if (!@getimagesize($Image->preview)){
			sleep(1);
			if (!@getimagesize($Image->preview))
				Response::Fail("<p class='align-center'>The specified image doesn't seem to exist. Please verify that you can reach the URL below and try again.<br><a href='{$Image->preview}' target='_blank'>{$Image->preview}</a></p>");
		}

		if (!$Database->where('id', $Post['id'])->update("{$thing}s",array(
			'preview' => $Image->preview,
			'fullsize' => $Image->fullsize,
		))) Response::DBError();

		Log::Action('img_update',array(
			'id' => $Post['id'],
			'thing' => $thing,
			'oldpreview' => $Post['preview'],
			'oldfullsize' => $Post['fullsize'],
			'newpreview' => $Image->preview,
			'newfullsize' => $Image->fullsize,
		));

		Response::Done(array('preview' => $Image->preview));
	}
	else if (regex_match(new RegExp('^fix-(request|reservation)-stash/(\d+)$'), $data, $_match)){
		if (!Permission::Sufficient('staff'))
			Response::Fail();

		$thing = $_match[1];
		$Post = $Database->where('id', $_match[2])->getOne("{$thing}s");
		if (empty($Post))
			Response::Fail("The specified $thing does not exist");

		// Link is already full size, we're done
		if (regex_match($FULLSIZE_MATCH_REGEX, $Post['fullsize']))
			Response::Done(array('fullsize' => $Post['fullsize']));

		// Reverse submission lookup
		$StashItem = $Database
			->where('fullsize', $Post['fullsize'])
			->orWhere('preview', $Post['preview'])
			->getOne('deviation_cache','id,fullsize,preview');
		if (empty($StashItem['id']))
			Response::Fail('Stash URL lookup failed');

		try {
			$fullsize = CoreUtils::GetFullsizeURL($StashItem['id'], 'sta.sh');
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
					Response::Fail('The original image has been deleted from Sta.sh',array('rmdirect' => true));
				}
				else throw new Exception("Code $fullsize; Could not find the URL");
			}
		}
		catch (Exception $e){
			Response::Fail('Error while finding URL: '.$e->getMessage());
		}
		// Check image availability
		if (!@getimagesize($fullsize)){
			sleep(1);
			if (!@getimagesize($fullsize))
				Response::Fail("The specified image doesn't seem to exist. Please verify that you can reach the URL below and try again.<br><a href='$fullsize' target='_blank'>$fullsize</a>");
		}

		if (!$Database->where('id', $Post['id'])->update("{$thing}s",array(
			'fullsize' => $fullsize,
		))) Response::DBError();

		Response::Done(array('fullsize' => $fullsize));
	}

	$type = (new Input('what',function($value){
		if (!in_array($value,Posts::$TYPES))
			return Input::ERROR_INVALID;
	},array(
		Input::IS_OPTIONAL => true,
		Input::CUSTOM_ERROR_MESSAGES => array(
			Input::ERROR_INVALID => 'Post type (@value) is invalid',
		)
	)))->out();

	if (!empty($type) && $type === 'reservation'){
		if (Permission::Insufficient('member'))
			Response::Fail();
		User::ReservationLimitExceeded();
	}

	$Image = Posts::CheckImage(Posts::ValidateImageURL());

	if (empty($type))
		Response::Done(array(
			'preview' => $Image->preview,
			'title' => $Image->title,
		));

	$insert = array(
		'preview' => $Image->preview,
		'fullsize' => $Image->fullsize,
	);

	$season = Episodes::ValidateSeason(Episodes::ALLOW_MOVIES);
	$episode = Episodes::ValidateEpisode();
	$epdata = Episodes::GetActual($season, $episode, Episodes::ALLOW_MOVIES);
	if (empty($epdata))
		Response::Fail("The specified episode (S{$season}E$episode) does not exist");
	$insert['season'] = $epdata->season;
	$insert['episode'] = $epdata->episode;

	$ByID = $currentUser['id'];
	if (Permission::Sufficient('developer')){
		$username = Posts::ValidatePostAs();
		if (isset($username)){
			$PostAs = User::Get($username, 'name', 'id,role');

			if (empty($PostAs))
				Response::Fail('The user you wanted to post as does not exist');

			if ($type === 'reservation' && !Permission::Sufficient('member', $PostAs['role']) && !isset($_POST['allow_nonmember']))
				Response::Fail('The user you wanted to post as is not a club member, do you want to post as them anyway?',array('canforce' => true));

			$ByID = $PostAs['id'];
		}
	}

	$insert[$type === 'reservation' ? 'reserved_by' : 'requested_by'] = $ByID;
	Posts::CheckPostDetails($type, $insert);

	$PostID = $Database->insert("{$type}s",$insert,'id');
	if (!$PostID)
		Response::DBError();
	Response::Done(array('id' => $PostID));
