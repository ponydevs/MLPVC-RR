<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Util\Literal;

class CreateNoticesTable extends AbstractMigration {
  public function change() {
    $this->table('notices', ['id' => false, 'primary_key' => 'id'])
      ->addColumn('id', 'uuid', ['default' => Literal::from('uuid_generate_v4()')])
      ->addColumn('message_html', 'string', ['length' => 500])
      ->addColumn('type', 'string', ['length' => 16])
      ->addColumn('posted_by', 'uuid')
      ->addTimestamps(null, null, true)
      ->addColumn('hide_after', 'timestamp', ['timezone' => true])
      ->addForeignKey('posted_by', 'users', 'id', ['update' => 'CASCADE', 'delete' => 'RESTRICT'])
      ->addIndex('posted_by')
      ->create();
  }
}
