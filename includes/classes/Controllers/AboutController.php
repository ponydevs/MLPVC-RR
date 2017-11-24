<?php

namespace App\Controllers;
use App\CachedFile;
use App\CoreUtils;
use App\CSRFProtection;
use App\DB;
use App\HTTP;
use App\Models\Session;
use App\Permission;
use App\RegExp;
use App\Response;
use App\Statistics;
use App\Time;

class AboutController extends Controller {
	public function index(){
		CoreUtils::loadPage(__METHOD__, [
			'title' => 'About',
			'css' => [true],
			'js' => ['Chart',true],
		]);
	}

	public function browser($params){
		$AgentString = null;
		if (isset($params['session'])){
			if (Permission::insufficient('developer'))
				CoreUtils::noPerm();
			$SessionID = intval($params['session'], 10);
			/** @var $Session \App\Models\Session */
			$Session = Session::find($SessionID);
			if (!empty($Session))
				$AgentString = $Session->user_agent;
		}
		else $Session = null;
		$browser = CoreUtils::detectBrowser($AgentString);
		if (empty($browser['platform']))
			CoreUtils::error_log('Could not find platform based on the following UA string: '.preg_replace(new RegExp(INVERSE_PRINTABLE_ASCII_PATTERN), '', $AgentString));

		if ($Session !== null){
			$Session->platform = $browser['platform'];
			$Session->browser_name = $browser['browser_name'];
			$Session->browser_ver = $browser['browser_ver'];
			$Session->save();
		}

		CoreUtils::fixPath('/about/browser'.(!empty($Session)?"/{$Session->id}":''));

		CoreUtils::loadPage(__METHOD__, [
			'title' => 'Browser recognition test page',
			'css' => [true],
			'no-robots' => true,
			'import' => [
				'AgentString' => $AgentString,
				'Session' => $Session ?? null,
				'browser' => $browser,
			],
		]);
	}

	const
		STAT_TYPES = ['posts','approvals','alltimeposts'],
		STAT_CHACHE_DURATION = 5*Time::IN_SECONDS['hour'];

	public function stats(){
		CSRFProtection::protect();

		$stat = strtolower(CoreUtils::trim($_GET['stat']));
		if (!in_array($stat, self::STAT_TYPES, true))
			HTTP::statusCode(404, AND_DIE);

		$cache = CachedFile::init(FSPATH."stats/$stat.json.gz", self::STAT_CHACHE_DURATION);
		if (!$cache->expired())
			Response::done([ 'data' => $cache->read() ]);

		$Data = ['datasets' => [], 'timestamp' => date('c')];
		$LabelFormat = 'YYYY-MM-DD';

		switch ($stat){
			case 'posts':
				$Labels = DB::$instance->query(
					"SELECT key FROM
					(
						SELECT requested_at as posted, to_char(requested_at,'$LabelFormat') AS key FROM requests
						WHERE requested_at > NOW() - INTERVAL '2 MONTHS'
						UNION ALL
						SELECT reserved_at as posted, to_char(reserved_at,'$LabelFormat') AS key FROM reservations
						WHERE reserved_at > NOW() - INTERVAL '2 MONTHS'
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
				$RequestData = DB::$instance->query(str_replace(['table_name','posted'], ['requests','requested_at'], $query));
				if (!empty($RequestData)){
					$Dataset = ['label' => 'Requests', 'clrkey' => 0];
					Statistics::processUsageData($RequestData, $Dataset, $Labels);
					$Data['datasets'][] = $Dataset;
				}
				$ReservationData = DB::$instance->query(str_replace(['table_name','posted'], ['reservations','reserved_at'], $query));
				if (!empty($ReservationData)){
					$Dataset = ['label' => 'Reservations', 'clrkey' => 1];
					Statistics::processUsageData($ReservationData, $Dataset, $Labels);
					$Data['datasets'][] = $Dataset;
				}
			break;
			case 'approvals':
				$Labels = DB::$instance->query(
					"SELECT to_char(timestamp,'$LabelFormat') AS key
					FROM log
					WHERE timestamp > NOW() - INTERVAL '2 MONTHS' AND reftype = 'post_lock'
					GROUP BY key
					ORDER BY MIN(timestamp)");

				Statistics::processLabels($Labels, $Data);

				$Approvals = DB::$instance->query(
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

				$Approvals = DB::$instance->query(
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
					$AssocApprovals = [];
					foreach ($Approvals as $a)
						$AssocApprovals[$a['key']] = $a;
					$i = 0;
					$FinalApprovals = [];
					foreach ($Labels as $key){
						$FinalApprovals[$i] = $AssocApprovals[$key] ?? [ 'key' => $key, 'cnt' => 0];
						if ($i > 0)
							$FinalApprovals[$i]['cnt'] += $FinalApprovals[$i-1]['cnt'];
						$i++;
					}
					Statistics::processUsageData($FinalApprovals, $Dataset, $Labels);
					$Data['datasets'][] = $Dataset;
				}
				$Requests = DB::$instance->query(
					"SELECT
						to_char(MIN(requested_at),'$BroadLabelFormat') AS key,
						COUNT(*)::INT AS cnt
					FROM requests
					WHERE requested_at >= '$site_launch'
					GROUP BY to_char(requested_at,'$BroadLabelFormat')
					ORDER BY MIN(requested_at)"
				);
				if (!empty($Requests)){
					$Dataset = ['label' => 'Requests', 'clrkey' => 1];
					$AssocRequests = [];
					foreach ($Requests as $a)
						$AssocRequests[$a['key']] = $a;
					$i = 0;
					$FinalRequests= [];
					foreach ($Labels as $key){
						$FinalRequests[$i] = $AssocRequests[$key] ?? [ 'key' => $key, 'cnt' => 0];
						if ($i > 0)
							$FinalRequests[$i]['cnt'] += $FinalRequests[$i-1]['cnt'];
						$i++;
					}
					Statistics::processUsageData($FinalRequests, $Dataset, $Labels);
					$dsl = count($Dataset['data']);
					for ($i=1; $i<$dsl; $i++){
						if ($Dataset['data'][$i] === 0 && $Dataset['data'][$i-1] > 0)
							$Dataset['data'][$i] = $Dataset['data'][$i-1];
					}
					$Data['datasets'][] = $Dataset;
				}
				$Reservations = DB::$instance->query(
					"SELECT
						to_char(MIN(reserved_at),'$BroadLabelFormat') AS key,
						COUNT(*)::INT AS cnt
					FROM reservations
					WHERE reserved_at >= '$site_launch'
					GROUP BY to_char(reserved_at,'$BroadLabelFormat')
					ORDER BY MIN(reserved_at)"
				);
				if (!empty($Reservations)){
					$Dataset = ['label' => 'Reservations', 'clrkey' => 2];
					$AssocReservations = [];
					foreach ($Reservations as $a)
						$AssocReservations[$a['key']] = $a;
					$i = 0;
					$FinalReservations= [];
					foreach ($Labels as $key){
						$FinalReservations[$i] = $AssocReservations[$key] ?? [ 'key' => $key, 'cnt' => 0];
						if ($i > 0)
							$FinalReservations[$i]['cnt'] += $FinalReservations[$i-1]['cnt'];
						$i++;
					}
					Statistics::processUsageData($FinalReservations, $Dataset, $Labels);
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
