<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;

class DropOldIdFromNotices extends AbstractMigration {
  public function up() {
    $this->table('notices')->removeColumn('old_id')->save();
  }

  public function down():void {
    throw new IrreversibleMigrationException("There is no going back");
  }
}
