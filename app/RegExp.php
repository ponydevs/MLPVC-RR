<?php

namespace App;

class RegExp {
  /** @var string */
  private $_pattern, $_modifiers, $_delimiter, $_jsRegex, $_phpRegex;

  /**
   * Construct an instance of the class
   *
   * @param string $pattern
   * @param string $modifiers
   * @param string $delimiter
   */
  public function __construct(string $pattern, string $modifiers = '', string $delimiter = '~') {
    $this->_delimiter = $delimiter[0] ?? '~';
    $this->_pattern = $pattern;
    $this->_modifiers = mb_strlen($modifiers) ? $modifiers : '';
  }

  public function __toString():string {
    if (!isset($this->_phpRegex))
      $this->_phpRegex = $this->_delimiter.$this->_escape($this->_pattern, $this->_delimiter).$this->_delimiter.$this->_modifiers;

    return $this->_phpRegex;
  }

  public function jsExport():string {
    if (!isset($this->_jsRegex))
      $this->_jsRegex = '/'.$this->_escape($this->_pattern, '/').'/'.preg_replace('/[^img]/', '', $this->_modifiers);

    return $this->_jsRegex;
  }

  private function _escape(string $pattern, string $delimiter):string {
    $d = $delimiter === '~' ? '@' : '~';

    return preg_replace("$d([^\\\\])(".preg_quote($delimiter, $d).")$d", "$1\\\\$2", $pattern);
  }

  /**
   * @param string     $text
   * @param array|null $matches
   *
   * @return bool
   */
  public function match(string $text, array &$matches = null):bool {
    return (bool)preg_match($this->__toString(), $text, $matches);
  }

  /**
   * @param string $with
   * @param string $in
   * @param int    $limit
   * @param int    $count
   *
   * @return string|array
   */
  public function replace(string $with, string $in, int $limit = -1, int &$count = null) {
    return preg_replace($this->__toString(), $with, $in, $limit, $count);
  }

  /**
   * @param string $str
   *
   * @return string
   */
  public static function escapeBackslashes(string $str):string {
    return preg_replace('~(\\\\)~', '$1$1', $str);
  }
}
