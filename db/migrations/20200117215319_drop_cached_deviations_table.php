<?php

use Phinx\Migration\AbstractMigration;

class DropCachedDeviationsTable extends AbstractMigration {
  public function up() {
    $this->table('cached_deviations')->drop()->save();
  }

  public function down() {
    $this->table('cached_deviations', ['id' => false, 'primary_key' => ['provider', 'id']])
      ->addColumn('provider', 'char', ['length' => 6])
      ->addColumn('id', 'string', ['length' => 20])
      ->addColumn('title', 'string', ['length' => 255])
      ->addColumn('author', 'string', ['length' => 20, 'null' => true])
      ->addColumn('preview', 'string', ['length' => 1024, 'null' => true])
      ->addColumn('fullsize', 'string', ['length' => 1024, 'null' => true])
      ->addColumn('updated_on', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP', 'null' => true])
      ->addColumn('type', 'string', ['length' => 12, 'null' => true])
      ->addIndex('author')
      ->create();
  }
}
