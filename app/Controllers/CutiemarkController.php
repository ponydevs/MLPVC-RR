<?php

namespace App\Controllers;

use App\CGUtils;
use App\CoreUtils;
use App\Models\Cutiemark;
use App\Permission;

class CutiemarkController extends ColorGuideController {
  public function view($params) {
    $cutiemark = Cutiemark::find($params['id']);
    if (empty($cutiemark) || $cutiemark->appearance->hidden())
      CoreUtils::notFound();

    CGUtils::renderCMSVG($cutiemark);
  }

  public function download($params) {
    $cutiemark = Cutiemark::find($params['id']);
    if (empty($cutiemark) || $cutiemark->appearance->hidden())
      CoreUtils::notFound();

    $source = isset($_REQUEST['source']) && Permission::sufficient('staff');

    $file = $source ? $cutiemark->getSourceFilePath() : $cutiemark->getRenderedFilePath();

    if (!$source && !file_exists($file))
      CGUtils::renderCMSVG($cutiemark, false);

    $filename = $cutiemark->label === null
      ? CoreUtils::posess($cutiemark->appearance->label).' Cutie Mark'
      : $cutiemark->appearance->label.' - '.$cutiemark->label;

    CoreUtils::downloadFile($file, $filename.($source ? ' (source)' : '').'.svg');
  }
}
