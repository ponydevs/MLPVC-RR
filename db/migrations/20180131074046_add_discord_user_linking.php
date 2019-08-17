<?php

use Phinx\Migration\AbstractMigration;

class AddDiscordUserLinking extends AbstractMigration {
  public function up() {
    $this->table('discord_members')
      ->addColumn('access', 'string', ['length' => 30, 'null' => true])
      ->addColumn('refresh', 'string', ['length' => 30, 'null' => true])
      ->addColumn('scope', 'string', ['length' => 50, 'null' => true])
      ->addColumn('expires', 'timestamp', ['timezone' => true, 'null' => true])
      ->addColumn('last_synced', 'timestamp', ['timezone' => true, 'null' => true])
      ->addIndex('user_id', ['unique' => true])
      ->update();

    $this->query('ALTER TABLE discord_members ALTER COLUMN id TYPE bigint USING (id::bigint)');
    $this->query("ALTER TABLE discord_members ALTER COLUMN discriminator TYPE CHARACTER(4) USING lpad(discriminator::varchar, 4, '0')");
  }

  public function down() {
    $this->table('discord_members')
      ->changeColumn('id', 'string')
      ->removeColumn('access')
      ->removeColumn('refresh')
      ->removeColumn('scope')
      ->removeColumn('expires')
      ->removeIndex(['user_id'])
      ->update();
    $this->query('ALTER TABLE discord_members ALTER COLUMN discriminator TYPE integer USING (discriminator::integer)');
  }
}
