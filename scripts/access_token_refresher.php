<?php

require __DIR__.'/../config/init/minimal.php';
require __DIR__.'/../config/init/monolog.php';

use App\Auth;
use App\CoreUtils;

function dyn_log(string $message) {
  if (posix_isatty(STDOUT))
    echo $message."\n";
  else CoreUtils::error_log(basename(__FILE__).": $message");
}

try {
  if (empty($argv[1])){
    dyn_log('Session ID is not specified');
    exit(1);
  }

  $session_id = strtolower($argv[1]);
  if (!preg_match('~^[a-f\d-]+$~', $session_id)){
    dyn_log("Session ID is malformed: $session_id");
    exit(2);
  }

  Auth::$session = \App\Models\Session::find($session_id);
  if (empty(Auth::$session)){
    dyn_log("Session not found for ID: $session_id");
    exit(3);
  }
  if (empty(Auth::$session->refresh)){
    dyn_log("Session $session_id had no refresh token, deleting.");
    Auth::$session->delete();
    exit(4);
  }
  Auth::$user = Auth::$session->user;

  if (Auth::$session->expired){
    try {
      \App\DeviantArt::refreshAccessToken();
    }
    catch (Throwable $e){
      $code = ($e instanceof \App\Exceptions\CURLRequestException ? 'HTTP ' : '').$e->getCode();
      dyn_log('Session refresh failed for '.Auth::$user->name.' ('.Auth::$user->id.") | {$e->getMessage()} ($code)");
      Auth::$session->delete();
      Auth::$signed_in = false;
      exit(5);
    }
  }

  Auth::$signed_in = true;
  Auth::$session->updating = false;
  Auth::$session->save();
}
catch (Throwable $e){
  dyn_log('Uncaught error: '.$e->getMessage());
}
