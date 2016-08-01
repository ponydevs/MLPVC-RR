<?php

	if (!POST_REQUEST) CoreUtils::NotFound();
	if (!$signedIn) Response::Fail();
	CSRFProtection::Protect();

	list($action, $data) = explode('/',$data.'/');

	switch($action){
		case 'get':
			Response::Done(array('list' => Notifications::GetHTML(Notifications::Get(null,Notifications::UNREAD_ONLY),NOWRAP)));
		break;
		case 'mark-read':
			$nid = intval($data, 10);
			$Notif = $Database->where('id', $nid)->where('user', $currentUser['id'])->getOne('notifications');
			if (empty($Notif))
				Response::Fail("The notification (#$nid) does not exist");

			$read_action = (new Input('read_action','string',array(
				Input::IS_OPTIONAL => true,
				Input::IN_RANGE => [null, 10],
				Input::CUSTOM_ERROR_MESSAGES => array(
					Input::ERROR_INVALID => 'Action (@value) is invalid',
					Input::ERROR_RANGE => 'Action must be between @min and @max characters',
				)
			)))->out();
			if (!empty($read_action)){
				if (empty(Notifications::$ACTIONABLE_NOTIF_OPTIONS[$Notif['type']][$read_action]))
					Response::Fail("Invalid read action ($action) specified for notification type {$Notif['type']}");
				$data = !empty($Notif['data']) ? JSON::Decode($Notif['data']) : null;
				switch ($Notif['type']){
					case "post-passon":
						$Post = $Database->where('id', $data['id'])->getOne("{$data['type']}s");
						if (empty($Post)){
							Posts::ClearTransferAttempts($data, $data['type'], 'del');
							Response::Fail("The {$data['type']} doesn't exist or has been deleted");
						}
						if ($read_action === 'true'){
							if ($Post['reserved_by'] !== $currentUser['id']){
								Posts::ClearTransferAttempts($Post, $data['type'], 'perm', null, $currentUser['id']);
								Response::Fail('You are not allowed to transfer this reservation');
							}

							Notifications::SafeMarkRead($Notif['id'], $read_action);
							Notifications::Send($data['user'], "post-passallow", array(
								'id' => $data['id'],
								'type' => $data['type'],
								'by' => $currentUser['id'],
							));
							$Database->where('id', $data['id'])->update("{$data['type']}s",array(
								'reserved_by' => $data['user'],
								'reserved_at' => date('c'),
							));

							Posts::ClearTransferAttempts($Post, $data['type'], 'deny');

							Log::Action('res_transfer',array(
								'id' => $data['id'],
								'type' => $data['type'],
								'to' => $data['user'],
							));
						}
						else {
							Notifications::SafeMarkRead($Notif['id'], $read_action);
							Notifications::Send($data['user'], "post-passdeny", array(
								'id' => $data['id'],
								'type' => $data['type'],
								'by' => $currentUser['id'],
							));
						}

						Response::Done();
					break;
					default:
						Notifications::SafeMarkRead($Notif['id'], $read_action);
				}
			}
			else Notifications::SafeMarkRead($Notif['id']);

			Response::Done();
	}
