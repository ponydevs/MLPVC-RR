<?php

use App\Models\DeviantartUser;
use PHPUnit\Framework\TestCase;

class DeviantartUserTest extends TestCase {
  public function testToURL() {
    $User = new DeviantartUser([
      'name' => 'TestUser',
      'avatar_url' => '/img/guest.svg',
    ]);
    $result = $User->toURL();
    static::assertEquals('https://www.deviantart.com/testuser', $result, 'Testing URL format return value');
  }

  public function testToAnchor() {
    $User = new DeviantartUser([
      'name' => 'TestUser',
      'avatar_url' => '/img/guest.svg',
    ]);
    $result = $User->toAnchor();
    static::assertEquals('<a href="https://www.deviantart.com/testuser" class="da-userlink"><span class="name">TestUser</span></a>', $result, 'Testing default parameter return value format');
    $result = $User->toAnchor(WITH_AVATAR);
    static::assertEquals('<a href="https://www.deviantart.com/testuser" class="da-userlink with-avatar"><img src=\'/img/guest.svg\' class=\'avatar\' alt=\'avatar\'> <span class="name">TestUser</span></a>', $result, 'Testing default parameter return value format');
  }
}
