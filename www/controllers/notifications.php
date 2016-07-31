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
			$Notif = $Database->where('id', $nid)->getOne('notifications','id');
			if (empty($Notif))
				Response::Fail("The notification (#$nid) does not exist");

			try {
				Notifications::MarkRead($Notif['id']);
			}
			catch (ElephantIO\Exception\ServerConnectionFailureException $e){
				error_log("Notification server down!\n".$e->getMessage());
				Response::Fail('Notification server is down! Please <a class="send-feedback">let us know</a>.');
			}
			catch (Exception $e){
				Response::Fail('SocketEvent Error: '.$e->getMessage());
			}
			Response::Done();
	}
