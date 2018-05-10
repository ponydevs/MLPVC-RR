<?php

$_dir = rtrim(__DIR__, '\/').DIRECTORY_SEPARATOR;
require $_dir.'../../vendor/autoload.php';
require $_dir.'../conf.php';

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
