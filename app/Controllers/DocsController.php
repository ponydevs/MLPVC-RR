<?php

namespace App\Controllers;

use App\CoreUtils;

class DocsController extends Controller {
  public function index() {
    CoreUtils::removeCSPHeaders();
    CoreUtils::generateApiSchema(CoreUtils::env('PRODUCTION'));
    CoreUtils::loadPage(__METHOD__);
  }
}
