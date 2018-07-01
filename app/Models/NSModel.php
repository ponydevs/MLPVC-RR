<?php

namespace App\Models;

use ActiveRecord\Model;
use ActiveRecord\RecordNotFound;
use App\CoreUtils;

class NSModel extends Model {
	public static function find(...$args){
		try {
			return parent::find(...$args);
		}
		catch (RecordNotFound $e){
			return null;
		}
	}
}
