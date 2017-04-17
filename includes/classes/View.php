<?php

namespace App;

class View {
	/** @var string */
	public $name;
	function __construct(string $name){
		$this->name = $name;
	}

	private function _requirePath():string {
		return INCPATH."views/{$this->name}.php";
	}

	function __toString():string {
		return $this->_requirePath();
	}
}
