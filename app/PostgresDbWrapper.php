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

	/**
	 * Sets the output format to use the specified class with late property fetching for php-activerecord
	 * Expects ModelName::class as the name argument, or the equivalent fully qualified model name.
	 *
	 * @param string $className Fully qualified class name
	 *
	 * @return self
	 */
	public function setModel(string $className){
		if (strpos($className, 'App\\') !== 0)
			$className = "App\\Models\\$className";
		if (!class_exists($className))
			throw new \RuntimeException("The model $className does not exist");

		$this->setClass($className, \PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE);

		return $this;
	}

	private $_nonexistantClassCache = [];

	/**
	 * @inheritdoc
	 */
	protected function _execStatement($stmt, $reset = true){
		$className = $this->tableNameToClassName();
		if ($className !== null && empty($this->_nonexistantClassCache[$className])){
			try {
				$this->setModel($className);
			}
			catch (\RuntimeException $e){ $this->_nonexistantClassCache[$className] = true; }
		}

		$execResult = parent::_execStatement($stmt, $reset);
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
