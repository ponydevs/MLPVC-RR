<?php

	$Pagination = new Pagination('episodes', 10, $Database->where('season != 0')->count('episodes'));

	CoreUtils::FixPath("/episodes/{$Pagination->page}");
	$heading = "Episodes";
	$title = "Page {$Pagination->page} - $heading";
	$Episodes = Episode::Get($Pagination->GetLimit());

	if (isset($_GET['js']))
		$Pagination->Respond(Episode::GetTableTbody($Episodes), '#episodes tbody');

	$settings = array(
		'title' => $title,
		'do-css',
		'js' => array('paginate',$do),
	);
	if (Permission::Sufficient('staff'))
		$settings['js'] = array_merge(
			$settings['js'],
			array('moment-timezone',"$do-manage")
		);
	CoreUtils::LoadPage($settings);
