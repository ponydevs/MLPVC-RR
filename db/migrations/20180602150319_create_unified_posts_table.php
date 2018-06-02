<?php

use Phinx\Migration\AbstractMigration;

class CreateUnifiedPostsTable extends AbstractMigration {
	public function up() {
		$posts_table = $this->table('posts')
			->addColumn('old_id',       'integer',   ['null' => true])
			->addColumn('type',         'string',    ['length' => 3, 'null' => true])
			->addColumn('season',       'integer')
			->addColumn('episode',      'integer')
			->addColumn('preview',      'string',    ['length' => 255, 'null' => true])
			->addColumn('fullsize',     'string',    ['length' => 255, 'null' => true])
			->addColumn('label',        'string',    ['length' => 255, 'null' => true])
			->addColumn('requested_by', 'uuid',      ['null' => true])
			->addColumn('requested_at', 'timestamp', ['timezone' => true, 'null' => true])
			->addColumn('reserved_by',  'uuid',      ['null' => true])
			->addColumn('reserved_at',  'timestamp', ['timezone' => true, 'null' => true])
			->addColumn('deviation_id', 'string',    ['length' => 7, 'null' => true])
			->addColumn('lock',         'boolean',   ['default' => false])
			->addColumn('finished_at',  'timestamp', ['timezone' => true, 'null' => true])
			->addColumn('broken',       'boolean',   ['default' => false])
			->addIndex('old_id')
			->addIndex('requested_by')
			->addIndex('reserved_by')
			->addIndex(['season','episode'])
			->addForeignKey(['season','episode'], 'episodes', ['season','episode'], ['delete' => 'cascade', 'update' => 'cascade'])
			->addForeignKey('requested_by', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
			->addForeignKey('reserved_by',  'users', 'id', ['delete' => 'restrict', 'update' => 'cascade']);
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
				else if (\is_bool($v))
					$item[$k] = $v ? 't' : 'f';
			}
		}

		if (!empty($data))
			$posts_table->insert($data)->save();

		#$this->table('requests')->drop();
		#$this->table('reservations')->drop();

		$req_del_table = $this->table('log__req_delete');
		$req_del_table->renameColumn('id','old_id')->save();
		$req_del_table->addColumn('id', 'integer', ['null' => true])->save();
	}

	function down() {
		$this->table('posts')->drop();

		$req_del_table = $this->table('log__req_delete');
		$req_del_table->removeColumn('id')->save();
		$req_del_table->renameColumn('old_id','id')->save();
	}
}
