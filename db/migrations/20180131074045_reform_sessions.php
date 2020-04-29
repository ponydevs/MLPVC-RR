<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;

class ReformSessions extends AbstractMigration {
  public function up() {
    $this->query(
      'ALTER TABLE sessions
			ALTER "user_id" DROP NOT NULL,
			ALTER "token" DROP NOT NULL,
			ALTER "access" DROP NOT NULL,
			ALTER "refresh" DROP NOT NULL,
			ALTER "scope" DROP NOT NULL,
			ALTER "scope" DROP DEFAULT');
    $this->query('ALTER TABLE sessions ALTER COLUMN id DROP DEFAULT, ALTER COLUMN id TYPE uuid USING (uuid_generate_v4()), ALTER COLUMN id SET DEFAULT uuid_generate_v4()');
    $this->query('DROP SEQUENCE sessions_id_seq');
    $this->table('sessions')
      ->addColumn('data', 'text', ['null' => true])
      ->update();
  }

  public function down():void {
    throw new IrreversibleMigrationException("There is no going back");
  }
}
