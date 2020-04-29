<?php

namespace App;

use function count;

class Statistics {
  /**
   * Process label data for stats
   *
   * @param array $Labels Labels to append to data
   * @param array $Data   Data array reference
   */
  public static function processLabels(&$Labels, &$Data) {
    if (empty($Labels))
      $Labels = [];
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
   * @param array $Labels  Array of labels
   */
  public static function processUsageData($Rows, &$Dataset, $Labels) {
    $Dataset['labels'] = [];
    $Dataset['data'] = [];

    foreach ($Rows as $row){
      $Dataset['labels'][] = $row['key'];
      $Dataset['data'][] = $row['cnt'];
    }

    foreach ($Labels as $ix => $label){
      if (empty($Dataset['labels'][$ix]) || $Dataset['labels'][$ix] !== $label){
        array_splice($Dataset['labels'], $ix, 0, [$label]);
        array_splice($Dataset['data'], $ix, 0, [0]);
      }
    }

    unset($Dataset['labels']);
  }

  /**
   * Post-process time-based statistics data
   *
   * @param array $Data
   */
  public static function postprocessTimedData(&$Data) {
    /** @noinspection ForeachSourceInspection */
    foreach ($Data['labels'] as $k => $l)
      $Data['labels'][$k] = strtotime($l);

    while (true){
      $break = true;
      $labelCount = count($Data['labels']);
      for ($lix = 1; $lix < $labelCount; $lix++){
        $diff = $Data['labels'][$lix] - $Data['labels'][$lix - 1];
        if ($diff > Time::IN_SECONDS['day']){
          $break = false;
          break;
        }
      }
      if ($break)
        break;

      array_splice($Data['labels'], $lix, 0, [$Data['labels'][$lix - 1] + Time::IN_SECONDS['day']]);
      /** @noinspection ForeachSourceInspection */
      foreach ($Data['datasets'] as &$set)
        array_splice($set['data'], $lix, 0, [0]);
      unset($set);
    }

    /** @noinspection ForeachSourceInspection */
    foreach ($Data['labels'] as $k => $ts)
      $Data['labels'][$k] = date('c', $ts);
  }
}
