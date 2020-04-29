<?php

use App\Models\DeviantartUser;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class UserTest extends TestCase {
  public function testToAnchor() {
    $User = new DeviantartUser([
      'name' => 'TestUser',
      'avatar_url' => '/img/guest.svg',
    ]);
    $result = $User->toAnchor();
    static::assertEquals("<a href='/@TestUser' class='da-userlink local'><span class='name'>TestUser</span></a>", $result, 'Testing default parameter return value format');
    $result = $User->toAnchor(DeviantartUser::WITH_AVATAR);
    static::assertEquals("<a href='/@TestUser' class='da-userlink local with-avatar provider-deviantart'><img src='/img/guest.svg' class='avatar' alt='avatar'><span class='name'>TestUser</span></a>", $result, 'Testing full format return value');
  }

  public function testToDALink() {
    $User = new DeviantartUser([
      'name' => 'TestUser',
      'avatar_url' => '/img/guest.svg',
    ]);
    $result = $User->toDALink();
    static::assertEquals('https://www.deviantart.com/testuser', $result, 'Testing URL format return value');
  }

  public function testToDAAnchor() {
    $User = new DeviantartUser([
      'name' => 'TestUser',
      'avatar_url' => '/img/guest.svg',
    ]);
    $result = $User->toDAAnchor();
    static::assertEquals("<a href='https://www.deviantart.com/testuser' class='da-userlink'><span class='name'>TestUser</span></a>", $result, 'Testing default parameter return value format');
    $result = $User->toDAAnchor(DeviantartUser::WITH_AVATAR);
    static::assertEquals("<a href='https://www.deviantart.com/testuser' class='da-userlink with-avatar'><img src='/img/guest.svg' class='avatar' alt='avatar'> <span class='name'>TestUser</span></a>", $result, 'Testing default parameter return value format');
  }

  public function testGetAvatarWrap() {
    $id = Uuid::uuid4();
    $User = new DeviantartUser([
      'id' => $id,
      'name' => 'TestUser',
      'avatar_url' => '/img/guest.svg',
    ]);
    $result = $User->getAvatarWrap(' app-illustrator');
    static::assertEquals("<div class='avatar-wrap provider-deviantart app-illustrator' data-for='$id'><img src='/img/guest.svg' class='avatar' alt='avatar'></div>", $result);
  }
}
