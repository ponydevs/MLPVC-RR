<?php

namespace App;

use App\Models\Episode;

class Input {
	private $_type, $_source, $_key, $_origValue = null, $_value = null, $_respond = true, $_validator, $_range, $_silentFail;
	private static $SUPPORTED_TYPES = [
		'exists' => true,
		'bool' => true,
		'int' => true,
		'vote' => true,
		'float' => true,
		'text' => true,
		'string' => true,
		'uuid' => true,
		'username' => true,
		'url' => true,
		'int[]' => true,
		'json' => true,
		'timestamp' => true,
		'epid' => true,
	];

	const
		IS_OPTIONAL = 'optional',
		SILENT_FAILURE = 'silent',
		CUSTOM_ERROR_MESSAGES = 'errors',
		THROW_EXCEPTIONS = 'throw',
		IN_RANGE = 'range',
		METHOD_GET = 'GET',
		ERROR_NONE = 0,
		ERROR_MISSING = 1,
		ERROR_INVALID = 2,
		ERROR_RANGE = 3;

	/**
	 * Creates a class instance based on the settings provided
	 * All options are optional and have default fallbacks
	 *
	 * $o = array(
	 *     // Prevents $ERROR_MISSING from being triggered
	 *     Input::$IS_OPTIONAL => bool,
	 *     // Throw exceptions instead of calling CoreUtils::Respond
	 *     Input::$THROW_EXCEPTIONS => bool,
	 *     // Range for length/size validation (choose one)
	 *     Input::$IN_RANGE => [int],        // input >= int
	 *     Input::$IN_RANGE => [int1, int2], // input >= $mix && input <= $max
	 *     Input::$IN_RANGE => [null, int],  // input <= int
	 *     // Custom error strings
	 *     Input::$CUSTOM_ERROR_MESSAGES => array(
	 *         Input::$ERROR_MISSING => string,
	 *         Input::$ERROR_INVALID => string,
	 *         Input::$ERROR_RANGE => string,
	 *         'custom' => string,
	 *     )
	 * )
	 *
	 * @param string                 $key
	 * @param string|RegExp|callable $type
	 * @param array                  $o
	 *
	 * @return Input
	 */
	public function __construct($key, $type, $o = null){
		if (isset($o[self::THROW_EXCEPTIONS]))
			$this->_respond = $o[self::THROW_EXCEPTIONS] === false;
		if ($type instanceof RegExp)
			$this->_validator = function($value) use ($type){
				return $type->match($value) ? self::ERROR_NONE : self::ERROR_INVALID;
			};
		else if (is_callable($type))
			$this->_validator = $type;
		else /** @var $type string */
		  if (empty(self::$SUPPORTED_TYPES[$type]))
			$this->_outputError('Validation failed: Input type is invalid');
		$this->_type = $type;

		if (!is_string($key))
			$this->_outputError('Input key missing or invalid');
		$this->_key = $key;

		$this->_silentFail = isset($o[self::SILENT_FAILURE]) && $o[self::SILENT_FAILURE] === true;

		$this->_source = $SRC = isset($o[self::METHOD_GET]) && $o[self::METHOD_GET] === true ? '_GET' : '_POST';
		$_SRC = $GLOBALS[$SRC];
		if (!isset($_SRC[$key]) || CoreUtils::length($_SRC[$key]) === 0)
			$result = empty($o[self::IS_OPTIONAL]) ? self::ERROR_MISSING : self::ERROR_NONE;
		else {
			$this->_value = $this->_type === 'text' ? CoreUtils::trim($_SRC[$key], true) : CoreUtils::trim($_SRC[$key]);
			$this->_origValue = $this->_value;
			$this->_range = $o[self::IN_RANGE] ?? null;

			$result = $this->_validate();
		}
		if ($result !== self::ERROR_NONE)
			$this->_outputError(
				!empty($o[self::CUSTOM_ERROR_MESSAGES][$result])
				? $o[self::CUSTOM_ERROR_MESSAGES][$result]
				: "Error wile checking \${$SRC}['{$this->_key}'] (code $result)",
				$result
			);
	}

