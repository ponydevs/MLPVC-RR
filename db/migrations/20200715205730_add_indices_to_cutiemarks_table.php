<?php

use Phinx\Migration\AbstractMigration;

class AddIndicesToCutiemarksTable extends AbstractMigration {
  public function change() {
    $this->table('cutiemarks')->addIndex('appearance_id')->addIndex('contributor_id')->update();
  }
}
