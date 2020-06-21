<?php

namespace App;

use AltoRouter;

$router = new AltoRouter();
$router->addMatchTypes([
  'uc' => USERNAME_CHARACTERS_PATTERN.'+',
  'un' => USERNAME_PATTERN,
  'au' => '[A-Za-z_]+',
  'ad' => '[A-Za-z\-]+',
  'adi' => '[A-Za-z\d\-]+',
  'ai' => '[A-Za-z\d]+',
  'epid' => EPISODE_ID_PATTERN,
  'rr' => '(req|res)',
  'rrl' => '(request|reservation)',
  'rrsl' => '(request|reservation)s?',
  'cgimg' => '[spfc]',
  'cgext' => '(png|svg|json|gpl)',
  'guide' => '('.implode('|', array_keys(CGUtils::GUIDE_MAP)).')',
  'gen' => '('.implode('|', array_keys(ShowHelper::GENERATIONS)).')',
  'favme' => 'd[a-z\d]{6}',
  'gsd' => '([gs]et|del)',
  'sett' => '(u|settings)',
  'cg' => '(c(olou?r)?g(uide)?)',
  'user' => 'u(ser)?',
  'v' => '(v|appearance)',
  'st' => '('.implode('|', array_keys(ShowHelper::VALID_TYPES)).')',
  'uuid' => '([0-9a-fA-F]{32}|[0-9a-fA-F-]{36})',
]);

require __DIR__.'/pages.php';
require __DIR__.'/private_api.php';
require __DIR__.'/public_api_v0.php';
