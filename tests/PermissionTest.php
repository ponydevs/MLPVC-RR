<?php

use App\Auth;
use App\Models\DeviantartUser;
use App\Permission;
use PHPUnit\Framework\TestCase;

class PermissionTest extends TestCase {
  public function testSufficient() {
    self::assertEquals(false, Permission::sufficient('member', 'user'));
    self::assertEquals(true, Permission::sufficient('member', 'member'));
    self::assertEquals(true, Permission::sufficient('member', 'developer'));

    Auth::$signed_in = true;
    Auth::$user = new DeviantartUser(['role' => 'user']);
    self::assertEquals(false, Permission::sufficient('member'));
    Auth::$user = new DeviantartUser(['role' => 'member']);
    self::assertEquals(true, Permission::sufficient('member'));
    Auth::$user = new DeviantartUser(['role' => 'developer']);
    self::assertEquals(true, Permission::sufficient('member'));
  }
}
