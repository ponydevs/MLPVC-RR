<?php

namespace App;

class PostgresDbWrapper extends \PostgresDb {
	/**
	 * Execute where method with the specified episode and season numbers
	 *
	 * @param string|int|Episode $s Season, or array with keys season & episode
	 * @param string|int|null    $e Episode, optional if $s is an array
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

	public $query_count = 0;

	private $_nonexistantClassCache = array();

	/**
	 * @param \PDOStatement $stmt Statement to execute
	 *
	 * @return bool|array|object[]
	 */
	protected function _execStatement($stmt){
		$className = $this->tableNameToClassName();
		if (isset($className) && empty($this->_nonexistantClassCache[$className])){
			try {
				if (!class_exists("\\App\\$className"))
					throw new \Exception();

				$this->setClass("\\App\\$className");
			}
			catch (\Exception $e){ $this->_nonexistantClassCache[$className] = true; }
		}

		$this->query_count++;
		return parent::_execStatement($stmt);
	}
}
