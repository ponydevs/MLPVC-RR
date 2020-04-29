<?php

namespace App\Controllers;

use App\CoreUtils;
use App\Models\DeviantartUser;

trait UserLoaderTrait {
  /** @var DeviantartUser|null */
  private $user;

  private function load_user($params) {
    if (!isset($params['id']))
      CoreUtils::notFound();

    $this->user = DeviantartUser::find($params['id']);

    if (empty($this->user))
      CoreUtils::notFound();
  }
}
