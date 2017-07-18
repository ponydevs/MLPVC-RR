<?php

namespace App;
use ActiveRecord\SQLBuilder;

/**
 * Class for writing out complex pagination HTML
 *  derived from http://codereview.stackexchange.com/a/10292/21877
 */
class Pagination {
	public $maxPages, $page, $itemsPerPage;
	public $_context, $_wrap, $_basePath;

	/**
	 * Creates an instance of the class and return the generated HTML
	 *
	 * @param string $basePath     The starting path of ech paginated page without the page number
	 * @param int    $ItemsPerPage Number of items to display on a single page
	 * @param int    $EntryCount   Number of available entries
	 * @param int    $context      How many items to show on either side of current page
	 *
	 * @return Pagination
	 */
	public function __construct(string $basePath, int $ItemsPerPage, ?int $EntryCount = null, $context = 2){
		$this->itemsPerPage = $ItemsPerPage;
		$this->_context = $context;
		$this->_basePath = $basePath;
		$this->page = 1;
		$this->guessPage();

		if ($EntryCount !== null)
			$this->calcMaxPages($EntryCount);
	}

	private function guessPage(){
		$path = explode('/',substr(strtok($_SERVER['REQUEST_URI'],'?'), 1));
		// We need at least 2 elements to paginate
		if (empty($path) || count($path) < 2)
			return;

		$lastPart = array_slice($path, -1)[0];
		if (is_numeric($lastPart))
			$this->page = max((int) $lastPart, 1);
	}

	/**
	 * Set a specific page as the currrent
	 *
	 * @param int $page
	 *
	 * @return self
	 */
	public function forcePage(int $page){
		$this->page = max((int) $page, 1);

		return $this;
	}

	/**
	 * Calculate the number of maximum possible pages
	 *
	 * @param int $EntryCount
	 *
	 * @return self
	 */
	public function calcMaxPages(int $EntryCount){
		$this->maxPages = (int) max(1, ceil($EntryCount/$this->itemsPerPage));
		if ($this->page > $this->maxPages)
			$this->page = $this->maxPages;

		return $this;
	}

	/**
	 * Collect page numbers for pagination
	 *
	 * @return array
	 * @throws \RuntimeException
	 */
	private function _getLinks(){
		if ($this->maxPages === null)
			throw new \RuntimeException('$this->maxPages must be defined');

		return array_unique(
			array_merge(
				[1],
				range(
					max($this->page - $this->_context, 1),
					min($this->page + $this->_context, $this->maxPages)
				),
				[$this->maxPages]
			)
		);
	}

	private function _toLink(int $i, &$currentIndex = null, $nr = null){
		$current = $i === (int) $this->page;
		if ($currentIndex !== null && $current)
			$currentIndex = $nr;
		return '<li>'.(
			!$current
			? "<a href='/{$this->_basePath}/$i'>$i</a>"
			: "<strong>$i</strong>"
		).'</li>';
	}

	public function toHTML(bool $wrap = WRAP):string {
		if ($this->maxPages === null){
			error_log(__METHOD__.": maxPages peroperty must be defined\nData: ".var_export($this, true)."\nTrace:\n".(new \RuntimeException())->getTraceAsString());
			return '';
		}

		if (!($this->page === 1 && $this->maxPages === 1)){
			$Items = [];
			$previousPage = 0;
			$nr = 0;
			$currentIndex = 0;

			if ($this->maxPages < 7){
				for ($i = 1; $i <= $this->maxPages; $i++){
					$Items[$nr] = $this->_toLink($i, $currentIndex, $nr++);
					$nr++;
				}
			}
			else {
				/** @noinspection MagicMethodsValidityInspection */
				foreach ($this->_getLinks() as $i) {
					if ($i !== min($previousPage + 1, $this->maxPages)){
						$diff = $i - ($previousPage + 1);
						$Items[$nr++] = $diff > 1 ? "<li class='spec'><a>&hellip;</a></li>" : $this->_toLink($previousPage+1);
					}
					$previousPage = $i;

					$Items[$nr] = $this->_toLink($i, $currentIndex, $nr);
					$nr++;
				}
			}

			$Items = implode('',$Items);
		}
		else $Items = '';

		$path = CoreUtils::aposEncode($this->_basePath);

		return $wrap ? "<ul class='pagination' data-for='$path'>$Items</ul>" : $Items;
	}

	/**
	 * Write the pagination links
	 *
	 * @return string
	 */
	public function __toString(){
		return $this->toHTML();
	}

	public function toElastic(){
		return [
			'size' => $this->itemsPerPage,
			'from' => ($this->page-1)*$this->itemsPerPage
		];
	}

	/**
	 * Calls respond if needed based on the paginate GET parameter
	 *
	 * @param string $output
	 * @param string $update
	 *
	 * @see _respond
	 */
	public function respondIfShould(string $output, string $update){
		if (isset($_GET['paginate'])){
			$_SERVER['REQUEST_URI'] = rtrim(preg_replace(new RegExp('paginate=true(?:&|$)'),'',$_SERVER['REQUEST_URI']),'?');
			$this->_respond($output, $update);
		}
	}

	/**
	 * Respond to paginated result requests
	 *
	 * @param string $output The HTML of the paginated content
	 * @param string $update The CSS selector telling the JS which element to place $output in
	 */
	private function _respond(string $output, string $update){
		CoreUtils::detectUnexpectedJSON();

		Response::done([
			'output' => $output,
			'update' => $update,
			'for' => $this->_basePath,
			'pagination' => $this->toHTML(NOWRAP),
			'page' => $this->page,
			'request_uri' => $_SERVER['REQUEST_URI'],
		]);
	}

	/**
	 * Creates the LIMIT array that can be used with PostgresDb's get() method
	 *
	 * @return int[]
	 */
	public function getLimit(){
		$arr = $this->toElastic();
		return [$arr['from'], $arr['size']];
	}

	/**
	 * Creates the associative array that can be used ActiveRecord's find() method
	 *
	 * @return array
	 */
	public function getAssocLimit(){
		$arr = $this->toElastic();
		return [ 'offset' => $arr['from'], 'limit' => $arr['size'] ];
	}

	/**
	 * Apply the limit and offset attributes on an SQLBuilder
	 *
	 * @param SQLBuilder $query
	 */
	public function applyAssocLimit(SQLBuilder $query){
		$assoc = $this->getAssocLimit();
		foreach ($assoc as $k => $v)
			$query->{$k} = $v;
	}

	/**
	 * Converts GetLimit()'s output to a string
	 *
	 * @return string
	 */
	public function getLimitString(){
		$limit = $this->getLimit();
		return "LIMIT $limit[1] OFFSET $limit[0]";
	}
}
