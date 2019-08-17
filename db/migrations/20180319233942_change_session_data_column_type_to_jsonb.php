<?php

use Phinx\Migration\AbstractMigration;

class ChangeSessionDataColumnTypeToJsonb extends AbstractMigration {
  public function up() {
    $this->table('sessions')
      ->removeColumn('data')
      ->addColumn('data', 'jsonb', ['null' => true])
      ->update();
  }

  public function down() {
    $this->table('sessions')
      ->removeColumn('data')
      ->addColumn('data', 'text', ['null' => true])
      ->update();
  }
}
