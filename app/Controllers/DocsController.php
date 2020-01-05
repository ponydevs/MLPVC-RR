<?php

namespace App\Controllers;

use App\CoreUtils;
use App\Models\Session;
use App\Permission;
use App\RegExp;
use App\Response;

class DocsController extends Controller {
  public function index() {
    CoreUtils::removeCSPHeaders();
    CoreUtils::generateApiSchema(CoreUtils::env('PRODUCTION'));
    CoreUtils::loadPage(__METHOD__);
  }
}
