<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

class Permission {
  public const ROLES_ASSOC = [
    'guest' => 'Guest',
    'user' => 'DeviantArt User',
    'member' => 'Club Member',
    'assistant' => 'Assistant',
    'staff' => 'Staff',
    'admin' => 'Administrator',
    'developer' => 'Site Developer',
  ];
  public const ROLES = [
    'guest' => 1,
    'user' => 2,
    'member' => 3,
    'assistant' => 4,
    'staff' => 4,
    'admin' => 4,
    'developer' => 255,
  ];

  /**
   * Permission checking function
   * ----------------------------
   * Compares the currently logged in user's role to the one specified
   * A "true" return value means that the user meets the required role or surpasses it.
   * If user isn't logged in, and $compareAgainst is missing, returns false
   * If $compareAgainst is set then $role is used as the current user's role
   *
   * @param string      $role
   * @param string|null $compareAgainst
   *
   * @return bool
   */
  public static function sufficient(string $role, ?string $compareAgainst = null):bool {
    if (!isset(self::ROLES[$role]))
      throw new RuntimeException("Invalid role: $role");

    $comparison = $compareAgainst !== null;

    if ($comparison)
      $checkRole = $compareAgainst;
    else {
      if (!Auth::$signed_in)
        return false;
      $checkRole = Auth::$user->role;
    }

    return self::ROLES[$checkRole] >= self::ROLES[$role];
  }

  /**
   * Same as above, except the return value is inverted
   * Added for better code readability
   *
   * @param string      $role
   * @param string|null $compareAgainst
   *
   * @return bool
   */
  public static function insufficient(string $role, ?string $compareAgainst = null) {
    return !self::sufficient($role, $compareAgainst);
  }
}
