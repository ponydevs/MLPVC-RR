<?php

namespace App\Controllers;

class ManifestController {
	public function json(){
		$file = file_get_contents(INCPATH.'manifest.json');
		$file = str_replace('{{ABSPATH}}', ABSPATH, $file);
		header('Content-Type: application/json');
		echo $file;
	}
}
