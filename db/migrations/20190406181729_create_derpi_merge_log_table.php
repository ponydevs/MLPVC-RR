<?php

use Phinx\Migration\AbstractMigration;

class CreateDerpiMergeLogTable extends AbstractMigration {
  public function change() {

    $this->table('log__derpimerge', ['id' => 'entryid'])
      ->addColumn('post_id', 'integer')
      ->addColumn('original_fullsize', 'string', ['length' => 255])
      ->addColumn('original_preview', 'string', ['length' => 255])
      ->addColumn('new_fullsize', 'string', ['length' => 255])
      ->addColumn('new_preview', 'string', ['length' => 255])
      ->addIndex('post_id')
      ->addForeignKey('post_id', 'posts', 'id', ['delete' => 'NO ACTION', 'update' => 'CASCADE'])
      ->create();
  }
}
