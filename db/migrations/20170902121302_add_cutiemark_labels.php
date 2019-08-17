<?php

use Phinx\Migration\AbstractMigration;

class AddCutiemarkLabels extends AbstractMigration {
  public function change() {
    $this->table('cutiemarks')
      ->addColumn('label', 'string', ['length' => 24, 'null' => true])
      ->update();
  }
}
