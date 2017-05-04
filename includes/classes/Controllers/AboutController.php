<?php

namespace App\Controllers;
use App\CachedFile;
use App\CoreUtils;
use App\CSRFProtection;
use App\HTTP;
use App\JSON;
use App\Response;
use App\Statistics;
use App\Time;

class AboutController extends Controller {
	public $do = 'about';

	function index(){
		CoreUtils::loadPage(array(
			'title' => 'About',
			'do-css',
			'js' => array('Chart', $this->do),
		), $this);
	}

	const
		STAT_TYPES = ['posts','approvals'],
		STAT_CHACHE_DURATION = 5*Time::IN_SECONDS['hour'];

	function stats(){
		global $Database;

		CSRFProtection::protect();

		$stat = strtolower(CoreUtils::trim($_GET['stat']));
		if (!in_array($stat, self::STAT_TYPES))
			HTTP::statusCode(404, AND_DIE);

		$cache = CachedFile::init(FSPATH."stats/$stat.json", self::STAT_CHACHE_DURATION);
		if (!$cache->expired())
			Response::done([ 'data' => $cache->read() ]);

		$Data = array('datasets' => array(), 'timestamp' => date('c'));
		$LabelFormat = 'YYYY-MM-DD';

		switch ($stat){
			case 'posts':
				$Labels = $Database->rawQuery(
					"SELECT key FROM
					(
						SELECT posted, to_char(posted,'$LabelFormat') AS key FROM requests
						WHERE posted > NOW() - INTERVAL '2 MONTHS'
						UNION ALL
						SELECT posted, to_char(posted,'$LabelFormat') AS key FROM reservations
						WHERE posted > NOW() - INTERVAL '2 MONTHS'
					) t
					GROUP BY key
					ORDER BY MIN(t.posted)");

				Statistics::processLabels($Labels, $Data);

				$query =
					"SELECT
						to_char(MIN(posted),'$LabelFormat') AS key,
						COUNT(*)::INT AS cnt
					FROM table_name t
					WHERE posted > NOW() - INTERVAL '2 MONTHS'
					GROUP BY to_char(posted,'$LabelFormat')
					ORDER BY MIN(posted)";
				$RequestData = $Database->rawQuery(str_replace('table_name', 'requests', $query));
				if (!empty($RequestData)){
					$Dataset = array('label' => 'Requests', 'clrkey' => 0);
					Statistics::processUsageData($RequestData, $Dataset, $Labels);
					$Data['datasets'][] = $Dataset;
				}
				$ReservationData = $Database->rawQuery(str_replace('table_name', 'reservations', $query));
				if (!empty($ReservationData)){
					$Dataset = array('label' => 'Reservations', 'clrkey' => 1);
					Statistics::processUsageData($ReservationData, $Dataset, $Labels);
					$Data['datasets'][] = $Dataset;
				}
			break;
			case 'approvals':
				$Labels = $Database->rawQuery(
					"SELECT to_char(timestamp,'$LabelFormat') AS key
					FROM log
					WHERE timestamp > NOW() - INTERVAL '2 MONTHS' AND reftype = 'post_lock'
					GROUP BY key
					ORDER BY MIN(timestamp)");

				Statistics::processLabels($Labels, $Data);

				$Approvals = $Database->rawQuery(
					"SELECT
						to_char(MIN(timestamp),'$LabelFormat') AS key,
						COUNT(*)::INT AS cnt
					FROM log
					WHERE timestamp > NOW() - INTERVAL '2 MONTHS' AND reftype = 'post_lock'
					GROUP BY to_char(timestamp,'$LabelFormat')
					ORDER BY MIN(timestamp)"
				);
				if (!empty($Approvals)){
					$Dataset = array('label' => 'Approved posts');
					Statistics::processUsageData($Approvals, $Dataset, $Labels);
					$Data['datasets'][] = $Dataset;
				}
			break;
		}

		Statistics::postprocessTimedData($Data);

		$cache->update($Data);

		Response::done(array('data' => $Data));
	}
}
