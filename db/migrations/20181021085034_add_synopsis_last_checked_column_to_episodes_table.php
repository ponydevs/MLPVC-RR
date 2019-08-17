<?php

use Phinx\Migration\AbstractMigration;

class AddSynopsisLastCheckedColumnToEpisodesTable extends AbstractMigration {
  public function change() {
    $this->table('episodes')
      ->addColumn('synopsis_last_checked', 'timestamp', ['timezone' => true, 'null' => true])
      ->update();
  }
}
