<?php /** @noinspection SqlResolve */

use Phinx\Migration\AbstractMigration;

class SimplifyDatabaseStructure extends AbstractMigration {
  const ARGS = [
    ['users', 'signup_date'],
    ['appearances', 'added'],
    ['log', 'timestamp'],
    ['events', 'added_at'],
    ['event_entries', 'submitted_at', 'last_edited'],
    ['notifications', 'sent_at'],
    ['pcg_slot_history', 'created'],
    ['show', 'posted'],
    ['show_videos', 'modified', null, true],
    ['tag_changes', 'when'],
    ['tags'],
  ];

  public function up():void {
    foreach (self::ARGS as $args)
      $this->renameTimestamps(true, ...$args);

    $this->table('global_settings')
      ->rename('settings')
      ->renameColumn('value', 'val')
      ->update();
    if ($this->hasTable('pcg_slot_gifts'))
      $this->query('DROP TABLE "pcg_slot_gifts" CASCADE');

    $this->execute('ALTER TABLE notices DROP CONSTRAINT notices_pkey');
    $this->execute('ALTER TABLE notices RENAME COLUMN id TO old_id');
    $this->execute('ALTER TABLE notices ADD id serial');
    $this->execute('ALTER TABLE notices ADD CONSTRAINT notices_pkey PRIMARY KEY (id)');

    $this->execute('ALTER TABLE show ALTER COLUMN twoparter DROP DEFAULT');
    $this->execute('ALTER TABLE show ALTER COLUMN twoparter TYPE INTEGER USING (CASE WHEN twoparter IS NULL THEN null WHEN twoparter THEN 2 ELSE 1 END), ALTER COLUMN twoparter SET DEFAULT 1');
    $this->execute('ALTER TABLE show RENAME COLUMN twoparter TO parts');

    $this->table('users')->rename('deviantart_users')->update();
    $this->table('log')
      ->rename('logs')
      ->renameColumn('entryid', 'id')
      ->update();
    $this->execute("ALTER SEQUENCE log_entryid_seq RENAME TO logs_id_seq");
  }

  public function down():void {
    $this->table('deviantart_users')->rename('users')->update();
    $this->table('logs')
      ->rename('log')
      ->renameColumn('id', 'entryid')
      ->update();
    $this->execute("ALTER SEQUENCE logs_id_seq RENAME TO log_entryid_seq");

    $this->table('settings')
      ->rename('global_settings')
      ->renameColumn('val', 'value')
      ->update();

    foreach (self::ARGS as $args)
      $this->renameTimestamps(false, ...$args);

    $this->execute('ALTER TABLE notices DROP CONSTRAINT notices_pkey, DROP COLUMN id');
    $this->execute('ALTER TABLE notices RENAME COLUMN old_id TO id');
    $this->execute('ALTER TABLE notices ADD CONSTRAINT notices_pkey PRIMARY KEY (id)');

    $this->execute('ALTER TABLE show ALTER COLUMN parts DROP DEFAULT');
    $this->execute('ALTER TABLE show ALTER COLUMN parts TYPE BOOLEAN USING (CASE WHEN parts IS NULL THEN null WHEN parts = 1 THEN false ELSE true END), ALTER COLUMN parts SET DEFAULT false');
    $this->execute('ALTER TABLE show RENAME COLUMN parts TO twoparter');
  }

  // HELPER METHODS

  private const CREATED_AT_ARGS = ['created_at', 'timestamp', ['timezone' => true, 'null' => true, 'default' => 'CURRENT_TIMESTAMP']];

  private function renameTimestamps(bool $up, string $table_name, ?string $created_name = null, ?string $updated_name = null, bool $was_nullable = false):void {
    if ($up){
      $table = $this->table($table_name);
      if ($created_name === null)
        $table->addColumn(...self::CREATED_AT_ARGS);
      else  {
        $table->renameColumn($created_name, 'created_at');
        if (!$was_nullable)
          $table->changeColumn(...self::CREATED_AT_ARGS);
      }
      if ($updated_name === null)
        $table->addColumn('updated_at', 'timestamp', ['timezone' => true, 'null' => true, 'default' => null]);
      else if ($updated_name !== 'updated_at'){
        $table->renameColumn($updated_name, 'updated_at');
        if (!$was_nullable)
          $table->changeColumn('updated_at', 'timestamp', ['timezone' => true, 'null' => true, 'default' => null]);
      }
      $table->update();
    }
    else {
      $table = $this->table($table_name);
      if ($created_name === null)
        $table->removeColumn('created_at');
      else {
        $table->renameColumn('created_at', $created_name);
        if (!$was_nullable)
          $table->changeColumn($created_name, 'timestamp', ['timezone' => true, 'null' => false, 'default' => 'CURRENT_TIMESTAMP']);
      }
      if ($updated_name === null)
        $table->removeColumn('updated_at');
      else if ($updated_name !== 'updated_at'){
        $table->renameColumn('updated_at', $updated_name);
        if (!$was_nullable)
          $table->changeColumn($updated_name, 'timestamp', ['timezone' => true, 'null' => false, 'default' => 'CURRENT_TIMESTAMP']);
      }
      $table->update();
    }
  }
}
