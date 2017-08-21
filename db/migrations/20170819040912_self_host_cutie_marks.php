<?php

use Phinx\Migration\AbstractMigration;

class SelfHostCutieMarks extends AbstractMigration {
	public function change(){
		$this->table('cutiemarks')
			->removeColumn('preview')
			->removeColumn('preview_src')
			->renameColumn('favme_rotation','rotation')
			->addColumn('contributor_id', 'uuid', ['null' => true])
			->update();
	}
}
