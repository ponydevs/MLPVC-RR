<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Util\Literal;

class DropPreviousUsernamesNewColumn extends AbstractMigration {
  public function up() {
    $this->table('previous_usernames')
      ->removeColumn('_new')
      ->update();
  }

  public function down() {
    $this->table('previous_usernames')
      ->addColumn('_new', Literal::from('citext'), ['null' => true])
      ->update();
  }
}
