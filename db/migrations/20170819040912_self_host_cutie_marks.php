<?php

use Phinx\Migration\AbstractMigration;

class SelfHostCutieMarks extends AbstractMigration {
	public function up(){
		$this->table('cutiemarks')
			->removeColumn('preview')
			->removeColumn('preview_src')
			->changeColumn('favme', 'string', ['length' => 30])
			->renameColumn('favme_rotation','rotation')
			->update();
		$this->execute('ALTER TABLE cutiemarks ADD contributor_id uuid NULL');
	}

	public function down(){
		$this->table('cutiemarks')
			->addColumn('preview',     'string', ['length' => 255, 'null' => true])
			->addColumn('preview_src', 'string', ['length' => 255, 'null' => true])
			->changeColumn('favme', 'string', ['length' => 7])
			->renameColumn('rotation','favme_rotation')
			->removeColumn('contributor_id')
			->update();
	}
}
