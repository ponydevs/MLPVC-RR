<?php

use App\JSON;
use Phinx\Db\Table;
use Phinx\Migration\AbstractMigration;

class CreateUnifiedPostsTable extends AbstractMigration {
  private function _renameIdColumn(Table $t) {
    $t->renameColumn('id', 'old_id')->save();
    $t->addColumn('id', 'integer', ['null' => true])
      ->changeColumn('old_id', 'integer', ['null' => true])
      ->changeColumn('type', 'string', ['length' => 11, 'default' => 'post'])
      ->save();
  }

  public function up() {
    $posts_table = $this->table('posts')
      ->addColumn('old_id', 'integer', ['null' => true])
      ->addColumn('type', 'string', ['length' => 3, 'null' => true])
      ->addColumn('season', 'integer')
      ->addColumn('episode', 'integer')
      ->addColumn('preview', 'string', ['length' => 255, 'null' => true])
      ->addColumn('fullsize', 'string', ['length' => 255, 'null' => true])
      ->addColumn('label', 'string', ['length' => 255, 'null' => true])
      ->addColumn('requested_by', 'uuid', ['null' => true])
      ->addColumn('requested_at', 'timestamp', ['timezone' => true, 'null' => true])
      ->addColumn('reserved_by', 'uuid', ['null' => true])
      ->addColumn('reserved_at', 'timestamp', ['timezone' => true, 'null' => true])
      ->addColumn('deviation_id', 'string', ['length' => 7, 'null' => true])
      ->addColumn('lock', 'boolean', ['default' => false])
      ->addColumn('finished_at', 'timestamp', ['timezone' => true, 'null' => true])
      ->addColumn('broken', 'boolean', ['default' => false])
      ->addIndex('old_id')
      ->addIndex('requested_by')
      ->addIndex('reserved_by')
      ->addIndex(['season', 'episode'])
      ->addForeignKey(['season', 'episode'], 'episodes', ['season', 'episode'], ['delete' => 'cascade', 'update' => 'cascade'])
      ->addForeignKey('requested_by', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
      ->addForeignKey('reserved_by', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade']);
    $posts_table->create();

    $data = $this->fetchAll(
      'SELECT
				id,
				null as "type",
				season,
				episode,
				preview,
				fullsize,
				"label",
				null as requested_by,
				null as requested_at,
				reserved_by,
				reserved_at,
				deviation_id,
				"lock",
				finished_at,
				broken
			FROM reservations
			UNION ALL 
			SELECT
				id,
				"type",
				season,
				episode,
				preview,
				fullsize,
				"label",
				requested_by,
				requested_at,
				reserved_by,
				reserved_at,
				deviation_id,
				"lock",
				finished_at,
				broken
			FROM requests');

    foreach ($data as &$item){
      $item['old_id'] = $item['id'];
      unset($item['id']);

      foreach ($item as $k => $v){
        if (is_numeric($k))
          unset($item[$k]);
        else if (is_bool($v))
          $item[$k] = $v ? 't' : 'f';
      }
    }
    unset($item);

    if (!empty($data))
      $posts_table->insert($data)->save();

    $this->table('requests')->drop()->save();
    $this->table('reservations')->drop()->save();

    $img_update_table = $this->table('log__img_update');
    $img_update_table->renameColumn('thing', 'type')->save();
    $this->_renameIdColumn($img_update_table);
    $this->_renameIdColumn($this->table('log__post_break'));
    $this->_renameIdColumn($this->table('log__post_fix'));
    $this->_renameIdColumn($this->table('log__post_lock'));
    $this->_renameIdColumn($this->table('log__req_delete'));
    $this->_renameIdColumn($this->table('log__res_overtake'));
    $this->_renameIdColumn($this->table('log__res_transfer'));

    $post_notifs = $this->fetchAll("SELECT * FROM notifications WHERE type LIKE 'post-%'");
    foreach ($post_notifs as $notif){
      $notif_data = JSON::decode($notif['data']);
      $NOT = $notif_data['type'] === 'request' ? 'NOT' : '';
      $new_post = $this->fetchRow("SELECT id FROM posts WHERE requested_by IS $NOT NULL AND old_id = {$notif_data['id']}");
      if (empty($new_post['id']))
        $this->query("DELETE FROM notifications WHERE id = {$notif['id']}");
      else $this->query("UPDATE notifications SET data = data - 'type' || jsonb_build_object('id', {$new_post['id']}) WHERE id = {$notif['id']}");
    }

    $pcg_history = $this->fetchAll("SELECT * FROM pcg_slot_history WHERE change_type LIKE 'post_%'");
    foreach ($pcg_history as $entry){
      $change_data = JSON::decode($entry['change_data']);
      $NOT = $change_data['type'] === 'request' ? 'NOT' : '';
      $new_post = $this->fetchRow("SELECT id FROM posts WHERE requested_by IS $NOT NULL AND old_id = {$change_data['id']}");
      if (empty($new_post['id']))
        $this->query("DELETE FROM pcg_slot_history WHERE id = {$entry['id']}");
      else $this->query("UPDATE pcg_slot_history SET change_data = change_data - 'type' || jsonb_build_object('id', {$new_post['id']}) WHERE id = {$entry['id']}");
    }
  }
}
