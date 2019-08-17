<?php

use Phinx\Migration\AbstractMigration;

class AddPcgPointGrantsTable extends AbstractMigration {
  public function change() {
    $this->table('pcg_point_grants')
      ->addColumn('receiver_id', 'uuid')
      ->addColumn('sender_id', 'uuid')
      ->addColumn('amount', 'integer')
      ->addColumn('comment', 'string', ['length' => 140, 'null' => true])
      ->addTimestamps(null, null, true)
      ->save();
  }
}
