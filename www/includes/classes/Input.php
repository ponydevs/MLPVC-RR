<?php

	class Input {
		private $_type, $_source, $_key, $_origValue = null, $_value = null, $_respond = true, $_validator, $_range;

		static
			$SUPPORTED_TYPES = array(
				'exists' => true,
				'bool' => true,
				'int' => true,
				'float' => true,
				'text' => true,
				'string' => true,
				'uuid' => true,
				'username' => true,
				'int[]' => true,
				'json' => true,
				'timestamp' => true,
				'epid' => true,
			),
			$ERROR_NONE = 0,
			$ERROR_MISSING = 1,
			$ERROR_INVALID = 2,
			$ERROR_RANGE = 3;

		/**
		 * Creates a class instance based on the settings provided
		 * All options are optional and have default fallbacks
		 *
		 * $o = array(
		 *     'optional' => bool,          // Prevents 'error' when value does not exist
		 *     'throw' => bool,             // Throw exceptions instead of calling CoreUtils::Respond
		 *     'range' => [null/int, int],  // Range for validation
		 *     'errors' => array(           // Custom error strings
		 *         Input::$ERROR_MISSING => string,
		 *         Input::$ERROR_INVALID => string,
		 *         Input::$ERROR_RANGE => string,
		 *         'custom' => string,
		 *     )
		 * )
		 *
		 * @param string $key
		 * @param string $type
		 * @param array $o
		 *
		 * @return Input
		 */
		public function __construct($key, $type, $o = null){
			if (($o['throw']??false) === true)
				$_respond = false;
			if ($type instanceof RegExp)
				$this->_validator = function($value) use ($type){
					return $type->match($value) ? self::$ERROR_NONE : self::$ERROR_INVALID;
				};
			else if (is_callable($type))
				$this->_validator = $type;
			else if (empty(self::$SUPPORTED_TYPES[$type]))
				$this->_outputError('Input type is invalid');
			$this->_type = $type;

			if (!is_string($key))
				$this->_outputError('Input key missing or invalid');
			$this->_key = $key;

			if (!isset($_POST[$key]) || strlen($_POST[$key]) === 0)
				$result = empty($o['optional']) ? self::$ERROR_MISSING : self::$ERROR_NONE;
			else {
				$this->_value = $this->_type === 'text' ? CoreUtils::TrimMultiline($_POST[$key]) : CoreUtils::Trim($_POST[$key]);
				$this->_origValue = $this->_value;
				$this->_range = $o['range'] ?? null;

				$result = $this->_validate();
			}
			if ($result !== self::$ERROR_NONE)
				$this->_outputError(
					!empty($o['errors'][$result])
					? $o['errors'][$result]
					: "Error wile checking \$_POST['{$this->_key}'] (code $result)",
					$result
				);
		}

		/**
		 * Validates the input and returns an error code
		 *
		 * @return int
		 */
		private function _validate(){
			if (isset($this->_validator))
				return call_user_func($this->_validator, $this->_value, $this->_range) ?? self::$ERROR_NONE;
			switch ($this->_type){
				case "bool":
					if (!in_array($this->_value,array('1','0','true','false')))
						return self::$ERROR_INVALID;
					$this->_value = $this->_value == '1' || $this->_value == 'true';
				break;
				case "int":
				case "float":
					if (!is_numeric($this->_value))
						return self::$ERROR_INVALID;
					$this->_value = $this->_type === 'int'
						? intval($this->_value, 10)
						: floatval($this->_value, 10);
					if (self::CheckNumberRange($this->_value, $this->_range, $code))
						return $code;
				break;
				case "text":
				case "string":
					if (!is_string($this->_value))
						return self::$ERROR_INVALID;
					if (self::CheckStringLength($this->_value, $this->_range, $code))
						return $code;
				break;
				case "uuid":
					if (!is_string($this->_value) || !regex_match(new RegExp('^[a-f0-9]{8}\-[a-f0-9]{4}\-4[a-f0-9]{3}\-[89ab][a-f0-9]{3}\-[a-f0-9]{12}$','i'), $this->_value))
						return self::$ERROR_INVALID;

					$this->_value = strtolower($this->_value);
				break;
				case "username":
					global $USERNAME_REGEX;
					if (!is_string($this->_value) || !$USERNAME_REGEX->match($this->_value))
						return self::$ERROR_INVALID;
				break;
				case "int[]":
					if (!is_string($this->_value) || !regex_match(new RegExp('^\d{1,5}(?:,\d{1,5})+$'), $this->_value))
						return self::$ERROR_INVALID;

					$this->_value = explode(',',$this->_value);
				break;
				case "json":
					try {
						$this->_value = JSON::Decode($this->_value);
						if (empty($this->_value))
							throw new Exception(rtrim('Could not decode JSON; '.json_last_error(),'; '));
					}
					catch (Exception $e){
						error_log($e->getMessage()."\n".$e->getTraceAsString());
						return self::$ERROR_INVALID;
					}
				break;
				case "timestamp":
					$this->_value = strtotime($this->_value);
					if ($this->_value === false)
						return self::$ERROR_INVALID;
					if (self::CheckNumberRange($this->_value, $this->_range, $code))
						return $code;
				break;
				case "epid":
					$this->_value = Episode::ParseID($this->_value);
					if (empty($this->_value))
						return self::$ERROR_INVALID;
				break;
			}

			return self::$ERROR_NONE;
		}

		static function CheckStringLength($value, $range, &$code){
			return $code = self::_numberInRange(strlen($value), $range);
		}
		static function CheckNumberRange($value, $range, &$code){
			return $code = self::_numberInRange($value, $range);
		}

		private static function _numberInRange($n, $range){
			if (isset($range[0]) || isset($range[1])){
				if (isset($range[0]) && $n < $range[0])
					return self::$ERROR_RANGE;
				if (isset($range[1]) && $n > $range[1])
					return self::$ERROR_RANGE;
			}
			return self::$ERROR_NONE;
		}

		private function _outputError($message, $errorCode = null){
			$message = str_replace('@value', $this->_value, $message);
			if ($errorCode === self::$ERROR_RANGE && (isset($this->_range[0]) || isset($this->_range[1]))){
				if (isset($this->_range[0]))
					$message = str_replace('@min', $this->_range[0], $message);
				if (isset($this->_range[1]))
					$message = str_replace('@max', $this->_range[1], $message);
			}
			if ($errorCode === self::$ERROR_INVALID)
				$message = str_replace('@input', $this->_origValue, $message);
			if ($this->_respond)
				CoreUtils::Respond($message);
			throw new Exception($message);
		}

		public function out(){
			return $this->_value;
		}
	}
