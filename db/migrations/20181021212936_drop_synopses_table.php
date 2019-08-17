<?php

use Phinx\Migration\AbstractMigration;

class DropSynopsesTable extends AbstractMigration {
  public function change() {
    $this->table('synopses')->drop()->update();
  }
}