	/**
	 * Validates the input and returns an error code
	 *
	 * @return int
	 */
	private function _validate(){
		if (isset($this->_validator)){
			$call_params = [&$this->_value, $this->_range];
			return call_user_func_array($this->_validator, $call_params) ?? self::ERROR_NONE;
		}
		switch ($this->_type){
			case 'bool':
				if (!in_array($this->_value, ['1', '0', 'true', 'false', 'on', 'off'], false))
					return self::ERROR_INVALID;
				$this->_value = in_array($this->_value, ['1', 'true', 'on'], false);
			break;
			case 'int':
			case 'vote':
			case 'float':
				if (!is_numeric($this->_value))
					return self::ERROR_INVALID;
				$this->_value = $this->_type === 'float'
					? (float) $this->_value
					: (int) $this->_value;
				if ($this->_type === 'vote' && $this->_value === 0)
					return self::ERROR_INVALID;
				if (self::checkNumberRange($this->_value, $this->_range, $code))
					return $code;
			break;
			case 'text':
			case 'string':
				if (!is_string($this->_value))
					return self::ERROR_INVALID;
				if (self::checkStringLength($this->_value, $this->_range, $code))
					return $code;
			break;
			case 'uuid':
				if (!is_string($this->_value) || !preg_match(new RegExp('^[a-f0-9]{8}\-[a-f0-9]{4}\-4[a-f0-9]{3}\-[89ab][a-f0-9]{3}\-[a-f0-9]{12}$','i'), $this->_value))
					return self::ERROR_INVALID;

				$this->_value = strtolower($this->_value);
			break;
			case 'username':
				global $USERNAME_REGEX;
				if (!is_string($this->_value) || !$USERNAME_REGEX->match($this->_value))
					return self::ERROR_INVALID;
			break;
			case 'url':
				if (!is_string($this->_value))
					return self::ERROR_INVALID;
				global $REWRITE_REGEX;
				if (stripos($this->_value, ABSPATH) === 0)
					$this->_value = CoreUtils::substring($this->_value, CoreUtils::length(ABSPATH)-1);
				if (!preg_match($REWRITE_REGEX,$this->_value) && !preg_match(new RegExp('^#[a-z\-]+$'),$this->_value)){
					if (self::checkStringLength($this->_value, $this->_range, $code))
						return $code;
					if (!preg_match(new RegExp('^https?://[a-z\d/.-]+(?:/[ -~]+)?$','i'), $this->_value))
						Response::fail('Link URL does not appear to be a valid link');
				}
			break;
			case 'int[]':
				if (!is_string($this->_value) || !preg_match(new RegExp('^\d{1,12}(?:,\d{1,12})*$'), $this->_value))
					return self::ERROR_INVALID;

				$this->_value = explode(',',$this->_value);
			break;
			case 'json':
				try {
					$this->_value = JSON::decode($this->_value);
					if (empty($this->_value))
						throw new \Exception(rtrim('Could not decode JSON; '.json_last_error(),'; '));
				}
				catch (\Exception $e){
					error_log($e->getMessage()."\n".$e->getTraceAsString());
					return self::ERROR_INVALID;
				}
			break;
			case 'timestamp':
				$this->_value = strtotime($this->_value);
				if ($this->_value === false)
					return self::ERROR_INVALID;
				if (self::checkNumberRange($this->_value, $this->_range, $code))
					return $code;
			break;
			case 'epid':
				$this->_value = Episode::parseID($this->_value);
				if (empty($this->_value))
					return self::ERROR_INVALID;
			break;
		}

		return self::ERROR_NONE;
	}

	public static function checkStringLength($value, $range, &$code){
		$code = self::_numberInRange(CoreUtils::length($value), $range);
		return $code;
	}
	public static function checkNumberRange($value, $range, &$code = false){
		$result = self::_numberInRange($value, $range);
		return $code === false ? $result === self::ERROR_RANGE : $result;
	}

	private static function _numberInRange($n, $range){
		$hasmin = isset($range[0]);
		$hasmax = isset($range[1]);
		if ($hasmin || $hasmax){
			if ($hasmin ? $n < $range[0] : $n < 1)
				return self::ERROR_RANGE;
			if ($hasmax && $n > $range[1])
				return self::ERROR_RANGE;
		}
		return self::ERROR_NONE;
	}

	private function _outputError($message, $errorCode = null){
		$message = str_replace('@value', CoreUtils::escapeHTML($this->_value), $message);
		if ($errorCode === self::ERROR_RANGE){
			if (isset($this->_range[0]))
				$message = str_replace('@min', $this->_range[0], $message);
			if (isset($this->_range[1]))
				$message = str_replace('@max', $this->_range[1], $message);
		}
		if ($this->_silentFail)
			return error_log("Silenced Input validation error: $message\nKey: $this->_key\nOptions: _source={$this->_source}, _origValue={$this->_origValue}, _respond={$this->_respond}, request_uri={$_SERVER['REQUEST_URI']}");
		if ($this->_respond)
			Response::fail($message);
		throw new \Exception($message);
	}

	public function out(){
		return $this->_value;
	}
}
