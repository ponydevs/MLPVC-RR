<?php

use Phinx\Migration\AbstractMigration;

class AddIndexToEventEntriesTable extends AbstractMigration
{
    public function change()
    {
      $this->table('event_entries')->addIndex('event_id')->update();
    }
}
