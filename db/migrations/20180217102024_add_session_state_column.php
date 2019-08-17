<?php

use Phinx\Migration\AbstractMigration;

class AddSessionStateColumn extends AbstractMigration {
  public function change() {
    $this->table('sessions')
      ->addColumn('updating', 'boolean', ['default' => false])
      ->update();
  }
}
