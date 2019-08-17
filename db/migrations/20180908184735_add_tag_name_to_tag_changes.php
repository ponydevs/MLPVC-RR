<?php

use Phinx\Migration\AbstractMigration;

class AddTagNameToTagChanges extends AbstractMigration {
  public function up() {
    $this->table('tag_changes')
      ->addColumn('tag_name', 'string', ['length' => 30, 'null' => true])
      ->dropForeignKey('tag_id')
      ->dropForeignKey('user_id')
      ->update();

    $tag_ids = $this->query('SELECT DISTINCT tag_id FROM tag_changes');
    foreach ($tag_ids as $row){
      $tid = $row['tag_id'];
      $this->query("UPDATE tag_changes SET tag_name = (SELECT name FROM tags WHERE id = $tid) WHERE tag_id = $tid");
    }
  }

  public function down() {
    $this->table('tag_changes')
      ->removeColumn('tag_name')
      ->addForeignKey('tag_id', 'tags', 'id', ['delete' => 'cascade', 'update' => 'cascade'])
      ->addForeignKey('user_id', 'users', 'id', ['delete' => 'cascade', 'update' => 'cascade'])
      ->update();
  }
}
