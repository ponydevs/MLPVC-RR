<?php

namespace App\Controllers;

use App\CoreUtils;
use App\Permission;
use App\Twig;
use PDOException;
use RuntimeException;

class DiagnoseController extends Controller {
  public function __construct() {
    parent::__construct();

    if (Permission::insufficient('developer'))
      CoreUtils::noPerm();
  }

  const TESTABLE_EXCEPTIONS = [
    'runtime' => RuntimeException::class,
    'twig' => true,
    'pdo' => PDOException::class,
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
    else $time = (int)$params['time'];

    CoreUtils::fixPath("/diagnose/lt/$time");

    for ($i = 0; $i < $time; $i++) {
      $since = $time - $i;
      echo sprintf("Sleeping for %d second%s&hellip;<br>\n", $since, $since !== 1 ? 's' : '');
      sleep(1);
    }

    echo sprintf("Loaded after waiting %d second%s.", $time, $time !== 1 ? 's' : '');
  }
}
