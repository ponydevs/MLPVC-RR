<?php

namespace App\Controllers;

use App\CoreUtils;
use App\Permission;
use App\Twig;

class DiagnoseController extends Controller {
  public function __construct() {
    parent::__construct();

    if (Permission::insufficient('developer'))
      CoreUtils::noPerm();
  }

  const TESTABLE_EXCEPTIONS = [
    'runtime' => \RuntimeException::class,
    'twig' => \Twig\Error\RuntimeError::class,
    'pdo' => \PDOException::class,
  ];

  public function exception($params) {
    $type = $params['type'] ?? null;
    if (!isset(self::TESTABLE_EXCEPTIONS[$type]))
      $type = 'runtime';

    CoreUtils::fixPath("/diagnose/ex/$type");

    $class = self::TESTABLE_EXCEPTIONS[$type];
    switch ($type){
      case 'twig':
        Twig::$env->render('diagnose/exception.html.twig');
      break;
      default:
        throw new $class();
    }

    echo 'Unreachable code reached';
  }

  public function loadtime($params) {
    if (!is_numeric($params['time'] ?? null))
      $time = 30;
    else $time = \intval($params['time'], 10);

    CoreUtils::fixPath("/diagnose/lt/$time");

    sleep($time);
    echo "Loaded after waiting $time second(s).";
  }
}
