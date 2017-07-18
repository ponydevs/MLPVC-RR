<?php

namespace App\Controllers;
use App\CoreUtils;
use App\Episodes;
use App\Models\Episode;
use App\Pagination;
use App\Permission;

class EpisodesController extends Controller {
	public $do = 'episodes';

	public function index(){
		$areMovies = $this->do !== 'episodes';

		$EpisodesPagination = new Pagination('episodes', 8, Episode::count(['conditions' => 'season != 0']));
		$MoviesPagination = new Pagination('movies', 8, Episode::count(['conditions' => 'season = 0']));

		$Pagination = $areMovies ? $MoviesPagination : $EpisodesPagination;
		($areMovies ? $EpisodesPagination : $MoviesPagination)->forcePage(1);

		$Episodes = Episodes::get($EpisodesPagination->getLimit());
		$Movies = Episodes::get($MoviesPagination->getLimit(), 'season = 0', true);

		CoreUtils::fixPath("/{$this->do}/{$Pagination->page}");
		$heading = CoreUtils::capitalize($this->do);
		$title = "Page {$Pagination->page} - $heading";

		$Pagination->respondIfShould(Episodes::getTableTbody($areMovies ? $Movies : $Episodes, $areMovies), "#{$this->do} tbody");

		$settings = [
			'heading' => $heading,
			'title' => $title,
			'view' => 'episodes',
			'css' => 'episodes',
			'js' => ['paginate', 'episodes'],
			'import' => [
				'Pagination' => $Pagination,
				'EpisodesPagination' => $EpisodesPagination,
				'MoviesPagination' => $MoviesPagination,
				'Movies' => $Movies,
				'Episodes' => $Episodes,
			],
		];
		if (Permission::sufficient('staff'))
			$settings['js'] = array_merge(
				$settings['js'],
				['moment-timezone', 'episodes-manage']
			);
		CoreUtils::loadPage($settings);
	}
}
