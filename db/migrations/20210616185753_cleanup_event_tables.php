<?php

use Phinx\Migration\AbstractMigration;

class CleanupEventTables extends AbstractMigration {
  public function up() {
    $this->table('event_entries')
      ->removeColumn('score')
      ->update();

    $this->table('events')
      ->removeColumn('type')
      ->update();

    $this->table('event_entry_votes')
      ->drop()
      ->update();
  }

  public function down() {
    $this->table('event_entries')
      ->addColumn('score', 'integer', ['null' => true])
      ->update();

    $this->table('events')
      ->addColumn('type', 'string', ['limit' => 10, "default" => "collab"])
      ->update();

    $this->table('event_entry_votes', ['id' => false, 'primary_key' => ['entry_id', 'user_id']])
      ->addColumn('entry_id', 'integer')
      ->addColumn('user_id', 'integer')
      ->addColumn('value', 'smallinteger')
      ->addColumn('cast_at', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
      ->addForeignKey('entry_id', 'event_entries', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
      ->addForeignKey('user_id', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->create();
  }
}
