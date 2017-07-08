<?php

namespace App;

use App\Models\Episode;

class PostgresDbWrapper extends \PostgresDb {
	public static function withConnection(string $db, \PDO $PDO):PostgresDbWrapper {
		$instance = new PostgresDbWrapper($db);
		$instance->setConnection($PDO);
		return $instance;
	}

	public function setConnection(\PDO $PDO){
		$this->_conn = $PDO;
	}

	/**
	 * Execute where method with the specified episode and season numbers
	 *
	 * @param int|Episode $s Season, or array with keys season & episode
	 * @param int|null    $e Episode, optional if $s is and instance of Episode
	 *
	 * @return self
	 */
	public function whereEp($s, $e = null){
		if (!isset($e)){
			parent::where('season', $s->season);
			parent::where('episode', $s->episode);
		}
		else {
			parent::where('season', $s);
			parent::where('episode', $e);
		}
		return $this;
	}

	public function setModel(string $name){
		if (!class_exists("\\App\\Models\\$name"))
			throw new \Exception();

		return $this->setClass("\\App\\Models\\$name", \PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE);
	}

	private $_nonexistantClassCache = [];

	/**
	 * @param \PDOStatement $stmt Statement to execute
	 *
	 * @return bool|array|object[]
	 */
	protected function _execStatement($stmt){
		$className = $this->tableNameToClassName();
		if (isset($className) && empty($this->_nonexistantClassCache[$className])){
			try {
				$this->setModel($className);
			}
			catch (\Exception $e){ $this->_nonexistantClassCache[$className] = true; }
		}

		return parent::_execStatement($stmt);
	}
}
