<?php

namespace App;

use Monolog\Logger;

class UsefulLogger extends Logger {
    /**
     * @inheritdoc
     */
    public function addRecord($level, $message, array $context = array()){
        $context['ip'] = $_SERVER['REMOTE_ADDR'];
		$context['referrer'] = $_SERVER['HTTP_REFERER'] ?? null;
		$context['auth'] = Auth::$signed_in ? Auth::$user->to_array(['include' => [ 'session' => Auth::$session->id ]]) : null;
        parent::addRecord($level, $message, $context);
    }
}
