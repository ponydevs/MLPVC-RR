<?php

use Phinx\Migration\AbstractMigration;

class AddForeignKeyToMajorChangesAppearanceId extends AbstractMigration {
  public function change() {
    $this->table('major_changes')->addForeignKey('appearance_id', 'appearances', 'id', ['delete' => 'cascade', 'update' => 'cascade'])->update();
  }
}
