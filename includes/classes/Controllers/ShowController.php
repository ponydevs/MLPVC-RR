<?php

namespace App\Controllers;

use App\CoreUtils;
use App\Episodes;
use App\Pagination;
use App\Permission;
use App\Models\Episode;
use Peertopark\UriBuilder;

class ShowController extends Controller {
	public function index(){
		$basePath = '/show';
		$EpisodesPagination = new Pagination($basePath, 8, Episode::count(['conditions' => 'season != 0']), 'ep');
		$MoviesPagination = new Pagination($basePath, 8, Episode::count(['conditions' => 'season = 0']), 'movie');

		$Episodes = Episodes::get($EpisodesPagination->getLimit());
		$Movies = Episodes::get($MoviesPagination->getLimit(), 'season = 0', true);

		$path = $EpisodesPagination->toURI();
		$path->append_query_raw($MoviesPagination->getPageQueryString());
		CoreUtils::fixPath($path);
		$heading = 'Episodes & Movies';

		$settings = [
			'heading' => $heading,
			'title' => $heading,
			'css' => [true],
			'js' => ['paginate', true],
			'import' => [
				'EpisodesPagination' => $EpisodesPagination,
				'MoviesPagination' => $MoviesPagination,
				'Episodes' => $Episodes,
				'Movies' => $Movies,
			],
		];
		if (Permission::sufficient('staff'))
			$settings['js'] = array_merge(
				$settings['js'],
				['moment-timezone', 'pages/show/index-manage']
			);
		CoreUtils::loadPage(__METHOD__, $settings);
	}
}
