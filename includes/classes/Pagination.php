<?php

namespace App;
use ActiveRecord\SQLBuilder;

/**
 * Class for writing out complex pagination HTML
 *  derived from http://codereview.stackexchange.com/a/10292/21877
 */
class Pagination {
	public $maxPages, $page, $HTML, $itemsPerPage;
	public $_context, $_wrap, $_basePath;

	/**
	 * Creates an instance of the class and return the generated HTML
	 *
	 * @param string $basePath     The starting path of ech paginated page without the page number
	 * @param int    $ItemsPerPage Number of items to display on a single page
	 * @param int    $EntryCount   Number of available entries
	 * @param bool   $wrap         Whether to return the wrapper element
	 * @param int    $context      How many items to show oneither side of current page
	 *
	 * @return Pagination
	 */
	public function __construct(string $basePath, int $ItemsPerPage, ?int $EntryCount = null, bool $wrap = true, $context = 2){
		global $data;

		$this->itemsPerPage = (int) $ItemsPerPage;
		$this->page = (int) max(intval(preg_replace(new RegExp('^.*\/(\d+)$'),'$1',$data), 10), 1);
		$this->_context = $context;
		$this->_wrap = (bool) $wrap;
		$this->_basePath = $basePath;

		if ($EntryCount !== null)
			$this->calcMaxPages($EntryCount);
	}

	public function calcMaxPages(int $EntryCount){
		$this->maxPages = (int) max(1, ceil($EntryCount/$this->itemsPerPage));
		if ($this->page > $this->maxPages)
			$this->page = $this->maxPages;
		$this->HTML = $this->__toString();
	}

	/**
	 * Collect page numbers for pagination
	 *
	 * @return array
	 */
	private function _getLinks(){
		if (!isset($this->maxPages))
			throw new \Exception('$this->maxPages must be defined');

		return array_unique(
			array_merge(
				range(1, 1 + $this->_context),
				range(
					max($this->page - $this->_context, 1),
					min($this->page + $this->_context, $this->maxPages)
				),
				range($this->maxPages - $this->_context, $this->maxPages)
			)
		);
	}

	private function _toLink($i, &$currentIndex = null, $nr = null){
		$current = $i == $this->page;
		if (isset($currentIndex) && $current)
			$currentIndex = $nr;
		return '<li>'.(
			!$current
			? "<a href='/{$this->_basePath}/$i'>$i</a>"
			: "<strong>$i</strong>"
		).'</li>';
	}

	/**
	 * Write the pagination links
	 *
	 * @return string
	 */
	public function __toString(){
		if ($this->maxPages === null){
			error_log(__METHOD__.': $this->maxPages must be defined\nData: '.var_export($this, true));
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
			else foreach ($this->_getLinks() as $i) {
				if ($i != min($previousPage + 1, $this->maxPages)){
					$diff = $i - ($previousPage + 1);
					$Items[$nr++] = $diff > 1 ? "<li class='spec'><a>&hellip;</a></li>" : $this->_toLink($previousPage+1);
				}
				$previousPage = $i;

				$Items[$nr] = $this->_toLink($i, $currentIndex, $nr);
				$nr++;
			}

			$Items = implode('',$Items);
		}
		else $Items = '';

		return $this->_wrap ? "<ul class='pagination'>$Items</ul>" : $Items;
	}

	public function toElastic(){
		return [
			'size' => $this->itemsPerPage,
			'from' => ($this->page-1)*$this->itemsPerPage
		];
	}

	/**
	 * Respond to paginated result requests
	 *
	 * @param string $output The HTML of the paginated content
	 * @param string $update The CSS selector specifying which element to place $output in
	 */
	public function respond($output, $update){
		$RQURI = rtrim(preg_replace(new RegExp('js=true(?:&|$)'),'',$_SERVER['REQUEST_URI']),'?');
		Response::done([
			'output' => $output,
			'update' => $update,
			'pagination' => $this->HTML,
			'page' => $this->page,
			'request_uri' => $RQURI,
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
	public function applyAssocLimit(SQLBuilder &$query){
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
