<?php

use Phinx\Migration\AbstractMigration;

class TrackPcgSlotGifts extends AbstractMigration {
  public function change() {
    $this->table('pcg_slot_gifts')
      ->addColumn('sender_id', 'uuid')
      ->addColumn('receiver_id', 'uuid')
      ->addColumn('amount', 'integer')
      ->addColumn('claimed', 'boolean', ['default' => false])
      ->addColumn('rejected', 'boolean', ['default' => false])
      ->addColumn('refunded_by', 'uuid', ['default' => null, 'null' => true])
      ->addTimestamps(null, null, true)
      ->addIndex('sender_id')
      ->addIndex('receiver_id')
      ->addIndex('refunded_by')
      ->addForeignKey('sender_id', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->addForeignKey('receiver_id', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->addForeignKey('refunded_by', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->create();

    $this->table('log__pcg_gift_refund', ['id' => 'entryid'])
      ->addColumn('gift_id', 'integer')
      ->addIndex('gift_id')
      ->addForeignKey('gift_id', 'pcg_slot_gifts', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->create();

    $this->table('pcg_slot_history')->truncate();

    $this->execute("DELETE FROM user_prefs WHERE key = 'pcg_slots'");
  }
}
