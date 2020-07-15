<?php

use Phinx\Migration\AbstractMigration;

class RenameEventEntryVotesCastAtColumn extends AbstractMigration {
  public function change() {
    $this->table('event_entry_votes')
      ->renameColumn('cast_at', 'created_at')
      ->addColumn('updated_at', 'timestamp', ['timezone' => true, 'null' => true])
      ->update();
  }
}
