<?php

use Phinx\Migration\AbstractMigration;

class AppearanceLastClearedNull extends AbstractMigration {
  public function up() {
    $this->table('appearances')
      ->changeColumn('last_cleared', 'timestamp', ['timezone' => true, 'null' => true, 'default' => null])
      ->update();
  }

  public function down() {
    $this->table('appearances')
      ->changeColumn('last_cleared', 'timestamp', ['timezone' => true, 'null' => true, 'default' => 'CURRENT_TIMESTAMP'])
      ->update();
  }
}
