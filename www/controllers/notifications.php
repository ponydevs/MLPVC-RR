<?php

	if (!POST_REQUEST) CoreUtils::NotFound();
	if (!$signedIn) CoreUtils::Respond();
	CSRFProtection::Protect();

	list($action, $data) = explode('/',$data.'/');

	switch($action){
		case 'get':
			CoreUtils::Respond(array('list' => Notifications::GetHTML(Notifications::Get(null,UNREAD_ONLY),NOWRAP)));
		break;
		case 'mark-read':
			$nid = intval($data, 10);
			$Notif = $Database->where('id', $nid)->getOne('notifications','id');
			if (empty($Notif))
				CoreUtils::Respond("The notification (#$nid) does not exist");

			Notifications::MarkRead($Notif['id']);
			CoreUtils::Respond(true);
	}
