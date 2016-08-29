<?php

namespace DB;

abstract class AbstractFillable {
	/**
	 * Makes the class' properties fillable from an array/object for easy instantiation
	 *
	 * @param array|object
	 */
	public function __construct($iteratable = null){
		if (!empty($iteratable)){
			foreach ($iteratable as $k => $v)
				if (property_exists(get_called_class(), $k))
					$this->$k = $v;
		}
	}
}
