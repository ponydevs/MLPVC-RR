<?php

use Phinx\Migration\AbstractMigration;

class AdjustSettingsTable extends AbstractMigration {
  public function up() {
    $this->query('ALTER TABLE settings ADD COLUMN id serial');
    $this->table('settings')
      ->addColumn('group', 'string', ['length' => 255, 'default' => 'default'])
      ->addTimestampsWithTimezone()
      ->changePrimaryKey('id')
      ->renameColumn('key', 'name')
      ->addIndex(['name', 'group'], ['unique' => true])
      ->update();
  }
  public function down() {
    $this->table('settings')
      ->removeColumn('group')
      ->removeIndexByName('settings_name')
      ->renameColumn('name', 'key')
      ->changePrimaryKey('key')
      ->removeColumn('id')
      ->removeColumn('created_at')
      ->removeColumn('updated_at')
      ->update();
  }
}
