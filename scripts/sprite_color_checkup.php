<?php

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../config/init/minimal.php';
require __DIR__.'/../config/init/db_class.php';

use App\Models\Appearance;

ini_set('max_execution_time', '0');

$sprite_dir = new DirectoryIterator(PUBLIC_SPRITE_PATH);
foreach ($sprite_dir as $item){
  if ($item->isDot())
    continue;

  $id = (int)preg_replace('/\..+$/', '', $item->getFilename());
  $appearance = Appearance::find($id);
  if (empty($appearance))
    continue;

  $appearance->checkSpriteColors();
}
