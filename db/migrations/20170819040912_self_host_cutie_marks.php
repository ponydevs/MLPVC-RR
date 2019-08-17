<?php

use Phinx\Migration\AbstractMigration;

class SelfHostCutieMarks extends AbstractMigration {
  public function up() {
    $this->table('cutiemarks')
      ->removeColumn('preview')
      ->removeColumn('preview_src')
      ->renameColumn('favme_rotation', 'rotation')
      ->addColumn('contributor_id', 'uuid', ['null' => true])
      ->changeColumn('favme', 'string', ['length' => 7, 'null' => true])
      ->removeIndex(['appearance_id', 'facing'])
      ->update();
  }

  public function down() {
    $this->table('cutiemarks')
      ->addColumn('preview', 'string', ['length' => 255, 'null' => true])
      ->addColumn('preview_src', 'string', ['length' => 255, 'null' => true])
      ->renameColumn('rotation', 'favme_rotation')
      ->removeColumn('contributor_id')
      ->changeColumn('favme', 'string', ['length' => 7])
      ->addIndex(['appearance_id', 'facing'], ['unique' => true])
      ->update();
  }
}
