<?php

namespace App\Models;

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

	/**
	 * Converts the object to an array, optionally removing empty (=== null) fields
	 *
	 * @param bool $remove_empty If true, removes keys with null as their value from the array
	 *
	 * @return array
	 */
	public function toArray(bool $remove_empty = false):array {
		$arr = (array)$this;
		if ($remove_empty)
			$arr = array_filter($arr, function($value) { return !is_null($value); });
		return $arr;
	}
}
