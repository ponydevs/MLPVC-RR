<?php

namespace App;

class NavBreadcrumb {
	/** @var string */
	private $name;

	/** @var string */
	private $link;

	/** @var bool */
	private $active, $enabled;

	/** @var NavBreadcrumb */
	private $child;

	function __construct(string $name, string $link = null, bool $active = false){
		$this->name = $name;
		$this->link = $link;
		$this->enabled = $link !== null;
		$this->active = $active;
	}

	function setActive(bool $value = true){
		$this->active = $value;

		return $this;
	}

	function setEnabled(bool $value){
		$this->enabled = $value;

		return $this;
	}

	/**
	 * @param string|NavBreadcrumb $ch
	 * @param bool                 $activate Whether to set child as active
	 *
	 * @return self
	 */
	function setChild($ch, bool $activate = false):NavBreadcrumb {
		if (is_string($ch)){
			$ch = new self($ch);
			$activate = true;
		}
		if ($activate === true)
			$ch->setActive();
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
		return $this->active ? "<strong>$name</strong>" : ($this->enabled ? "<a href='{$this->link}'>$name</a>" : "<span>$name</span>");
	}

	const DIV = '<span class="div">/</span>';

	function __toString():string {
		$HTML = [];
		$ptr = $this;
		do {
			$HTML[] = $ptr->toAnchor();
			$ptr = $ptr->getChild();
		}
		while ($ptr !== null);

		return self::DIV.implode(self::DIV, $HTML);
	}
}
