<?php

namespace App\Controllers;

use App\CoreUtils;
use App\ShowHelper;
use App\Pagination;
use App\Permission;
use App\Models\Show;
use App\Regexes;

class ShowController extends Controller {
	public function index(){
		$base_path = '/show';
		$episodes_pagination = new Pagination($base_path, 8, Show::count(['conditions' => 'season is not null']), 'ep');
		$movies_pagination = new Pagination($base_path, 8, Show::count(['conditions' => 'season is null']), 'movie');

		\App\DB::$instance->orderBy('no', 'DESC');
		$episodes = ShowHelper::get($episodes_pagination->getLimit(), null, false, true);
		\App\DB::$instance->orderBy('no', 'DESC');
		$movies = ShowHelper::get($movies_pagination->getLimit(), 'season is null', true);

		$path = $episodes_pagination->toURI();
		$path->append_query_raw($movies_pagination->getPageQueryString());
		CoreUtils::fixPath($path);
		$heading = 'Episodes & Movies';

		$settings = [
			'heading' => $heading,
			'title' => $heading,
			'css' => [true],
			'js' => ['paginate', true],
			'import' => [
				'episodes_pagination' => $episodes_pagination,
				'movies_pagination' => $movies_pagination,
				'episodes' => $episodes,
				'movies' => $movies,
			],
		];
		if (Permission::sufficient('staff')){
			$settings['js'] = array_merge(
				$settings['js'],
				['moment-timezone', 'pages/show/index-manage']
			);
			$settings['import']['export'] = [
				'EP_TITLE_REGEX' => Regexes::$ep_title,
				'SHOW_TYPES' => array_keys(ShowHelper::VALID_TYPES),
			];
		}
		CoreUtils::loadPage(__METHOD__, $settings);
	}
}
