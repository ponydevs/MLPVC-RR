<?php

use Phinx\Migration\AbstractMigration;

class PersonalGuideSlotsReform extends AbstractMigration {
  public function change() {
    $this->table('pcg_slot_history')
      ->addColumn('user_id', 'uuid')
      ->addColumn('change_type', 'string', ['length' => 15])
      ->addColumn('change_data', 'jsonb', ['null' => true])
      ->addColumn('change_amount', 'float')
      ->addColumn('created', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
      ->save();

    $this->table('log__staff_limits', ['id' => 'entryid'])
      ->addColumn('setting', 'string', ['length' => 50])
      ->addColumn('allow', 'boolean')
      ->addColumn('user_id', 'uuid')
      ->save();
  }
}
