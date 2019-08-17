<?php

namespace App\Controllers;

use App\HTTP;

class EQGController extends Controller {
  public function redirectInt($params) {
    HTTP::tempRedirect("/movie/{$params['id']}");
  }

  public function redirectStr($params) {
    HTTP::tempRedirect("/movie/equestria-girls-{$params['id']}");
  }
}
