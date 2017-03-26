<?php

use PHPUnit\Framework\TestCase;
use App\Permission;
use App\Models\User;

class PermissionTest extends TestCase {
	function testSufficient(){
		self::assertEquals(false, Permission::sufficient('member','user'));
		self::assertEquals(true, Permission::sufficient('member','member'));
		self::assertEquals(true, Permission::sufficient('member','developer'));

		global $signedIn, $currentUser;
		$signedIn = true;
		$currentUser = new User(['role' => 'user']);
		self::assertEquals(false, Permission::sufficient('member'));
		$currentUser = new User(['role' => 'member']);
		self::assertEquals(true, Permission::sufficient('member'));
		$currentUser = new User(['role' => 'developer']);
		self::assertEquals(true, Permission::sufficient('member'));
	}
}
