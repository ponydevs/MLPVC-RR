<?php

namespace App\Controllers;

use App\File;

class ManifestController extends Controller {
  protected static $auth = false;

  public function json() {
    $file = File::get(CONFPATH.'manifest.json');
    $file = str_replace('{{ABSPATH}}', ABSPATH, $file);
    header('Content-Type: application/json');
    echo $file;
  }
}
