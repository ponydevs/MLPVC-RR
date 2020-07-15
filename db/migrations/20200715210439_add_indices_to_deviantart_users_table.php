<?php

use Phinx\Migration\AbstractMigration;

class AddIndicesToDeviantartUsersTable extends AbstractMigration
{
    public function change()
    {
      $this->table('deviantart_users')->addIndex('user_id')->addIndex('name', ['unique' => true])->update();
    }
}
