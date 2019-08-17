<?php

use Phinx\Migration\AbstractMigration;

class IncreaseCachedDeviationsTableStringLengths extends AbstractMigration {
  public function change() {
    $this->table('cached_deviations')
      ->changeColumn('preview', 'string', ['length' => 1024, 'null' => true])
      ->changeColumn('fullsize', 'string', ['length' => 1024, 'null' => true])
      ->update();

    $this->table('posts')
      ->changeColumn('preview', 'string', ['length' => 1024, 'null' => true])
      ->changeColumn('fullsize', 'string', ['length' => 1024, 'null' => true])
      ->update();
  }
}
