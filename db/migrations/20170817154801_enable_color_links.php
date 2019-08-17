<?php

use Phinx\Migration\AbstractMigration;

class EnableColorLinks extends AbstractMigration {
  public function change() {
    $this->table('colors')
      ->addColumn('linked_to', 'integer', ['null' => true])
      ->addForeignKey('linked_to', 'colors', 'id', ['update' => 'CASCADE', 'delete' => 'RESTRICT'])
      ->update();
  }
}
