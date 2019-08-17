<?php

use Phinx\Migration\AbstractMigration;

class IncreasePostImageLinkLengthLimit extends AbstractMigration {
  public function change() {
    $this->table('posts')
      ->changeColumn('preview', 'string', ['length' => 300, 'null' => true])
      ->changeColumn('fullsize', 'string', ['length' => 300, 'null' => true])
      ->update();
  }
}
