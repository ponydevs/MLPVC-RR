<?php

	class RegExp {
		private $_pattern, $_modifiers, $_delimiter, $_jsRegex, $_phpRegex;

		/**
		 * Construct an instance of the class
		 *
		 * @param string $pattern
		 * @param string $modifiers
		 * @param string $delimiter
		 *
		 * @return RegExp
		 */
		public function __construct($pattern, $modifiers = null, $delimiter = '~'){
			$this->_delimiter = isset($delimiter[0]) ? $delimiter[0] : '~';
			$this->_pattern = $pattern;
			$this->_modifiers = is_string($modifiers) ? $modifiers : '';
		}

		public function __toString(){
			if (!isset($this->_phpRegex))
				$this->_phpRegex = $this->_delimiter.$this->_escape($this->_pattern,$this->_delimiter).$this->_delimiter.$this->_modifiers;
			return $this->_phpRegex;
		}

		public function jsExport(){
			if (!isset($this->_jsRegex))
				$this->_jsRegex = '/'.$this->_escape($this->_pattern,'/').'/'.preg_replace('/[^img]/','',$this->_modifiers);
			return $this->_jsRegex;
		}

		private function _escape($pattern, $delimiter){
			$d = $delimiter === '~' ? '@' : '~';
			return preg_replace("$d([^\\\\])(".preg_quote($delimiter).")$d","$1\\\\$2",$pattern);
		}

		/**
		 * @param array $text
		 * @param array|null $matches
		 *
		 * @return bool
		 */
		public function match($text, &$matches = null){
			return (bool) preg_match($this->_phpRegex, $text, $matches);
		}

		/**
		 * @param string $with
		 * @param string $in
		 * @param int    $limit
		 * @param int    $count
		 *
		 * @return string|array
		 */
		public function replace($with, $in, $limit = -1, &$count = null){
			return preg_replace($this->_phpRegex,$with, $in, $limit = -1, $count = null);
		}
	}

	/**
	 * Match text against a RegExp
	 *
	 * @param RegExp     $regex   Regular Expression
	 * @param array      $text    Text to match
	 * @param array|null $matches Arry to output matches to
	 *
	 * @return bool
	 */
	function regex_match(RegExp $regex, $text, &$matches = null){
		return (bool) preg_match(strval($regex), $text, $matches);
	}

	/**
	 * Replace text using a RegExp
	 *
	 * @param RegExp     $regex Regular Expression
		 * @param string $with  Replacement
		 * @param string $in    String to replace in
		 * @param int    $limit
		 * @param int    $count
	 *
	 * @return string|array
	 */
	function regex_replace(RegExp $regex, $with, $in, $limit = -1, &$count = null){
		return preg_replace(strval($regex), $with, $in, $limit, $count);
	}
