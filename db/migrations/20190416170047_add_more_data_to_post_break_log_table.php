<?php

use Phinx\Migration\AbstractMigration;

class AddMoreDataToPostBreakLogTable extends AbstractMigration {
  public function change() {
    $this->table('log__post_break')
      ->addColumn('response_code', 'integer', ['null' => true])
      ->addColumn('failing_url', 'string', ['length' => 1024, 'null' => true])
      ->update();
  }
}
