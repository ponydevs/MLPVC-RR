<?php

use App\CoreUtils;
use App\Episodes;
use App\Pagination;
use App\Permission;

/** @var $do string */

$Pagination = new Pagination('episodes', 10, $Database->where('season != 0')->count('episodes'));

CoreUtils::fixPath("/episodes/{$Pagination->page}");
$heading = "Episodes";
$title = "Page {$Pagination->page} - $heading";
$Episodes = Episodes::get($Pagination->getLimit());

if (isset($_GET['js']))
	$Pagination->respond(Episodes::getTableTbody($Episodes), '#episodes tbody');

$settings = array(
	'title' => $title,
	'do-css',
	'js' => array('paginate',$do),
);
if (Permission::sufficient('staff'))
	$settings['js'] = array_merge(
		$settings['js'],
		array('moment-timezone',"$do-manage")
	);
CoreUtils::loadPage($settings);
