<?php

namespace App;

use App\Models\Appearance;
use App\Models\Session;
use App\Models\Show;
use App\Models\User;
use RuntimeException;
use function array_slice;

class View {
  /** @var string */
  public $name, $class, $method;

  public function __construct(string $name) {
    [$this->class, $this->method] = self::processName($name);
    $this->name = "$this->class/$this->method";
  }

  /**
   * Turns a string in the format of SomeThingController::methodNameHere into ['something', 'method-name-here']
   *
   * @param string $name
   *
   * @return string[]
   * @throws RuntimeException
   */
  public static function processName(string $name):array {
    [$controller, $method] = explode('::', $name);
    $name = strtolower("$controller::".preg_replace('/([a-z])([A-Z])/', '$1-$2', $method));
    if (!preg_match('~^(?:\\\\?app\\\\controllers\\\\)?([a-z]+)controller::([a-z-]+)$~', $name, $match))
      throw new RuntimeException("Could not resolve view based on value $name");
    [$class, $method] = array_slice($match, 1, 2);

    return [$class, $method];
  }

  public function __toString():string {
    return $this->name;
  }

  public function getBreadcrumb(array $scope = []):?NavBreadcrumb {
    switch ($this->class){
      case 'about':
        $bc = new NavBreadcrumb('About', '/about');
        switch ($this->method){
          case 'browser':
            if (isset($scope['session'])){
              /** @var $session Session */
              $session = $scope['session'];
              $bc = (new NavBreadcrumb('Users', '/users'))->setEnabled(Permission::sufficient('staff'))->setChild(
                (new NavBreadcrumb($session->user->name, $session->user->toURL(false)))->setChild('Session #'.$session->id)
              );
            }
            else $bc->setChild($scope['title']);
          break;
          case 'privacy':
            $bc->setChild($scope['title']);
          break;
          default:
            $bc->setActive();
        }

        return $bc;
      case 'admin':
        $bc = new NavBreadcrumb('Admin Area', '/admin');
        switch ($this->method){
          case 'log':
          case 'useful-links':
          case 'wsdiag':
          case 'pcg-appearances':
          case 'notices':
            $bc->setChild($scope['heading']);
          break;
          case 'index':
          default:
            $bc->setActive();
        }

        return $bc;
      case 'colorguide':
        $guide = $scope['guide'] ?? null;
        $ret = new NavBreadcrumb('Color Guides', '/cg');
        if ($guide !== null) {
          $bc = new NavBreadcrumb(CGUtils::GUIDE_MAP[$guide], "/cg/$guide");
          $ret->setChild($bc);
        }
        else if (isset($scope['guides'])) {
          $ret->setActive();
        }
        switch ($this->method){
          case 'appearance':
            /** @var $appearance Appearance */
            $appearance = $scope['appearance'];
            if ($appearance->owner_id !== null){
              $bc = $appearance->owner->getPCGBreadcrumb();
              $ret = $bc;
            }
            $bc->end()->setChild($appearance->label);
          break;
          case 'blending':
            $ret->setLink('/cg');
            $ret->setChild(new NavBreadcrumb('Color Blending Calculator', '/cg/blending', true));
          break;
          case 'blending-reverse':
            $ret->setLink('/cg');
            $ret->setChild(new NavBreadcrumb('Color Blending Reverser', '/cg/blending-reverse', true));
          break;
          case 'change-list':
            $bc->setChild(new NavBreadcrumb('List of Major Changes', "/cg/$guide/changes", true));
          break;
          case 'full-list':
            $bc->setChild('Full List');
          break;
          case 'picker':
            $ret->setLink('/cg');
            $ret->setChild('Color Picker');
          break;
          case 'sprite':
            /** @var $appearance Appearance */
            $appearance = $scope['appearance'];
            if ($appearance->owner_id !== null)
              $bc = $appearance->owner->getPCGBreadcrumb();
            $bc->end()->setChild(
              (new NavBreadcrumb($appearance->label, $appearance->toURL()))->setChild('Sprite Colors')
            );
          break;
          case 'tag-list':
            $ret->setLink('/cg');
            $ret->setChild(new NavBreadcrumb('Tags', '/cg/tags', true));
          break;
          case 'index':
            /* $bc will be undefined in this case */
          break;
          case 'guide':
          default:
            if (isset($bc))
              $bc->setActive();
        }

        return $ret;
      case 'components':
        return new NavBreadcrumb('Components', null, true);
      case 'show':
        $showbc = new NavBreadcrumb('Show', '/show');
        switch ($this->method){
          case 'index':
            return $showbc->setActive();
          case 'view':
            if (!isset($scope['current_episode']))
              return new NavBreadcrumb('Home', null, true);
            /** @var $ep Show */
            $ep = $scope['current_episode'];
            $cat = new NavBreadcrumb($ep->is_episode ? ShowHelper::GENERATIONS[$ep->generation].' Episodes' : 'Movies, Shorts & Specials');
            $cat->setChild(new NavBreadcrumb($scope['heading'], $ep->toURL(), true));
            $showbc->setChild($cat);

            return $showbc->setActive(false);
        }
      break;
      case 'error':
        $bc = new NavBreadcrumb('Error');
        switch ($this->method){
          case 'auth':
            $bc->setChild('Auth');
          break;
          case 'not-found':
            $bc->setChild('Not Found');
          break;
          case 'no-perm':
            $bc->setChild('Unauthorized');
          break;
          case 'bad-request':
            $bc->setChild('Bad Request');
          break;
          default:
            $bc->setActive();
        }

        return $bc;
      case 'event':
        switch ($this->method){
          case 'list':
            return new NavBreadcrumb('Events', null, true);
          case 'view':
            return (new NavBreadcrumb('Events', '/events'))->setChild($scope['heading']);
        }
      break;
      case 'user':
        $is_staff = Permission::sufficient('staff');
        $bc = (new NavBreadcrumb('Users', '/users'))->setEnabled($is_staff);
        if ($this->method !== 'list'){
          /** @var $User User */
          $user = $scope['user'] ?? null;
          if ($user instanceof User){
            switch ($this->method){
              case 'colorguide':
                return $user->getPCGBreadcrumb(true);
              case 'pcg-slots':
                $bc = $user->getPCGBreadcrumb();
                $bc->end()->setChild(
                  new NavBreadcrumb('Slot History', null, true)
                );

                return $bc;
            }

            $subbc = new NavBreadcrumb($user->name, $user->toURL(false));
          }
          else $subbc = new NavBreadcrumb('Profile', null);
          switch ($this->method){
            case 'contrib':
              $subbc->setChild(
                (new NavBreadcrumb('Contributions'))->setChild($scope['contrib_name'])
              );
            break;
            case 'profile':
              $subbc->setActive();
            break;
            case 'account':
              $subbc->setChild(
                new NavBreadcrumb('Account', null, true)
              );
            break;
            case 'verify':
              $subbc = new NavBreadcrumb($scope['heading'], null, true);
            break;
          }
          $bc->setChild($subbc);
        }
        else {
          if (!$is_staff) return (new NavBreadcrumb('Club Members', '/users'))->setActive();
          $bc->setActive();
        }

        return $bc;
    }
  }
}
