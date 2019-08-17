<?php

use Phinx\Migration\AbstractMigration;

class RenameSessionLastvisitColumn extends AbstractMigration {
  public function change() {
    $this->table('sessions')
      ->renameColumn('lastvisit', 'last_visit')
      ->update();
  }
}
