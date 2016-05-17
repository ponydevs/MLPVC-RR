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

				if (Permission::Sufficient('developer') && isset($Post['reserved_at']))
					$response['reserved_at'] = date('c', strtotime($Post['reserved_at']));
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
		CoreUtils::Respond($update);
	}
	else if (regex_match(new RegExp('^set-(request|reservation)-image/(\d+)$'), $data, $_match)){

		$thing = $_match[1];
		$Post = $Database->where('id', $_match[2])->getOne("{$thing}s");
		if (empty($Post))
			CoreUtils::Respond("The specified $thing does not exist");
		if ($Post['lock'])
			CoreUtils::Respond('This post is locked, its image cannot be changed.');

		if (!Permission::Sufficient('staff') || $thing !== 'request' || !($Post['requested_by'] === $currentUser['id'] && empty($Post['reserved_by'])))
			CoreUtils::Respond();

		$Image = Posts::CheckImage($Post);

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

	$image_check = empty($_POST['what']);
	if (!$image_check){
		if (!in_array($_POST['what'],Posts::$TYPES))
			CoreUtils::Respond('Invalid post type');

		$type = $_POST['what'];
		if ($type === 'reservation'){
			if (!Permission::Sufficient('member'))
				CoreUtils::Respond();
			User::ReservationLimitCheck();
		}
	}

	if (!empty($_POST['image_url'])){
		$Image = Posts::CheckImage();

		if ($image_check)
			CoreUtils::Respond(array(
				'preview' => $Image->preview,
				'title' => $Image->title,
			));
	}
	else if ($image_check)
		CoreUtils::Respond("Please provide an image URL!");

	$insert = array(
		'preview' => $Image->preview,
		'fullsize' => $Image->fullsize,
	);

	if (($_POST['season'] != '0' && empty($_POST['season'])) || empty($_POST['episode']))
		CoreUtils::Respond('Missing episode identifiers');
	$epdata = Episode::GetActual((int)$_POST['season'], (int)$_POST['episode'], ALLOW_SEASON_ZERO);
	if (empty($epdata))
		CoreUtils::Respond('This episode does not exist');
	$insert['season'] = $epdata['season'];
	$insert['episode'] = $epdata['episode'];

	$ByID = $currentUser['id'];
	if (Permission::Sufficient('developer') && !empty($_POST['post_as'])){
		$username = CoreUtils::Trim($_POST['post_as']);
		$PostAs = User::Get($username, 'name', '');

		if (empty($PostAs))
			CoreUtils::Respond('The user you wanted to post as does not exist');

		if ($type === 'reservation' && !Permission::Sufficient('member', $PostAs['role']) && !isset($_POST['allow_nonmember']))
			CoreUtils::Respond('The user you wanted to post as is not a club member, so you want to post as them anyway?',0,array('canforce' => true));

		$ByID = $PostAs['id'];
	}

	$insert[$type === 'reservation' ? 'reserved_by' : 'requested_by'] = $ByID;
	Posts::CheckPostDetails($type, $insert);

	$PostID = $Database->insert("{$type}s",$insert,'id');
	if (!$PostID)
		CoreUtils::Respond(ERR_DB_FAIL);
	CoreUtils::Respond(array('id' => $PostID));
