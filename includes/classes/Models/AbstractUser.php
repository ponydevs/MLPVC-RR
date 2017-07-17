<?php

namespace App\Models;

use ActiveRecord\Model;

/**
 * @property string $id
 * @property string $name
 * @property string $avatar_url
 */
abstract class AbstractUser extends Model { }
