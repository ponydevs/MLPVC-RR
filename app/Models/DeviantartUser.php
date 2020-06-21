<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\DeviantArt;

/**
 * @property string             $id
 * @property string             $name
 * @property string             $avatar_url
 * @property string             $role
 * @property DateTime           $created_at
 * @property int                $user_id
 * @property string             $access         (oAuth)
 * @property string             $refresh        (oAuth)
 * @property string             $scope          (oAuth)
 * @property DateTime           $access_expires (oAuth)
 * @property User               $user           (Via relations)
 * @property PreviousUsername[] $previous_names (Via relations)
 * @method static DeviantartUser find(...$args)
 * @method static DeviantartUser find_by_name(string $name)
 * @method static DeviantartUser find_by_user_id(int $user_id)
 */
class DeviantartUser extends NSModel implements Linkable {
  public static $table_name = 'deviantart_users';

  public static $has_many = [
    ['previous_names', 'class' => 'PreviousUsername', 'foreign_key' => 'user_id', 'order' => 'username asc'],
  ];

  public static $belongs_to = [
    ['user'],
  ];

  public function toURL():string {
    return 'https://www.deviantart.com/'.strtolower($this->name);
  }

  /**
   * DeviantArt profile link generator
   *
   * @param bool $show_avatar
   *
   * @return string
   */
  public function toAnchor(bool $show_avatar = false):string {
    $link = $this->toURL();

    $avatar = $show_avatar ? "<img src='{$this->avatar_url}' class='avatar' alt='avatar'> " : '';
    $with_avatar = $show_avatar ? ' with-avatar' : '';

    return <<<HTML
    <a href="$link" class="da-userlink$with_avatar">$avatar<span class="name">{$this->name}</span></a>
    HTML;
  }

  public function isLinked(): bool {
    return $this->access !== null;
  }

  /**
   * Checks if a user is a club member
   *
   * @return bool
   */
  public function isClubMember():bool {
    return $this->getClubRole() !== null;
  }

  public function getClubRole():?string {
    return DeviantArt::getClubRoleByName($this->name);
  }

  /**
   * Returns whether the user has permissions for the specified role
   * @deprecated
   *
   * @param string $role
   *
   * @return bool
   */
  public function perm(string $role):bool {
    return $this->user->perm($role);
  }

  public function getOpenSubmissionsURL(): string {
    return "https://www.deviantart.com/mlp-vectorclub/messages/?log_type=1&instigator_module_type=21&instigator_username={$this->name}&bpp_status=3&display_order=desc";
  }
}
