<?php

use App\CoreUtils;
use App\Episodes;
use App\Pagination;
use App\Permission;

$Pagination = new Pagination('episodes', 10, $Database->where('season != 0')->count('episodes'));

CoreUtils::FixPath("/episodes/{$Pagination->page}");
$heading = "Episodes";
$title = "Page {$Pagination->page} - $heading";
$Episodes = Episodes::Get($Pagination->GetLimit());

if (isset($_GET['js']))
	$Pagination->Respond(Episodes::GetTableTbody($Episodes), '#episodes tbody');

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
