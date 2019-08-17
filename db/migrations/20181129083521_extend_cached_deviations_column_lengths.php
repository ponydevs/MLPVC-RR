<?php

use Phinx\Migration\AbstractMigration;

class ExtendCachedDeviationsColumnLengths extends AbstractMigration {
  public function change() {
    $this->table('cached_deviations')
      ->changeColumn('preview', 'string', ['length' => 300, 'null' => true])
      ->changeColumn('fullsize', 'string', ['length' => 300, 'null' => true])
      ->update();
  }
}
