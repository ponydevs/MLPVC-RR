<?php

namespace App\Controllers;
use App\CoreUtils;
use App\Episodes;
use App\Pagination;
use App\Permission;

class EpisodesController extends Controller {
	public $do = 'episodes';

	function index(){
		global $Database;

		$Pagination = new Pagination('episodes', 10, $Database->where('season != 0')->count('episodes'));

		CoreUtils::fixPath("/episodes/{$Pagination->page}");
		$heading = "Episodes";
		$title = "Page {$Pagination->page} - $heading";
		$Episodes = Episodes::get($Pagination->getLimit());

		if (isset($_GET['js']))
			$Pagination->respond(Episodes::getTableTbody($Episodes), '#episodes tbody');

		$settings = array(
			'heading' => $heading,
			'title' => $title,
			'do-css',
			'js' => array('paginate',$this->do),
			'import' => [
				'Pagination' => $Pagination,
				'Episodes' => $Episodes,
			],
		);
		if (Permission::sufficient('staff'))
			$settings['js'] = array_merge(
				$settings['js'],
				array('moment-timezone',"{$this->do}-manage")
			);
		CoreUtils::loadPage($settings, $this);
	}
}
