<?php

namespace App\Controllers;

use App\CoreUtils;

class ComponentsController extends Controller {
  public function index() {
    CoreUtils::loadPage(__METHOD__, [
      'title' => 'Components',
      'noindex' => true,
    ]);
  }
}
