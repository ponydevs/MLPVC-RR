<?php

namespace App\Models;

abstract class AbstractUser extends AbstractFillable {
	/** @var string */
	public
		$id,
		$name,
		$avatar_url;
}
