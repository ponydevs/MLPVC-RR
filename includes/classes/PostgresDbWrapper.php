<?php

namespace App;

use ActiveRecord\Model;
use App\Models\Episode;

class PostgresDbWrapper extends \PostgresDb {
	public static function withConnection(string $db, \PDO $PDO):PostgresDbWrapper {
		$instance = new self($db);
		$instance->setConnection($PDO);
		return $instance;
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
		if ($e === null){
			if (!$s instanceof Episode)
				throw new \InvalidArgumentException(__METHOD__.' expects parameter 1 to be an instance of '.Episode::class.' (because parameter 2 is null), '.\gettype($s).' given');
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
		$className = "\\App\\Models\\$name";
		if (!class_exists($className))
			throw new \RuntimeException("The model $className does not exist");

		return $this->setClass($className, \PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE);
	}

	private $_nonexistantClassCache = [];

	/**
	 * @param \PDOStatement $stmt Statement to execute
	 *
	 * @return bool|array|mixed
	 */
	protected function _execStatement($stmt){
		$className = $this->tableNameToClassName();
		if ($className !== null && empty($this->_nonexistantClassCache[$className])){
			try {
				$this->setModel($className);
			}
			catch (\RuntimeException $e){ $this->_nonexistantClassCache[$className] = true; }
		}

		$execResult = parent::_execStatement($stmt);
		$isarray = \is_array($execResult);
		if ($isarray && \count($execResult) > 0)
			$check = $execResult[0];
		else $check = $execResult;

		if ($check instanceof Model){
			/** @var $execResult Model|Model[] */
			if ($isarray){
				foreach ($execResult as $el)
					$el->forceExisting(true);
			}
			else $execResult->forceExisting(true);
		}

		return $execResult;
	}
}
