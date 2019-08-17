<?php

namespace App;

use App\Models\Appearance;
use App\Models\Cutiemark;

class Cutiemarks {
  /**
   * Fetches FRESH cutiemark data from the database instead of using the cached property
   * DO NOT REPLACE WITH ActiveRecord RELATIONS
   *
   * @param Appearance $Appearance
   *
   * @return Cutiemark[]
   */
  public static function get(Appearance $Appearance) {
    return DB::$instance->where('appearance_id', $Appearance->id)->get(Cutiemark::$table_name);
  }

  public const VALID_FACING_VALUES = ['left', 'right'];

  /**
   * @param Cutiemark[] $cutie_marks
   * @param bool        $wrap
   *
   * @return string
   */
  public static function getListForAppearancePage($cutie_marks, $wrap = WRAP) {
    return Twig::$env->render('appearances/_cutie_marks.html.twig', [
      'cutie_marks' => $cutie_marks,
      'wrap' => $wrap,
    ]);
  }

  /**
   * @param Cutiemark[] $CMs
   *
   * @return string
   */
  public static function convertDataForLogs($CMs):string {
    $out = [];
    foreach ($CMs as $v)
      $out[$v->id] = $v->to_array([
        'except' => ['id', 'appearance_id'],
      ]);

    return JSON::encode($out);
  }
}
