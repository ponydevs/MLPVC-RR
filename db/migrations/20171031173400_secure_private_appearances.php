<?php

use Phinx\Migration\AbstractMigration;

class SecurePrivateAppearances extends AbstractMigration {
	public function change() {
		$this->table('appearances')
			->addColumn('token', 'uuid', ['default' => 'uuid_generate_v4()'])
			->save();
	}
}
