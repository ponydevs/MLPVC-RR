<?php /** @noinspection SqlResolve */

use Phinx\Migration\AbstractMigration;

class RemoveRefidFromLogs extends AbstractMigration {
  public function up() {
    $this->table('logs')
      ->removeColumn('refid')
      ->renameColumn('reftype', 'entry_type')
      ->save();
  }

  public function down() {
    $this->table('logs')
      ->addColumn('refid', 'integer', ['null' => true])
      ->renameColumn('entry_type', 'reftype')
      ->save();

    $this->query('UPDATE logs SET refid = (data->>refid)::integer WHERE data IS NOT NULL');
  }
}
