<?php

use Phinx\Migration\AbstractMigration;

class AddSpriteHashToAppearances extends AbstractMigration {
	public function up() {
		$this->table('appearances')
			->addColumn('sprite_hash', 'string', ['length' => 32, 'null' => true])
			->update();

		foreach (new DirectoryIterator(SPRITE_PATH) as $file_info){
			if ($file_info->isDot()) continue;

			$filename = $file_info->getFilename();
			$appearance_id = explode('.', $filename)[0];
			$hash = md5_file($file_info->getPathname());
			if ($hash !== false) {
				$this->query("UPDATE appearances SET sprite_hash = '$hash' WHERE id = $appearance_id");
			}
		}
	}

	public function down() {
		$this->table('appearances')
			->removeColumn('sprite_hash')
			->update();
	}
}
