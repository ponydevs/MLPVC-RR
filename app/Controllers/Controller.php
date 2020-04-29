<?php

namespace App\Controllers;

use App\CoreUtils;
use App\CSRFProtection;
use App\Users;
use function is_array;

abstract class Controller {
  protected static $auth = true;
  /** @var string */
  protected $path;
  /** @var string */
  protected $action;
  /** @var bool */
  protected $creating;

  public function __construct() {
    $this->action = $_SERVER['REQUEST_METHOD'];
    $this->creating = $this->action === 'POST';

    switch ($this->action){
      case 'PUT':
      case 'DELETE':
        $in = file_get_contents('php://input');
        parse_str($in, $data);

        if (is_array($data) && !empty($data))
          foreach ($data as $k => $v){
            if (is_numeric($k))
              $_REQUEST[] = $v;
            else $_REQUEST[$k] = $v;
          }
      break;
    }

    CSRFProtection::protect();
    if (static::$auth) {
      Users::authenticate();
      CoreUtils::checkNutshell();
    }
  }
}
