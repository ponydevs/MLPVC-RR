<?php

use Phinx\Migration\AbstractMigration;

class AddSynopsisTable extends AbstractMigration {
  public function change() {
    $this->table('synopses', ['id' => false, 'primary_key' => ['season', 'episode', 'part']])
      ->addColumn('season', 'integer')
      ->addColumn('episode', 'integer')
      ->addColumn('part', 'integer')
      ->addColumn('tmdb_id', 'integer')
      ->addColumn('body', 'text')
      ->addColumn('image', 'string', ['length' => 255, 'null' => true])
      ->addTimestampsWithTimezone()
      ->create();
  }
}
