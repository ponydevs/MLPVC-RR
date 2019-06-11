<?php

namespace App;

class Response {
	public static function fail(string $message = '', $data = [], bool $prettyPrint = false){
		if (empty($message)){
			$message = Auth::$signed_in ? 'Insufficient permissions.' : '<p>You are not signed in (or your session expired).</p><p class="align-center"><button class="typcn green btn-da da-login" id="turbo-sign-in" data-url="/da-auth/begin">Sign back in</button></p>';
		}

		self::_respond(false, $message, $data, $prettyPrint);
	}

	public static function failApi(string $message = '', $data = [], bool $prettyPrint = false){
		if (empty($message)){
			$message = Auth::$signed_in
				? 'You do not have permission to access the requested resource'
				: 'The requested resource requires authentication';
		}

		self::_respond(false, $message, $data, $prettyPrint);
	}

	public static function dbError(string $message = '', bool $prettyPrint = false){
		if (!empty($message))
			$message .= ': ';
		$message .= rtrim('Error while saving to database: '.DB::$instance->getLastError(), ': ');

		self::_respond(false, $message, [], $prettyPrint);
	}

	public static function success(string $message, $data = [], bool $prettyPrint = false){
		self::_respond(true, $message, $data, $prettyPrint);
	}
	public static function done(array $data = [], bool $prettyPrint = false){
		self::_respond(true, '', $data, $prettyPrint);
	}

	private static function _respond(bool $status, string $message, $data, bool $prettyPrint){
		header('Content-Type: application/json');
		$response = ['status' => $status];
		if (!empty($message))
			$response['message'] = $message;
		if (!empty($data) && \is_array($data))
			$response = array_merge($data, $response);
		$mask = JSON_UNESCAPED_SLASHES;
		if ($prettyPrint)
			$mask |= JSON_PRETTY_PRINT;
		echo JSON::encode($response, $mask);
		exit;
	}
}
