<?php

require __DIR__.'/../config/init/minimal.php';
require __DIR__.'/../config/init/monolog.php';

use App\Auth;
use App\CoreUtils;
use App\DB;
use App\DeviantArt;
use App\Models\Session;

try {
  if (empty($argv[1])){
    CoreUtils::error_log(__FILE__ . ': Session ID is not specified');
    exit(1);
  }

  $raw_session_id = $argv[1];
  if (!preg_match('~^\d+$~', $raw_session_id)){
    CoreUtils::error_log(__FILE__ . ": Session ID is malformed: {$raw_session_id}");
    exit(2);
  }

  $session_id = (int) $raw_session_id;

  Auth::$session = Session::find($session_id);
  if (empty(Auth::$session)){
    CoreUtils::error_log(__FILE__ . ": Session not found for ID: $session_id");
    exit(3);
  }

  $user = Auth::$session->user;
  if ($user === null || !$user->isDeviantartLinked()) {
    // Session doesn't have an associated DeviantArt user, do nothing
    Auth::$session->updating = false;
    Auth::$session->save();
    return;
  }

  if (empty($user->deviantart_user->refresh)){
    CoreUtils::error_log(__FILE__ . ": DeviantArt user {$user->deviantart_user->id} had no refresh token, signing out all sessions.");
    DB::$instance->where('user_id', $user->id)->update('sessions', ['user_id' => null, 'updating' => false]);
    exit(4);
  }

  Auth::$user = $user;

  DeviantArt::gracefullyRefreshAccessTokenImmediately(AND_DIE);
}
catch (Throwable $e){
  CoreUtils::error_log(__FILE__ . ': Uncaught error: '.$e->getMessage());
}
