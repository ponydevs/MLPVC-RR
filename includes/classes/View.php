<?php

namespace App;

class View {
	/** @var string */
	public $name;
	public function __construct(string $name){
		$this->name = $name;
	}

	private function _requirePath():string {
		return INCPATH."views/{$this->name}.php";
	}

	public function __toString():string {
		return $this->_requirePath();
	}
}
