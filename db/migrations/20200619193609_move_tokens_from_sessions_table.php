<?php /** @noinspection SqlResolve */

use Phinx\Migration\AbstractMigration;

class MoveTokensFromSessionsTable extends AbstractMigration {
  public function up() {
    $this->table('deviantart_users')
      ->addColumn('access', 'string', ['length' => 50, 'null' => true])
      ->addColumn('refresh', 'string', ['length' => 40, 'null' => true])
      ->addColumn('access_expires', 'timestamp', ['timezone' => true, 'null' => true])
      ->addColumn('scope', 'string', ['length' => 50, 'null' => true])
      ->save();

    $this->query(<<<'SQL'
      UPDATE deviantart_users du
      SET access = s.access, refresh = s.refresh, access_expires = s.expires
      FROM (SELECT user_id, access, refresh, expires FROM sessions) s
      WHERE s.user_id = du.user_id
    SQL);

    $this->table('sessions')
      ->removeColumn('access')
      ->removeColumn('refresh')
      ->removeColumn('expires')
      ->removeColumn('scope')
      ->addIndex('token', ['unique' => true])
      ->save();
  }

  public function down() {
    $this->table('sessions')
      ->removeIndexByName('sessions_token')
      ->addColumn('access', 'string', ['length' => 50, 'null' => true])
      ->addColumn('refresh', 'string', ['length' => 40, 'null' => true])
      ->addColumn('expires', 'timestamp', ['timezone' => true, 'null' => true])
      ->addColumn('scope', 'string', ['length' => 50, 'null' => true])
      ->save();

    $this->query(<<<'SQL'
      UPDATE sessions s
      SET access = du.access, refresh = du.refresh, expires = du.access_expires
      FROM (SELECT user_id, access, refresh, expires FROM deviantart_users) du
      WHERE du.user_id = s.user_id
    SQL);

    $this->table('deviantart_users')
      ->removeColumn('access')
      ->removeColumn('refresh')
      ->removeColumn('access_expires')
      ->removeColumn('scope')
      ->save();
  }
}
