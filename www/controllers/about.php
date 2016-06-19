<?php

	if (POST_REQUEST){
		CSRFProtection::Protect();
		$StatCacheDuration = 5*Time::$IN_SECONDS['hour'];

		$_match = array();
		if (!empty($data) && regex_match(new RegExp('^stats-(posts|approvals)$'),$data,$_match)){
			$stat = $_match[1];
			$CachePath = APPATH."../stats/$stat.json";
			if (file_exists($CachePath) && filemtime($CachePath) > time() - $StatCacheDuration)
				CoreUtils::Respond(array('data' => JSON::Decode(file_get_contents($CachePath))));

			$Data = array('datasets' => array(), 'timestamp' => date('c'));
			$LabelFormat = 'YYYY-MM-DD 00:00:00';

			switch ($stat){
				case 'posts':
					$Labels = $Database->rawQuery(
						"SELECT key FROM
						(
							SELECT posted, to_char(posted,'$LabelFormat') AS key FROM requests
							WHERE posted > NOW() - INTERVAL '20 DAYS'
							UNION ALL
							SELECT posted, to_char(posted,'$LabelFormat') AS key FROM reservations
							WHERE posted > NOW() - INTERVAL '20 DAYS'
						) t
						GROUP BY key
						ORDER BY MIN(t.posted)");

					Statistics::ProcessLabels($Labels, $Data);

					$query =
						"SELECT
							to_char(MIN(posted),'$LabelFormat') AS key,
							COUNT(*)::INT AS cnt
						FROM table_name t
						WHERE posted > NOW() - INTERVAL '20 DAYS'
						GROUP BY to_char(posted,'$LabelFormat')
						ORDER BY MIN(posted)";
					$RequestData = $Database->rawQuery(str_replace('table_name', 'requests', $query));
					if (!empty($RequestData)){
						$Dataset = array('label' => 'Requests', 'clrkey' => 0);
						Statistics::ProcessUsageData($RequestData, $Dataset);
						$Data['datasets'][] = $Dataset;
					}
					$ReservationData = $Database->rawQuery(str_replace('table_name', 'reservations', $query));
					if (!empty($ReservationData)){
						$Dataset = array('label' => 'Reservations', 'clrkey' => 1);
						Statistics::ProcessUsageData($ReservationData, $Dataset);
						$Data['datasets'][] = $Dataset;
					}
				break;
				case 'approvals':
					$Labels = $Database->rawQuery(
						"SELECT to_char(timestamp,'$LabelFormat') AS key
						FROM log
						WHERE timestamp > NOW() - INTERVAL '20 DAYS' AND reftype = 'post_lock'
						GROUP BY key
						ORDER BY MIN(timestamp)");

					Statistics::ProcessLabels($Labels, $Data);

					$Approvals = $Database->rawQuery(
						"SELECT
							to_char(MIN(timestamp),'$LabelFormat') AS key,
							COUNT(*)::INT AS cnt
						FROM log
						WHERE timestamp > NOW() - INTERVAL '20 DAYS' AND reftype = 'post_lock'
						GROUP BY to_char(timestamp,'$LabelFormat')
						ORDER BY MIN(timestamp)"
					);
					if (!empty($Approvals)){
						$Dataset = array('label' => 'Approved posts');
						Statistics::ProcessUsageData($Approvals, $Dataset);
						$Data['datasets'][] = $Dataset;
					}
				break;
			}

			Statistics::PostprocessTimedData($Data);

			CoreUtils::CreateUploadFolder($CachePath);
			file_put_contents($CachePath, JSON::Encode($Data));

			CoreUtils::Respond(array('data' => $Data));
		}

		CoreUtils::NotFound();
	}
	CoreUtils::LoadPage(array(
		'title' => 'About',
		'do-css',
		'js' => array('Chart', $do),
	));
