<?php

namespace App;

class NavBreadcrumb {
	/** @var string */
	private $name;

	/** @var string */
	private $link;

	/** @var bool */
	private $active;

	/** @var NavBreadcrumb */
	private $child;

	function __construct(string $name, string $link = null){
		$this->name = $name;
		$this->link = $link;
		$this->active = $link === null;
	}

	function setActive(bool $value = true){
		$this->active = $value;

		return $this;
	}

	/**
	 * @param string|NavBreadcrumb $ch
	 *
	 * @return self
	 */
	function setChild($ch):NavBreadcrumb {
		if (is_string($ch))
			$ch = new self($ch);
		$this->child = $ch;

		return $this;
	}

	/**
	 * Returns the last breadcrumb in the chain to make appending easier
	 *
	 * @return self
	 */
	function end():NavBreadcrumb {
		$end = $this;
		while ($end->child !== null)
			$end = $end->child;

		return $end;
	}

	function getChild():?NavBreadcrumb {
		return $this->child;
	}

	function toAnchor(){
		$name = CoreUtils::escapeHTML($this->name);
		return $this->active ? "<span>$name</span>" : "<a href='{$this->link}'>$name</a>";
	}

	function __toString():string {
		$HTML = [];
		$ptr = $this;
		do {
			$HTML[] = $ptr->toAnchor();
			$ptr = $ptr->getChild();
		}
		while ($ptr !== null);

		return implode(' &rsaquo; ', $HTML);
	}
}
