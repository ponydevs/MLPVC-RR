<?php

use Phinx\Migration\AbstractMigration;

class AdjustShowUniqueKey extends AbstractMigration {
  public function up() {
    $this->table('show')
      ->removeIndex(['season', 'episode'])
      ->addIndex(['season', 'episode', 'generation'], ['unique' => true])
      ->update();
  }

  public function down() {
    $this->table('show')
      ->removeIndex(['season', 'episode', 'generation'])
      ->addIndex(['season', 'episode'], ['unique' => true])
      ->update();
  }
}
