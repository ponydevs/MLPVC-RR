<?php

use Phinx\Db\Table;
use Phinx\Migration\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;
use Phinx\Util\Literal;

/*
 * You'll need to import setup/create_extensions.pg.sql as superuser before the first migration
 */

class ConvertSchemaToMigration extends AbstractMigration {
  /** @var Table[] */
  protected $tables = [];

  public function up() {
    $table = 'appearances';
    $this->tables[$table] = $this->table($table)
      ->addColumn('order', 'integer', ['null' => true])
      ->addColumn('label', 'string', ['length' => 70])
      ->addColumn('notes', 'text', ['null' => true])
      ->addColumn('ishuman', 'boolean', ['null' => true])
      ->addColumn('added', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP', 'null' => true])
      ->addColumn('private', 'boolean', ['default' => false])
      ->addColumn('owner_id', 'uuid', ['null' => true])
      ->addColumn('last_cleared', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP', 'null' => true])
      ->addIndex('ishuman')
      ->addIndex('label')
      ->addIndex('order')
      ->addIndex('owner_id');
    $this->tables[$table]->create();

    $table = 'cached_deviations';
    $this->tables[$table] = $this->table($table, ['id' => false, 'primary_key' => ['provider', 'id']])
      ->addColumn('provider', 'char', ['length' => 6])
      ->addColumn('id', 'string', ['length' => 20])
      ->addColumn('title', 'string', ['length' => 255])
      ->addColumn('author', 'string', ['length' => 20, 'null' => true])
      ->addColumn('preview', 'string', ['length' => 255, 'null' => true])
      ->addColumn('fullsize', 'string', ['length' => 255, 'null' => true])
      ->addColumn('updated_on', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP', 'null' => true])
      ->addColumn('type', 'string', ['length' => 12, 'null' => true])
      ->addIndex('author');
    $this->tables[$table]->create();

    $table = 'color_groups';
    $this->tables[$table] = $this->table($table)
      ->addColumn('appearance_id', 'integer')
      ->addColumn('label', 'string', ['length' => 255])
      ->addColumn('order', 'integer')
      ->addIndex('appearance_id')
      ->addIndex(['appearance_id', 'label'], ['unique' => true]);
    $this->tables[$table]->create();

    $table = 'colors';
    $this->tables[$table] = $this->table($table, ['id' => false])
      ->addColumn('group_id', 'integer')
      ->addColumn('order', 'integer')
      ->addColumn('label', 'string', ['length' => 255])
      ->addColumn('hex', 'char', ['length' => 7, 'null' => true])
      ->addIndex('group_id');
    $this->tables[$table]->create();

    $table = 'cutiemarks';
    $this->tables[$table] = $this->table($table)
      ->addColumn('appearance_id', 'integer')
      ->addColumn('facing', 'string', ['length' => 10, 'null' => true])
      ->addColumn('favme', 'string', ['length' => 7])
      ->addColumn('favme_rotation', 'smallinteger')
      ->addColumn('preview', 'string', ['length' => 255, 'null' => true])
      ->addColumn('preview_src', 'string', ['length' => 255, 'null' => true])
      ->addIndex(['appearance_id', 'facing'], ['unique' => true]);
    $this->tables[$table]->create();

    $table = 'discord_members';
    $this->tables[$table] = $this->table($table, ['id' => false, 'primary_key' => 'id'])
      ->addColumn('id', 'string', ['length' => 20])
      ->addColumn('user_id', 'uuid', ['null' => true])
      ->addColumn('username', 'string', ['length' => 255])
      ->addColumn('discriminator', 'integer')
      ->addColumn('nick', 'string', ['length' => 255, 'null' => true])
      ->addColumn('avatar_hash', 'string', ['length' => 255, 'null' => true])
      ->addColumn('joined_at', 'timestamp', ['timezone' => true]);
    $this->tables[$table]->create();

    $table = 'episode_videos';
    $this->tables[$table] = $this->table($table, ['id' => false, 'primary_key' => ['season', 'episode', 'provider', 'part']])
      ->addColumn('season', 'integer')
      ->addColumn('episode', 'integer')
      ->addColumn('provider', 'char', ['length' => 2])
      ->addColumn('id', 'string', ['length' => 15])
      ->addColumn('part', 'integer', ['default' => 1])
      ->addColumn('fullep', 'boolean', ['default' => true])
      ->addColumn('modified', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP', 'null' => true])
      ->addColumn('not_broken_at', 'timestamp', ['timezone' => true, 'null' => true]);
    $this->tables[$table]->create();

    $table = 'episode_votes';
    $this->tables[$table] = $this->table($table, ['id' => false, 'primary_key' => ['season', 'episode', 'user_id']])
      ->addColumn('season', 'integer')
      ->addColumn('episode', 'integer')
      ->addColumn('user_id', 'uuid')
      ->addColumn('vote', 'smallinteger')
      ->addIndex('user_id');
    $this->tables[$table]->create();

    $table = 'episodes';
    $this->tables[$table] = $this->table($table, ['id' => false, 'primary_key' => ['season', 'episode']])
      ->addColumn('season', 'integer')
      ->addColumn('episode', 'integer')
      ->addColumn('twoparter', 'boolean', ['default' => false])
      ->addColumn('title', 'text')
      ->addColumn('posted', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
      ->addColumn('posted_by', 'uuid')
      ->addColumn('airs', 'timestamp', ['timezone' => true, 'null' => true])
      ->addColumn('no', 'smallinteger', ['null' => true])
      ->addColumn('score', 'float', ['default' => 0])
      ->addColumn('notes', 'text', ['null' => true])
      ->addIndex('posted_by');
    $this->tables[$table]->create();

    $table = 'events';
    $this->tables[$table] = $this->table($table)
      ->addColumn('name', 'string', ['limit' => 64])
      ->addColumn('type', 'string', ['limit' => 10])
      ->addColumn('entry_role', 'string', ['limit' => 15])
      ->addColumn('starts_at', 'timestamp', ['timezone' => true])
      ->addColumn('ends_at', 'timestamp', ['timezone' => true])
      ->addColumn('added_by', 'uuid')
      ->addColumn('added_at', 'timestamp', ['timezone' => true])
      ->addColumn('desc_src', 'text')
      ->addColumn('desc_rend', 'text')
      ->addColumn('max_entries', 'integer', ['null' => true])
      ->addColumn('vote_role', 'string', ['limit' => 15, 'null' => true])
      ->addColumn('result_favme', 'string', ['limit' => 7, 'null' => true])
      ->addColumn('finalized_at', 'timestamp', ['timezone' => true, 'null' => true])
      ->addColumn('finalized_by', 'uuid', ['null' => true]);
    $this->tables[$table]->create();

    $table = 'event_entries';
    $this->tables[$table] = $this->table($table)
      ->addColumn('event_id', 'integer')
      ->addColumn('prev_src', 'string', ['length' => 255, 'null' => true])
      ->addColumn('prev_full', 'string', ['length' => 255, 'null' => true])
      ->addColumn('prev_thumb', 'string', ['length' => 255, 'null' => true])
      ->addColumn('sub_prov', 'string', ['length' => 20])
      ->addColumn('sub_id', 'string', ['length' => 20])
      ->addColumn('submitted_by', 'uuid')
      ->addColumn('submitted_at', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
      ->addColumn('title', 'string', ['length' => 64])
      ->addColumn('score', 'integer', ['null' => true])
      ->addColumn('last_edited', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP']);
    $this->tables[$table]->create();

    $table = 'event_entry_votes';
    $this->tables[$table] = $this->table($table, ['id' => false, 'primary_key' => ['entry_id', 'user_id']])
      ->addColumn('entry_id', 'integer')
      ->addColumn('user_id', 'uuid')
      ->addColumn('value', 'smallinteger')
      ->addColumn('cast_at', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP']);
    $this->tables[$table]->create();

    $table = 'global_settings';
    $this->tables[$table] = $this->table($table, ['id' => false])
      ->addColumn('key', 'string', ['limit' => 50])
      ->addColumn('value', 'text', ['null' => true]);
    $this->tables[$table]->create();

    $table = 'known_ips';
    $this->tables[$table] = $this->table($table)
      ->addColumn('ip', 'inet')
      ->addColumn('user_id', 'uuid', ['null' => true])
      ->addColumn('first_seen', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
      ->addColumn('last_seen', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
      ->addIndex(['ip', 'user_id'], ['unique' => true]);
    $this->tables[$table]->create();

    $table = 'log';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('initiator', 'uuid', ['null' => true])
      ->addColumn('reftype', 'string', ['length' => 20])
      ->addColumn('refid', 'integer', ['null' => true])
      ->addColumn('timestamp', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
      ->addColumn('ip', 'inet')
      ->addIndex('initiator');
    $this->tables[$table]->create();

    $table = 'log__appearance_modify';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('appearance_id', 'integer')
      ->addColumn('changes', 'jsonb')
      ->addIndex('appearance_id');
    $this->tables[$table]->create();

    $table = 'log__appearances';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('action', 'char', ['length' => 3])
      ->addColumn('id', 'integer')
      ->addColumn('order', 'integer', ['null' => true])
      ->addColumn('label', 'string', ['length' => 70])
      ->addColumn('notes', 'text', ['null' => true])
      ->addColumn('ishuman', 'boolean', ['null' => true])
      ->addColumn('added', 'timestamp', ['timezone' => true, 'null' => true])
      ->addColumn('usetemplate', 'boolean', ['default' => false])
      ->addColumn('private', 'boolean', ['default' => false])
      ->addColumn('owner_id', 'uuid', ['null' => true]);
    $this->tables[$table]->create();

    $table = 'log__banish';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('target_id', 'uuid')
      ->addColumn('reason', 'string', ['length' => 255])
      ->addIndex('target_id');
    $this->tables[$table]->create();

    $table = 'log__cg_modify';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('group_id', 'integer')
      ->addColumn('oldlabel', 'string', ['length' => 255, 'null' => true])
      ->addColumn('newlabel', 'string', ['length' => 255, 'null' => true])
      ->addColumn('oldcolors', 'text', ['null' => true])
      ->addColumn('newcolors', 'text', ['null' => true])
      ->addColumn('appearance_id', 'integer')
      ->addIndex('appearance_id')
      ->addIndex('group_id');
    $this->tables[$table]->create();

    $table = 'log__cg_order';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('appearance_id', 'integer')
      ->addColumn('oldgroups', 'text')
      ->addColumn('newgroups', 'text')
      ->addIndex('appearance_id');
    $this->tables[$table]->create();

    $table = 'log__cgs';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('action', 'char', ['length' => 3])
      ->addColumn('group_id', 'integer')
      ->addColumn('appearance_id', 'integer')
      ->addColumn('label', 'string', ['length' => 255])
      ->addColumn('order', 'integer', ['null' => true])
      ->addIndex('appearance_id')
      ->addIndex('group_id');
    $this->tables[$table]->create();

    $table = 'log__cm_delete';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('appearance_id', 'integer')
      ->addColumn('data', 'jsonb')
      ->addIndex('appearance_id');
    $this->tables[$table]->create();

    $table = 'log__cm_modify';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('appearance_id', 'integer')
      ->addColumn('olddata', 'jsonb')
      ->addColumn('newdata', 'jsonb')
      ->addIndex('appearance_id');
    $this->tables[$table]->create();

    $table = 'log__major_changes';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('appearance_id', 'integer')
      ->addColumn('reason', 'string', ['length' => 255])
      ->addIndex('appearance_id');
    $this->tables[$table]->create();

    $table = 'log__da_namechange';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('old', Literal::from('citext'))
      ->addColumn('new', Literal::from('citext'))
      ->addColumn('user_id', 'uuid')
      ->addIndex('user_id');
    $this->tables[$table]->create();

    $table = 'log__episode_modify';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('target', 'text')
      ->addColumn('oldseason', 'integer', ['null' => true])
      ->addColumn('newseason', 'integer', ['null' => true])
      ->addColumn('oldepisode', 'integer', ['null' => true])
      ->addColumn('newepisode', 'integer', ['null' => true])
      ->addColumn('oldtwoparter', 'boolean', ['null' => true])
      ->addColumn('newtwoparter', 'boolean', ['null' => true])
      ->addColumn('oldtitle', 'text', ['null' => true])
      ->addColumn('newtitle', 'text', ['null' => true])
      ->addColumn('oldairs', 'timestamp', ['timezone' => true, 'null' => true])
      ->addColumn('newairs', 'timestamp', ['timezone' => true, 'null' => true]);
    $this->tables[$table]->create();

    $table = 'log__episodes';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('action', 'char', ['length' => 3])
      ->addColumn('season', 'integer')
      ->addColumn('episode', 'integer')
      ->addColumn('twoparter', 'boolean')
      ->addColumn('title', 'text')
      ->addColumn('airs', 'timestamp', ['timezone' => true, 'null' => true]);
    $this->tables[$table]->create();

    $table = 'log__img_update';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('id', 'integer')
      ->addColumn('thing', 'string', ['length' => 11])
      ->addColumn('oldpreview', 'string', ['length' => 255, 'null' => true])
      ->addColumn('newpreview', 'string', ['length' => 255, 'null' => true])
      ->addColumn('oldfullsize', 'string', ['length' => 255, 'null' => true])
      ->addColumn('newfullsize', 'string', ['length' => 255, 'null' => true]);
    $this->tables[$table]->create();

    $table = 'log__post_break';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('type', 'string', ['length' => 11])
      ->addColumn('id', 'integer')
      ->addColumn('reserved_by', 'uuid', ['null' => true]);
    $this->tables[$table]->create();

    $table = 'log__post_fix';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('type', 'string', ['length' => 11])
      ->addColumn('id', 'integer')
      ->addColumn('reserved_by', 'uuid', ['null' => true]);
    $this->tables[$table]->create();

    $table = 'log__post_lock';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('type', 'string', ['length' => 11])
      ->addColumn('id', 'integer');
    $this->tables[$table]->create();

    $table = 'log__req_delete';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('id', 'integer', ['null' => true])
      ->addColumn('season', 'integer', ['null' => true])
      ->addColumn('episode', 'integer', ['null' => true])
      ->addColumn('label', 'string', ['length' => 255, 'null' => true])
      ->addColumn('type', 'string', ['length' => 4, 'null' => true])
      ->addColumn('requested_by', 'uuid', ['null' => true])
      ->addColumn('requested_at', 'timestamp', ['null' => true, 'timezone' => true])
      ->addColumn('reserved_by', 'uuid', ['null' => true])
      ->addColumn('deviation_id', 'string', ['length' => 7, 'null' => true])
      ->addColumn('lock', 'boolean', ['null' => true]);
    $this->tables[$table]->create();

    $table = 'log__res_overtake';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('type', 'string', ['length' => 11])
      ->addColumn('id', 'integer')
      ->addColumn('reserved_at', 'timestamp', ['null' => true, 'timezone' => true])
      ->addColumn('reserved_by', 'uuid');
    $this->tables[$table]->create();

    $table = 'log__res_transfer';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('type', 'string', ['length' => 11])
      ->addColumn('id', 'integer')
      ->addColumn('to', 'uuid');
    $this->tables[$table]->create();

    $table = 'log__rolechange';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('target', 'uuid')
      ->addColumn('oldrole', 'string', ['length' => 10])
      ->addColumn('newrole', 'string', ['length' => 10])
      ->addIndex('target');
    $this->tables[$table]->create();

    $table = 'log__unbanish';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('target_id', 'uuid')
      ->addColumn('reason', 'string', ['length' => 255])
      ->addIndex('target_id');
    $this->tables[$table]->create();

    $table = 'log__userfetch';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('userid', 'uuid')
      ->addIndex('userid');
    $this->tables[$table]->create();

    $table = 'log__video_broken';
    $this->tables[$table] = $this->table($table, ['id' => 'entryid'])
      ->addColumn('season', 'integer')
      ->addColumn('episode', 'integer')
      ->addColumn('provider', 'char', ['length' => 2])
      ->addColumn('id', 'string', ['length' => 15]);
    $this->tables[$table]->create();

    $table = 'notifications';
    $this->tables[$table] = $this->table($table)
      ->addColumn('recipient_id', 'uuid')
      ->addColumn('type', 'string', ['length' => 15])
      ->addColumn('data', 'jsonb')
      ->addColumn('sent_at', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
      ->addColumn('read_at', 'timestamp', ['timezone' => true, 'null' => true])
      ->addColumn('read_action', 'string', ['length' => 15, 'null' => true])
      ->addIndex('recipient_id')
      ->addIndex('type');
    $this->tables[$table]->create();

    $table = 'related_appearances';
    $this->tables[$table] = $this->table($table, ['id' => false, 'primary_key' => ['source_id', 'target_id']])
      ->addColumn('source_id', 'integer')
      ->addColumn('target_id', 'integer');
    $this->tables[$table]->create();

    $table = 'requests';
    $this->tables[$table] = $this->table($table)
      ->addColumn('type', 'string', ['length' => 3])
      ->addColumn('season', 'integer')
      ->addColumn('episode', 'integer')
      ->addColumn('preview', 'string', ['length' => 255])
      ->addColumn('fullsize', 'string', ['length' => 255])
      ->addColumn('label', 'string', ['length' => 255])
      ->addColumn('requested_by', 'uuid')
      ->addColumn('requested_at', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
      ->addColumn('reserved_by', 'uuid', ['null' => true])
      ->addColumn('reserved_at', 'timestamp', ['timezone' => true, 'null' => true])
      ->addColumn('deviation_id', 'string', ['length' => 7, 'null' => true])
      ->addColumn('lock', 'boolean', ['default' => false])
      ->addColumn('finished_at', 'timestamp', ['timezone' => true, 'null' => true])
      ->addColumn('broken', 'boolean', ['default' => false])
      ->addIndex('requested_by')
      ->addIndex('reserved_by')
      ->addIndex(['season', 'episode']);
    $this->tables[$table]->create();

    $table = 'reservations';
    $this->tables[$table] = $this->table($table)
      ->addColumn('season', 'integer')
      ->addColumn('episode', 'integer')
      ->addColumn('preview', 'string', ['length' => 255, 'null' => true])
      ->addColumn('fullsize', 'string', ['length' => 255, 'null' => true])
      ->addColumn('label', 'string', ['length' => 255, 'null' => true])
      ->addColumn('reserved_at', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
      ->addColumn('reserved_by', 'uuid')
      ->addColumn('deviation_id', 'string', ['length' => 255, 'null' => true])
      ->addColumn('lock', 'boolean', ['default' => false])
      ->addColumn('finished_at', 'timestamp', ['timezone' => true, 'null' => true])
      ->addColumn('broken', 'boolean', ['default' => false])
      ->addIndex('reserved_by')
      ->addIndex(['season', 'episode']);
    $this->tables[$table]->create();

    $table = 'sessions';
    $this->tables[$table] = $this->table($table)
      ->addColumn('user_id', 'uuid')
      ->addColumn('platform', 'string', ['length' => 50])
      ->addColumn('browser_name', 'string', ['length' => 50, 'null' => true])
      ->addColumn('browser_ver', 'string', ['length' => 50, 'null' => true])
      ->addColumn('user_agent', 'string', ['length' => 300, 'null' => true])
      ->addColumn('token', 'string', ['length' => 64])
      ->addColumn('access', 'string', ['length' => 50])
      ->addColumn('refresh', 'string', ['length' => 40])
      ->addColumn('expires', 'timestamp', ['timezone' => true, 'null' => true])
      ->addColumn('created', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
      ->addColumn('lastvisit', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
      ->addColumn('scope', 'string', ['length' => 50, 'default' => 'user browse'])
      ->addIndex('user_id');
    $this->tables[$table]->create();

    $table = 'tagged';
    $this->tables[$table] = $this->table($table, ['id' => false, 'primary_key' => ['tag_id', 'appearance_id']])
      ->addColumn('tag_id', 'integer')
      ->addColumn('appearance_id', 'integer');
    $this->tables[$table]->create();

    $table = 'tags';
    $this->tables[$table] = $this->table($table)
      ->addColumn('name', 'string', ['length' => 30])
      ->addColumn('title', 'string', ['length' => 255, 'null' => true])
      ->addColumn('type', 'string', ['length' => 4, 'null' => true])
      ->addColumn('uses', 'integer', ['default' => 0])
      ->addColumn('synonym_of', 'integer', ['null' => true])
      ->addIndex('name')
      ->addIndex('synonym_of');
    $this->tables[$table]->create();

    $table = 'users';
    $this->tables[$table] = $this->table($table, ['id' => false, 'primary_key' => 'id'])
      ->addColumn('id', 'uuid')
      ->addColumn('name', Literal::from('citext'))
      ->addColumn('role', 'string', ['length' => 10, 'default' => 'user'])
      ->addColumn('avatar_url', 'string', ['length' => 255])
      ->addColumn('signup_date', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP']);
    $this->tables[$table]->create();

    $this->execute(<<<SQL
			CREATE VIEW unread_notifications AS
			SELECT
				u.name AS "user",
				count(n.id) AS count
			FROM notifications n
			LEFT JOIN users u ON n.recipient_id = u.id
			WHERE n.read_at IS NULL
			GROUP BY u.name
			ORDER BY count(n.id) DESC;
			SQL
    );

    $table = 'useful_links';
    $this->tables[$table] = $this->table($table)
      ->addColumn('url', 'string', ['length' => 255])
      ->addColumn('label', 'string', ['length' => 40])
      ->addColumn('title', 'string', ['length' => 255])
      ->addColumn('minrole', 'string', ['length' => 10, 'default' => 'user'])
      ->addColumn('order', 'integer', ['null' => true]);
    $this->tables[$table]->create();

    $table = 'user_prefs';
    $this->tables[$table] = $this->table($table, ['id' => false, 'primary_key' => ['user_id', 'key']])
      ->addColumn('user_id', 'uuid')
      ->addColumn('key', 'string', ['length' => 50])
      ->addColumn('value', 'text', ['null' => true]);
    $this->tables[$table]->create();

    // Add all foreign keys
    $this->tables['appearances']
      ->addForeignKey('owner_id', 'users', 'id', ['delete' => 'NO ACTION', 'update' => 'CASCADE'])
      ->update();
    $this->tables['color_groups']
      ->addForeignKey('appearance_id', 'appearances', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
      ->update();
    $this->tables['colors']
      ->addForeignKey('group_id', 'color_groups', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
      ->update();
    $this->tables['cutiemarks']
      ->addForeignKey('appearance_id', 'appearances', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
      ->update();
    $this->tables['discord_members']
      ->addForeignKey('user_id', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->update();
    $this->tables['episode_videos']
      ->addForeignKey(['season', 'episode'], 'episodes', ['season', 'episode'], ['delete' => 'CASCADE', 'update' => 'CASCADE'])
      ->update();
    $this->tables['episode_votes']
      ->addForeignKey(['season', 'episode'], 'episodes', ['season', 'episode'], ['delete' => 'CASCADE', 'update' => 'CASCADE'])
      ->addForeignKey('user_id', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->update();
    $this->tables['episodes']->addForeignKey('posted_by', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->update();
    $this->tables['events']
      ->addForeignKey('added_by', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->addForeignKey('finalized_by', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->update();
    $this->tables['event_entries']
      ->addForeignKey('event_id', 'events', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
      ->addForeignKey('submitted_by', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->update();
    $this->tables['event_entry_votes']
      ->addForeignKey('entry_id', 'event_entries', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
      ->addForeignKey('user_id', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->update();
    $this->tables['known_ips']
      ->addForeignKey('user_id', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->update();
    $this->tables['log']
      ->addForeignKey('initiator', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->update();
    $this->tables['log__banish']
      ->addForeignKey('target_id', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->update();
    $this->tables['log__da_namechange']
      ->addForeignKey('user_id', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->update();
    $this->tables['log__unbanish']
      ->addForeignKey('target_id', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->update();
    $this->tables['notifications']
      ->addForeignKey('recipient_id', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->update();
    $this->tables['related_appearances']
      ->addForeignKey('source_id', 'appearances', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
      ->addForeignKey('target_id', 'appearances', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
      ->update();
    $this->tables['requests']
      ->addForeignKey(['season', 'episode'], 'episodes', ['season', 'episode'], ['delete' => 'CASCADE', 'update' => 'CASCADE'])
      ->addForeignKey('requested_by', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->addForeignKey('reserved_by', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->update();
    $this->tables['reservations']
      ->addForeignKey(['season', 'episode'], 'episodes', ['season', 'episode'], ['delete' => 'CASCADE', 'update' => 'CASCADE'])
      ->addForeignKey('reserved_by', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->update();
    $this->tables['sessions']
      ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
      ->update();
    $this->tables['tagged']
      ->addForeignKey('tag_id', 'tags', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
      ->addForeignKey('appearance_id', 'appearances', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
      ->update();
    $this->tables['tags']
      ->addForeignKey('synonym_of', 'tags', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
      ->update();
    $this->tables['user_prefs']
      ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
      ->update();
  }

  public function down():void {
    throw new IrreversibleMigrationException("There is no going back");
  }
}
