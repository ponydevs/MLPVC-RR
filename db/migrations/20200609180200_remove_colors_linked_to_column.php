<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;

class RemoveColorsLinkedToColumn extends AbstractMigration {
  public function up() {
    $this->query("UPDATE colors c1 SET hex = c2.hex FROM (SELECT id, hex from colors) c2 WHERE c2.id = c1.linked_to");
    $this->table('colors')->removeColumn('linked_to')->update();
  }

  public function down() {
    throw new IrreversibleMigrationException();
  }
}
