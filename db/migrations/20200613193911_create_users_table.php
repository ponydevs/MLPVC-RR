<?php /** @noinspection SqlResolve */

use Phinx\Migration\AbstractMigration;

class CreateUsersTable extends AbstractMigration {
  public function up() {
    $this->query('ALTER TABLE deviantart_users RENAME CONSTRAINT users_pkey TO deviantart_users_pkey');

    $this->table('users')
      ->addColumn('name', 'string')
      ->addColumn('email', 'string', ['null' => true])
      ->addColumn('role', 'string', ['length' => 10, 'default' => 'user'])
      ->addColumn('email_verified_at', 'timestamp', ['timezone' => true, 'null' => true])
      ->addColumn('password', 'string', ['null' => true])
      ->addColumn('remember_token', 'string', ['length' => 100, 'null' => true])
      ->addIndex('name', ['unique' => true])
      ->addIndex('email', ['unique' => true])
      ->addTimestampsWithTimezone()
      ->create();

    $this->query(<<<'SQL'
      INSERT INTO users (
        name,
        role,
        created_at
      )
      SELECT
        name,
        role,
        created_at
      FROM deviantart_users
      ORDER BY created_at
    SQL);

    $this->table('deviantart_users')
      ->addColumn('user_id', 'integer', ['null' => true])
      ->addForeignKey('user_id', 'users', 'id', ['delete' => 'cascade', 'update' => 'cascade'])
      ->update();

    $this->query('UPDATE deviantart_users du SET user_id = u.id FROM (SELECT id, name FROM users) u WHERE u.name = du.name');

    $this->table('deviantart_users')
      ->removeColumn('role')
      ->update();
  }

  public function down() {
    $this->table('deviantart_users')
      ->addColumn('role', 'string', ['length' => 10, 'default' => 'user'])
      ->update();

    $this->query('UPDATE deviantart_users du SET role = u.role FROM (SELECT id, role FROM users) u WHERE u.id = du.user_id');

    $this->table('deviantart_users')
      ->removeColumn('user_id')
      ->update();

    $this->table('users')->drop()->save();

    $this->query('ALTER TABLE deviantart_users RENAME CONSTRAINT deviantart_users_pkey TO users_pkey');
  }
}
