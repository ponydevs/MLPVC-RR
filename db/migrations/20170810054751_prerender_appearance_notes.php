<?php

use Phinx\Migration\AbstractMigration;

class PrerenderAppearanceNotes extends AbstractMigration {
  public function change() {
    $this->table('appearances')
      ->renameColumn('notes', 'notes_src')
      ->addColumn('notes_rend', 'text', ['null' => true])
      ->update();
  }
}
