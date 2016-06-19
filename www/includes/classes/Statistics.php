<?php

	class Statistics {
		/**
		 * Process label data for stats
		 *
		 * @param array $Labels Labels to append to data
		 * @param array $Data   Data array reference
		 */
		static function ProcessLabels(&$Labels, &$Data){
			if (empty($Labels))
				$Labels = array();
			else {
				foreach ($Labels as $k => $v)
					$Labels[$k] = $v['key'];
			}

			$Data['labels'] = $Labels;
		}

		/**
		 * Process data for usage stats
		 *
		 * @param array $Rows    Database rows obtained with rawQuery
		 * @param array $Dataset Array to process data into
		 */
		static function ProcessUsageData($Rows, &$Dataset){
			$Dataset['labels'] =
			$Dataset['data'] = array();

			foreach ($Rows as $row){
				$Dataset['labels'][] = $row['key'];
				$Dataset['data'][] = $row['cnt'];
			}

			global $Labels;
			foreach ($Labels as $ix => $label){
				if (empty($Dataset['labels'][$ix]) || $Dataset['labels'][$ix] !== $label){
					array_splice($Dataset['labels'], $ix, 0, array($label));
					array_splice($Dataset['data'], $ix, 0, array(0));
				}
			}

			unset($Dataset['labels']);
		}

		/**
		 * Post-process time-based statistics data
		 *
		 * @param array $Data
		 */
		static function PostprocessTimedData(&$Data){
			foreach ($Data['labels'] as $k => $l)
				$Data['labels'][$k] = strtotime($l);

			$safety = 0;
			while (true){
				if ($safety++ > 20)
					throw new Exception('Too many loops');

				$continue = false;
				$labelCount = count($Data['labels']);
				for ($lix = 1; $lix < $labelCount-1; $lix++){
					$diff = $Data['labels'][$lix] - $Data['labels'][$lix-1];
					//var_dump(array(date('Y-m-d', $Data['labels'][$lix-1]),date('Y-m-d', $Data['labels'][$lix]),$diff));
					if ($diff > Time::$IN_SECONDS['day']){
						$continue = true;
						//var_dump('breaks');
						break;
					}
				}
				if (!$continue)
					break;

				array_splice($Data['labels'], $lix, 0, array(strtotime('+1 day', $Data['labels'][$lix-1])));
				foreach ($Data['datasets'] as $k => $_){
					array_splice($Data['datasets'][$k]['data'], $lix, 0, array(0));
					//var_dump(array($lix,$Data['datasets'][$k]['data'],$Data['datasets'][$k]['data'][$lix], $Data['datasets'][$k]['data'][$lix - 1],$Data['datasets'][$k]['data'][$lix + 1]));
					//die();
				}
			}

			foreach ($Data['labels'] as $k => $ts)
				$Data['labels'][$k] = date('jS M',$ts);
		}
	}
