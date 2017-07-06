<?php

namespace App\Controllers;
use App\CachedFile;
use App\CoreUtils;
use App\CSRFProtection;
use App\HTTP;

use App\Response;
use App\Statistics;
use App\Time;

class AboutController extends Controller {
	public $do = 'about';

	function index(){
		CoreUtils::loadPage([
			'title' => 'About',
			'do-css',
			'js' => ['Chart', $this->do],
		], $this);
	}

	const
		STAT_TYPES = ['posts','approvals','alltimeposts'],
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

		$Data = ['datasets' => [], 'timestamp' => date('c')];
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
					$Dataset = ['label' => 'Requests', 'clrkey' => 0];
					Statistics::processUsageData($RequestData, $Dataset, $Labels);
					$Data['datasets'][] = $Dataset;
				}
				$ReservationData = $Database->rawQuery(str_replace('table_name', 'reservations', $query));
				if (!empty($ReservationData)){
					$Dataset = ['label' => 'Reservations', 'clrkey' => 1];
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
					$Dataset = ['label' => 'Approved posts'];
					Statistics::processUsageData($Approvals, $Dataset, $Labels);
					$Data['datasets'][] = $Dataset;
				}
			break;
			case 'alltimeposts':
				$site_launch = '2015-06-27T17:24:00Z';
				$ts = strtotime($site_launch);
				$Labels = [];
				do {
					$Labels[] = [ 'key' => date('Y-m', $ts) ];
					$ts = strtotime('+1 month', $ts);
				}
				while ($ts < strtotime('next month'));

				Statistics::processLabels($Labels, $Data);

				$BroadLabelFormat = 'YYYY-MM';

				$Approvals = $Database->rawQuery(
					"SELECT
						to_char(MIN(timestamp),'$BroadLabelFormat') AS key,
						COUNT(*)::INT AS cnt
					FROM log
					WHERE reftype = 'post_lock' AND timestamp >= '$site_launch'
					GROUP BY to_char(timestamp,'$BroadLabelFormat')
					ORDER BY MIN(timestamp)"
				);
				if (!empty($Approvals)){
					$Dataset = ['label' => 'Approved posts', 'clrkey' => 0];
					foreach ($Approvals as $i => $_){
						if ($i < 1)
							continue;

						$Approvals[$i]['cnt'] += $Approvals[$i-1]['cnt'];
					}
					Statistics::processUsageData($Approvals, $Dataset, $Labels);
					$Data['datasets'][] = $Dataset;
				}
				$Requests = $Database->rawQuery(
					"SELECT
						to_char(MIN(posted),'$BroadLabelFormat') AS key,
						COUNT(*)::INT AS cnt
					FROM requests
					WHERE posted >= '$site_launch'
					GROUP BY to_char(posted,'$BroadLabelFormat')
					ORDER BY MIN(posted)"
				);
				if (!empty($Requests)){
					$Dataset = ['label' => 'Requests', 'clrkey' => 1];
					foreach ($Requests as $i => $_){
						if ($i < 1)
							continue;

						$Requests[$i]['cnt'] += $Requests[$i-1]['cnt'];
					}
					Statistics::processUsageData($Requests, $Dataset, $Labels);
					$dsl = count($Dataset['data']);
					for ($i=1; $i<$dsl; $i++){
						if ($Dataset['data'][$i] === 0 && $Dataset['data'][$i-1] > 0)
							$Dataset['data'][$i] = $Dataset['data'][$i-1];
					}
					$Data['datasets'][] = $Dataset;
				}
				$Reservations = $Database->rawQuery(
					"SELECT
						to_char(MIN(posted),'$BroadLabelFormat') AS key,
						COUNT(*)::INT AS cnt
					FROM reservations
					WHERE posted >= '$site_launch'
					GROUP BY to_char(posted,'$BroadLabelFormat')
					ORDER BY MIN(posted)"
				);
				if (!empty($Reservations)){
					$Dataset = ['label' => 'Reservations', 'clrkey' => 2];
					foreach ($Reservations as $i => $_){
						if ($i < 1)
							continue;

						$Reservations[$i]['cnt'] += $Reservations[$i-1]['cnt'];
					}
					Statistics::processUsageData($Reservations, $Dataset, $Labels);
					$dsl = count($Dataset['data']);
					for ($i=1; $i<$dsl; $i++){
						if ($Dataset['data'][$i] === 0 && $Dataset['data'][$i-1] > 0)
							$Dataset['data'][$i] = $Dataset['data'][$i-1];
					}
					$Data['datasets'][] = $Dataset;
				}
			break;
		}

		if ($stat !== 'alltimeposts')
			Statistics::postprocessTimedData($Data);

		$cache->update($Data);

		Response::done(['data' => $Data]);
	}
}
