<?php

namespace App;

class Response {
	static function fail(string $message = '', $data = array(), bool $prettyPrint = false){
		if (empty($message)){
			$message = Auth::$signed_in ? 'Insufficient permissions.' : '<p>You are not signed in (or your session expired).</p><p class="align-center"><button class="typcn green da-login" id="turbo-sign-in" data-url="'.OAUTH_AUTHORIZATION_URL.'">Sign back in</button></p>';
		}

		self::_respond(false, $message, $data, $prettyPrint);
	}
	static function dbError(string $message = '', bool $prettyPrint = false){
		global $Database;

		if (!empty($message))
			$message .= ': ';
		$message .= rtrim('Error while saving to database: '.$Database->getLastError(), ': ');

		self::_respond(false, $message, array(), $prettyPrint);
	}

	static function success(string $message, $data = array(), bool $prettyPrint = false){
		self::_respond(true, $message, $data, $prettyPrint);
	}
	static function done(array $data = array(), bool $prettyPrint = false){
		self::_respond(true, '', $data, $prettyPrint);
	}

	static private function _respond(bool $status, string $message, $data, bool $prettyPrint){
		header('Content-Type: application/json');
		$response = array('status' => $status);
		if (!empty($message))
			$response['message'] = $message;
		if (!empty($data) && is_array($data))
			$response = array_merge($data, $response);
		$mask = JSON_UNESCAPED_SLASHES;
		if ($prettyPrint)
			$mask |= JSON_PRETTY_PRINT;
		echo JSON::encode($response, $mask);
		exit;
	}
}
