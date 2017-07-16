<?php

namespace App\Controllers;

use App\DeviantArt;
use App\HTTP;
use App\Response;

class DeviationController extends Controller {
	public function lazyloadContrib($params){
		$CachedDeviation = DeviantArt::getCachedDeviation($params['favme']);
		if (empty($CachedDeviation))
			HTTP::statusCode(404, AND_DIE);

		if (empty($_GET['format']))
			Response::done(['html' => $CachedDeviation->toLinkWithPreview()]);
		else switch ($_GET['format']){
			case 'raw':
				Response::done($CachedDeviation->to_array());
			break;
		}
	}
}
