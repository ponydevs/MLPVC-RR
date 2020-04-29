<?php

namespace App\Exceptions;

use Exception;

class UnsupportedProviderException extends Exception {
  public function __construct() {
    parent::__construct("Unsupported provider. Try uploading your image to <a href='http://sta.sh' target='_blank' rel='noopener'>Sta.sh</a>");
  }
}
