<?php

use App\Models\DeviantartUser;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase {
  public function testToAnchor() {
    $user = new User([
      'id' => 0,
      'name' => 'TestUser',
    ]);
    $da_user = new DeviantartUser([
      'user_id' => 0,
      'avatar_url' => '/img/guest.svg',
    ]);
    $user->set_relationship_from_eager_load($da_user, 'deviantart_user');
    $result = $user->toAnchor();
    static::assertEquals("<a href='/users/0-TestUser' class='da-userlink local'><span class='name'>TestUser</span></a>", $result, 'Testing default parameter return value format');
    $result = $user->toAnchor(WITH_AVATAR);
    static::assertEquals("<a href='/users/0-TestUser' class='da-userlink local with-avatar'><img src='/img/guest.svg' class='avatar' alt='avatar'><span class='name'>TestUser</span></a>", $result, 'Testing full format return value');
  }

  public function testGetAvatarWrap() {
    $id = 0;
    $user = new User([
      'id' => $id,
      'name' => 'TestUser',
    ]);
    $da_user = new DeviantartUser([
      'user_id' => 0,
      'avatar_url' => '/img/guest.svg',
    ]);
    $user->set_relationship_from_eager_load($da_user, 'deviantart_user');
    $result = $user->getAvatarWrap(' app-illustrator');
    static::assertEquals("<div class='avatar-wrap app-illustrator' data-for='$id'><img src='/img/guest.svg' class='avatar' alt='avatar'></div>", $result);
  }
}
