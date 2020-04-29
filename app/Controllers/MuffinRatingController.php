<?php

namespace App\Controllers;

use App\File;

class MuffinRatingController extends Controller {
  protected static $auth = false;

  public function image() {
    $ScorePercent = 100;
    if (isset($_GET['w']) && is_numeric($_GET['w']))
      $ScorePercent = min(max((int)$_GET['w'], 0), 100);
    $RatingFile = File::get(APPATH.'img/muffin-rating.svg');
    header('Content-Type: image/svg+xml');
    echo str_replace("width='100'", "width='$ScorePercent'", $RatingFile);
    exit;
  }
}
