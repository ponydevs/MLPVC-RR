<?php

namespace App;

use App\Models\User;

class View {
	/** @var string */
	public $name, $class, $method;
	public function __construct(string $name){
		[$this->class, $this->method] = self::processName($name);
		$this->name = "$this->class/$this->method";
	}

	public static function processName(string $name){
		$name = strtolower(preg_replace(new RegExp('List$'),'-list',$name));
		if (!preg_match(new RegExp('^(?:\\\\?app\\\\controllers\\\\)?([a-z]+)controller::([a-z-]+)$'), $name, $match))
			throw new \RuntimeException('Could not resolve view based on value '.$name);
		[$class, $method] = \array_slice($match, 1, 2);
		return [$class, $method];
	}

	public function __toString():string {
		return $this->name;
	}

	public function getBreadcrumb(array $scope = []):?NavBreadcrumb {
		switch ($this->class){
			case 'about':
				$bc = new NavBreadcrumb('About','/about');
				switch ($this->method){
					case 'browser':
						if (isset($scope['Session'])){
							/** @var $session \App\Models\Session */
							$session = $scope['Session'];
							$bc = (new NavBreadcrumb('Users', '/users'))->setEnabled(Permission::sufficient('staff'))->setChild(
								(new NavBreadcrumb($session->user->name, $session->user->toURL()))->setChild('Session #'.$session->id)
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
					case 'usefullinks':
					case 'wsdiag':
					case 'pcgappearances':
					case 'notices':
						$bc->setChild($scope['heading']);
					break;
					case 'index':
					default:
						$bc->setActive();
				}
				return $bc;
			case 'colorguide':
				$eqg = $scope['eqg'] ?? false;
				$ret = new NavBreadcrumb('Color Guide');
				$bc = new NavBreadcrumb($eqg ? 'EQG' : 'Pony', '/cg/'.($eqg?'eqg':'pony'));
				$ret->setChild($bc);
				switch ($this->method){
					case 'appearance':
						/** @var $appearance \App\Models\Appearance */
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
					case 'blendingreverse':
						$ret->setLink('/cg');
						$ret->setChild(new NavBreadcrumb('Color Blending Reverser', '/cg/blending-reverse', true));
					break;
					case 'change-list':
						$ret->setLink('/cg');
						$ret->setChild(new NavBreadcrumb('List of Major Changes', '/cg/changes', true));
					break;
					case 'full-list':
						$bc->setChild('Full List');
					break;
					case 'picker':
						$ret->setLink('/cg');
						$ret->setChild('Color Picker');
					break;
					case 'sprite':
						/** @var $appearance \App\Models\Appearance */
						$appearance = $scope['appearance'];
						if ($appearance->owner_id !== null)
							$bc = $appearance->owner->getPCGBreadcrumb();
						$bc->end()->setChild(
							(new NavBreadcrumb($appearance->label, $appearance->toURL()))->setChild('Sprite Colors')
						);
					break;
					case 'tag-list':
						$ret->setLink('/cg');
						$ret->setChild(new NavBreadcrumb('List of Tags', '/cg/tags', true));
					break;
					case 'guide':
					default:
						$bc->setActive();
				}
				return $ret;
			case 'components':
				return new NavBreadcrumb('Components',null,true);
			case 'show':
			case 'episode':
				$showbc = new NavBreadcrumb('Show','/show');
				switch ($this->method){
					case 'index':
						return $showbc->setActive();
					case 'view':
						if (!isset($scope['current_episode']))
							return new NavBreadcrumb('Home',null,true);
						/** @var $ep \App\Models\Episode */
						$ep = $scope['current_episode'];
						$cat = new NavBreadcrumb($ep->is_movie ? 'Movies & Shorts' : 'Episodes');
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
					case 'notfound':
						$bc->setChild('Not Found');
					break;
					case 'noperm':
						$bc->setChild('Unauthorized');
					break;
					case 'badreq':
						$bc->setChild('Bad Request');
					break;
					default:
						$bc->setActive();
				}
				return $bc;
			case 'event':
				switch ($this->method){
					case 'list':
						return new NavBreadcrumb('Events',null,true);
					case 'view':
						return (new NavBreadcrumb('Events', '/events'))->setChild($scope['heading']);
				}
			break;
			case 'user':
				$bc = (new NavBreadcrumb('Users', '/users'))->setEnabled(Permission::sufficient('staff'));
				if ($this->method !== 'list'){
					/** @var $User \App\Models\User */
					$user = $scope['user'];
					if ($user instanceof User){
						switch ($this->method){
							case 'colorguide':
								return $user->getPCGBreadcrumb(true);
							case 'pcgslots':
								$bc = $user->getPCGBreadcrumb();
								$bc->end()->setChild(
									 new NavBreadcrumb('Slot History', null, true)
								);
								return $bc;
						}

						$subbc = new NavBreadcrumb($user->name, $user->toURL());
					}
					else $subbc = new NavBreadcrumb('Profile',null);
					switch ($this->method){
						case 'contrib':
							$subbc->setChild(
								(new NavBreadcrumb('Contributions'))->setChild($scope['contrib_name'])
							);
						break;
						case 'profile':
							$subbc->setActive();
						break;
					}
					$bc->setChild($subbc);
				}
				else $bc->setActive();
				return $bc;
		}
	}
}
