<?php

use Phinx\Migration\AbstractMigration;

class CreateEmailVerificationTables extends AbstractMigration {
  public function change() {
    $this->table('email_verifications')
      ->addColumn('user_id', 'integer')
      ->addColumn('email', 'string', ['length' => 255])
      ->addColumn('hash', 'string', ['length' => 128])
      ->addTimestampsWithTimezone()
      ->addIndex('hash', ['unique' => true])
      ->addIndex('user_id')
      ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
      ->create();

    $this->table('blocked_emails')
      ->addColumn('email', 'string', ['length' => 255])
      ->addTimestampsWithTimezone()
      ->addIndex('email', ['unique' => true])
      ->create();
  }
}
