<?php /** @noinspection SqlResolve */

use Phinx\Db\Table;
use Phinx\Migration\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;

class AddDataToLogTable extends AbstractMigration {
  public function up():void {
    $this->table('log')
      ->addColumn('data', 'jsonb', ["null" => true])
      ->update();

        $this->renameLogTable('log__da_namechange', 'previous_usernames', function (Table $table) {
      $table
        ->renameColumn('old', 'username')
        ->renameColumn('new', '_new');
    });
    $this->renameLogTable('log__major_changes', 'major_changes', function (Table $table) {
      $this->laravelTimestamps(true, $table)
        ->addColumn('user_id', 'uuid', ['null' => true]);
    });
    $this->renameLogTable('log__failed_auth_attempts', 'failed_auth_attempts', function (Table $table) {
      $this->laravelTimestamps(true, $table)
        ->addColumn('ip', 'inet', ['null' => true]);
    });
    $this->renameLogTable('log__post_lock', 'locked_posts', function (Table $table) {
      $this->laravelTimestamps(true, $table)
        ->addColumn('user_id', 'uuid', ['null' => true])
        ->addForeignKey('user_id', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->renameColumn('old_id', 'old_post_id')
        ->renameColumn('id', 'post_id')
        ->update();
    });
    $this->renameLogTable('log__post_break', 'broken_posts', function (Table $table) {
      $this->laravelTimestamps(true, $table)
        ->renameColumn('old_id', 'old_post_id')
        ->renameColumn('id', 'post_id')
        ->update();
    });

    $this->query("UPDATE major_changes mc SET created_at = l.timestamp, user_id = l.initiator FROM (SELECT timestamp, initiator, refid, reftype from log) l WHERE l.reftype = 'major_changes' AND l.refid = mc.id");
    $this->query("UPDATE failed_auth_attempts faa SET created_at = l.timestamp, ip = l.ip FROM (SELECT timestamp, ip, refid, reftype from log) l WHERE l.reftype = 'failed_auth_attempts' AND l.refid = faa.id");
    $this->query("UPDATE locked_posts lp SET created_at = l.timestamp, user_id = l.initiator FROM (SELECT timestamp, initiator, refid, reftype from log) l WHERE l.reftype = 'post_lock' AND l.refid = lp.id");
    $this->query("UPDATE broken_posts bp SET created_at = l.timestamp FROM (SELECT timestamp, refid, reftype from log) l WHERE l.reftype = 'post_break' AND l.refid = bp.id");

    // Cleanup of main log entries for renamed tables
    $this->query("DELETE FROM log WHERE reftype IN ('da_namechange', 'major_changes', 'failed_auth_attempts', 'post_lock', 'post_break')");

    $reftypes = $this->fetchAll("SELECT DISTINCT reftype from log");
    foreach ($reftypes as $reftype_arr) {
      $reftype = $reftype_arr['reftype'];
      $this->query("UPDATE log l SET data = to_jsonb(t) FROM (SELECT * FROM log__$reftype) t WHERE reftype = '$reftype' AND t.entryid = l.refid");
    }

    $log_table_names = [
      'log__rolechange',
      'log__userfetch',
      'log__post_lock',
      'log__major_changes',
      'log__req_delete',
      'log__img_update',
      'log__res_overtake',
      'log__appearances',
      'log__res_transfer',
      'log__cg_modify',
      'log__cgs',
      'log__cg_order',
      'log__appearance_modify',
      'log__da_namechange',
      'log__video_broken',
      'log__cm_modify',
      'log__cm_delete',
      'log__post_break',
      'log__post_fix',
      'log__staff_limits',
      'log__failed_auth_attempts',
      'log__derpimerge',
      'log__pcg_gift_refund',
    ];
    foreach ($log_table_names as $log_table_name) {
      if ($this->hasTable($log_table_name))
        $this->table($log_table_name)->drop()->update();
    }
  }

  public function down():void {
    throw new IrreversibleMigrationException();
  }

  // HELPER METHODS

  private function laravelTimestamps(bool $up, Table $table):Table {
    if ($up){
      return $table
        ->addColumn('created_at', 'timestamp', ['timezone' => true, 'null' => true, 'default' => 'CURRENT_TIMESTAMP'])
        ->addColumn('updated_at', 'timestamp', ['timezone' => true, 'null' => true, 'default' => 'CURRENT_TIMESTAMP']);
    }

    return $table
      ->removeColumn('created_at')
      ->removeColumn('updated_at');
  }

  /**
   * @param string        $log_table_name
   * @param string        $new_name
   * @param callable|null $table_transformer A function that will receive the $table variable.
   *                                         May optionally return with another function that will be called
   *                                         after the changes this method makes to the table.
   */
  private function renameLogTable(string $log_table_name, string $new_name, callable $table_transformer = null):void {
    $table = $this->table($log_table_name);
    $post_rename = null;
    if ($table_transformer)
      $post_rename = $table_transformer($table);

    $table
      ->rename($new_name)
      ->renameColumn('entryid', 'id');
    if (is_callable($post_rename))
      $post_rename($table);
    $table->update();

    $this->execute("ALTER SEQUENCE {$log_table_name}_entryid_seq RENAME TO {$new_name}_id_seq");
  }
}
