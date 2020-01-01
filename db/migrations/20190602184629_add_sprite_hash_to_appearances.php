<?php

use App\CoreUtils;
use Phinx\Migration\AbstractMigration;

class AddSpriteHashToAppearances extends AbstractMigration {
  public function up() {
    $this->table('appearances')
      ->addColumn('sprite_hash', 'string', ['length' => 32, 'null' => true])
      ->update();

    $iterator = function(DirectoryIterator $file_info) {
      $filename = $file_info->getFilename();
      $appearance_id = explode('.', $filename)[0];
      $hash = CoreUtils::generateFileHash($file_info->getPathname());
      $this->query("UPDATE appearances SET sprite_hash = '$hash' WHERE id = $appearance_id");
    };

    foreach ([PUBLIC_SPRITE_PATH, PRIVATE_SPRITE_PATH] as $path) {
      if (!is_dir($path))
        continue;

      foreach (new DirectoryIterator($path) as $file_info){
        if ($file_info->isDot()) continue;

        $iterator($file_info);
      }
    }
  }

  public function down() {
    $this->table('appearances')
      ->removeColumn('sprite_hash')
      ->update();
  }
}
