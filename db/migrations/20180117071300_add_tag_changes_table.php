<?php

use Phinx\Migration\AbstractMigration;

class AddTagChangesTable extends AbstractMigration {
  public function change() {
    $this->table('tag_changes')
      ->addColumn('tag_id', 'integer')
      ->addColumn('appearance_id', 'integer')
      ->addColumn('user_id', 'uuid')
      ->addColumn('added', 'boolean')
      ->addIndex('tag_id')
      ->addIndex('appearance_id')
      ->addIndex('user_id')
      ->addColumn('when', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
      ->addForeignKey('tag_id', 'tags', 'id', ['delete' => 'cascade', 'update' => 'cascade'])
      ->addForeignKey('appearance_id', 'appearances', 'id', ['delete' => 'cascade', 'update' => 'cascade'])
      ->addForeignKey('user_id', 'users', 'id', ['delete' => 'cascade', 'update' => 'cascade'])
      ->create();
  }
}
