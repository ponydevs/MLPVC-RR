<?php

use Phinx\Migration\AbstractMigration;

class AddUserIdForeignKeyToTagChanges extends AbstractMigration {
  public function up() {
    $this->table('tag_changes')
      ->addForeignKey('user_id', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
      ->update();
  }

  public function down() {
    $this->table('tag_changes')
      ->dropForeignKey('user_id')
      ->update();
  }
}
