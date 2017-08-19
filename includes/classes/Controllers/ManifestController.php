<?php

namespace App\Controllers;

use App\File;

class ManifestController {
	public function json(){
		$file = File::get(INCPATH.'manifest.json');
		$file = str_replace('{{ABSPATH}}', ABSPATH, $file);
		header('Content-Type: application/json');
		echo $file;
	}
}
