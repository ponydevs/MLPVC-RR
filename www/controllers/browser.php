<?php

	$AgentString = null;
	if (is_numeric($data) && Permission::Sufficient('developer')){
		$SessionID = intval($data, 10);
		$Session = $Database->where('id', $SessionID)->getOne('sessions');
		if (!empty($Session))
			$AgentString = $Session['user_agent'];
	}
	$browser = CoreUtils::DetectBrowser($AgentString);

	CoreUtils::FixPath('/browser'.(!empty($Session)?"/{$Session['id']}":''));

	CoreUtils::LoadPage(array(
		'title' => 'Browser recognition test page',
		'do-css',
		'no-robots',
	));
