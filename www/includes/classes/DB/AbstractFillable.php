<?php

namespace DB;

abstract class AbstractFillable {
	/**
	 * Makes the class' properties fillable from an array/object for easy instantiation
	 *
	 * @param object       $child
	 * @param array|object $iteratable
	 */
	public function __construct($child, $iteratable = null){
		if (!empty($iteratable)){
			$childClassName = get_class($child);
			foreach ($iteratable as $k => $v)
				if (property_exists($childClassName, $k))
					$this->$k = $v;
		}
	}
}
