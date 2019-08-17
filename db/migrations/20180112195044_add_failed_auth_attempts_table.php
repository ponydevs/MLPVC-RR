<?php

use Phinx\Migration\AbstractMigration;

class AddFailedAuthAttemptsTable extends AbstractMigration {
  public function change() {
    $this->table('log__failed_auth_attempts', ['id' => 'entryid'])
      ->addColumn('user_agent', 'string', ['null' => true])
      ->create();
  }
}
