<?php

namespace App;

class View {
	/** @var string */
	public $name, $class, $method;
	public function __construct(string $name){
		[$this->class, $this->method] = self::processName($name);
		$this->name = "$this->class/$this->method";
	}

	public static function processName(string $name){
		$name = strtolower(preg_replace(new RegExp('List$'),'-list',$name));
		if (!preg_match(new RegExp('^(?:\\\\?app\\\\controllers\\\\)?([a-z]+)controller::([a-z-]+)$'), $name, $match))
			throw new \RuntimeException('Could not resolve view based on value '.$name);
		[$class, $method] = array_slice($match, 1, 2);
		return [$class, $method];
	}

	private function _requirePath():string {
		return INCPATH."views/{$this->name}.php";
	}

	public function __toString():string {
		return $this->_requirePath();
	}
}
