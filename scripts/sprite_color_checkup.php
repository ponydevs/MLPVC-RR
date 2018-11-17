<?php

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../config/init/minimal.php';
require __DIR__.'/../config/init/db_class.php';

use App\Models\Appearance;
use App\RegExp;

ini_set('max_execution_time', '0');

$SpriteDir = new \DirectoryIterator(SPRITE_PATH);
foreach ($SpriteDir as $item){
	if ($item->isDot())
		continue;

	$id = (int)preg_replace(new RegExp('\..+$'),'',$item->getFilename());
	$Appearance = Appearance::find($id);
	if (empty($Appearance))
		continue;

	$Appearance->checkSpriteColors();
}
