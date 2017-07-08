<?php

use PHPUnit\Framework\TestCase;

class UserTest extends TestCase {
	public function testGetProfileLink(){
		$User = new \App\Models\User([
			'name' => 'TestUser',
			'avatar_url' => '/img/guest.svg',
		]);
		$result = $User->getProfileLink();
		static::assertEquals("<a href='/@TestUser' class='da-userlink'><span class='name'>TestUser</span></a>", $result, 'Testing default parameter return value format');
		$result = $User->getProfileLink(\App\Models\User::LINKFORMAT_FULL);
		static::assertEquals("<a href='/@TestUser' class='da-userlink with-avatar'><img src='/img/guest.svg' class='avatar' alt='avatar'> <span class='name'>TestUser</span></a>", $result, 'Testing full format return value');
	}

	public function testGetDALink(){
		$User = new \App\Models\User([
			'name' => 'TestUser',
			'avatar_url' => '/img/guest.svg',
		]);
		$result = $User->getDALink(\App\Models\User::LINKFORMAT_URL);
		static::assertEquals('http://testuser.deviantart.com/', $result, 'Testing URL format return value');
		$result = $User->getDALink(\App\Models\User::LINKFORMAT_TEXT);
		static::assertEquals("<a href='http://testuser.deviantart.com/' class='da-userlink'><span class='name'>TestUser</span></a>", $result, 'Testing default parameter return value format');
		$result = $User->getDALink();
		static::assertEquals("<a href='http://testuser.deviantart.com/' class='da-userlink with-avatar'><img src='/img/guest.svg' class='avatar' alt='avatar'> <span class='name'>TestUser</span></a>", $result, 'Testing default parameter return value format');
	}

	public function testGetAvatarWrap(){
		$User = new \App\Models\User([
			'name' => 'TestUser',
			'avatar_url' => '/img/guest.svg',
		]);
		$result = $User->getAvatarWrap(' app-illustrator');
		static::assertEquals("<div class='avatar-wrap app-illustrator'><img src='/img/guest.svg' class='avatar' alt='avatar'></div>", $result);
	}
}
