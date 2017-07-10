<?php

namespace App\Controllers;
use App\CoreUtils;
use App\Episodes;
use App\Pagination;
use App\Permission;

class EpisodesController extends Controller {
	public $do = 'episodes';

	public function index(){
		global $Database;

		$Pagination = new Pagination('episodes', 8, $Database->where('season != 0')->count('episodes'));

		CoreUtils::fixPath("/episodes/{$Pagination->page}");
		$heading = 'Episodes';
		$title = "Page {$Pagination->page} - $heading";
		$Episodes = Episodes::get($Pagination->getLimit());

		if (isset($_GET['js']))
			$Pagination->respond(Episodes::getTableTbody($Episodes), '#episodes tbody');

		$settings = [
			'heading' => $heading,
			'title' => $title,
			'do-css',
			'js' => ['paginate', $this->do],
			'import' => [
				'Pagination' => $Pagination,
				'Episodes' => $Episodes,
			],
		];
		if (Permission::sufficient('staff'))
			$settings['js'] = array_merge(
				$settings['js'],
				['moment-timezone', "{$this->do}-manage"]
			);
		CoreUtils::loadPage($settings, $this);
	}
}
