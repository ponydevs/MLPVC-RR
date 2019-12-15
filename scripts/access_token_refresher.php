<?php

require __DIR__.'/../config/init/minimal.php';
require __DIR__.'/../config/init/monolog.php';

use App\Auth;
use App\CoreUtils;
use App\DeviantArt;
use App\Models\Session;

try {
  if (empty($argv[1])){
    CoreUtils::logToTtyOrFile(__FILE__, 'Session ID is not specified');
    exit(1);
  }

  $session_id = strtolower($argv[1]);
  if (!preg_match('~^[a-f\d-]+$~', $session_id)){
    CoreUtils::logToTtyOrFile(__FILE__, "Session ID is malformed: $session_id");
    exit(2);
  }

  Auth::$session = Session::find($session_id);
  if (empty(Auth::$session)){
    CoreUtils::logToTtyOrFile(__FILE__, "Session not found for ID: $session_id");
    exit(3);
  }
  if (empty(Auth::$session->refresh)){
    CoreUtils::logToTtyOrFile(__FILE__, "Session $session_id had no refresh token, deleting.");
    Auth::$session->delete();
    exit(4);
  }
  Auth::$user = Auth::$session->user;

  DeviantArt::gracefullyRefreshAccessTokenImmediately(AND_DIE);
}
catch (Throwable $e){
  CoreUtils::logToTtyOrFile(__FILE__, 'Uncaught error: '.$e->getMessage());
}
