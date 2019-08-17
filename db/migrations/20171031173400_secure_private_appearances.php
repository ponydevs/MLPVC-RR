<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Util\Literal;

class SecurePrivateAppearances extends AbstractMigration {
  public function change() {
    $this->table('appearances')
      ->addColumn('token', 'uuid', ['default' => Literal::from('uuid_generate_v4()')])
      ->save();
  }
}
