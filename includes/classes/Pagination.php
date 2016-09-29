<?php

	/**
	 * Class for writing out complex pagination HTML
	 *  derived from http://codereview.stackexchange.com/a/10292/21877
	 */
	class Pagination {
		public $maxPages, $page, $HTML, $itemsPerPage;
		var $_context, $_wrap, $_basePath;

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
		function __construct($basePath, $ItemsPerPage, $EntryCount, $wrap = true, $context = 2){
			global $data;

			foreach (array('basePath','EntryCount','ItemsPerPage') as $var)
				if (!isset($$var))
					trigger_error("Missing variable \$$var", E_USER_ERROR);

			$this->itemsPerPage = (int) $ItemsPerPage;
			$this->maxPages = (int) max(1, ceil($EntryCount/$this->itemsPerPage));
			$this->page = (int) min(max(intval(regex_replace(new RegExp('^.*\/(\d+)$'),'$1',$data), 10), 1), $this->maxPages);
			$this->_context = $context;
			$this->_wrap = (bool) $wrap;
			$this->_basePath = $basePath;

			$this->HTML = $this->__toString();
		}

		/**
		 * Collect page numbers for pagination
		 *
		 * @return array
		 */
		private function _getLinks(){
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
			if (!($this->page === 1 && $this->maxPages === 1)){
				$Items = array();
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

		/**
		 * Respond to paginated result requests
		 *
		 * @param string $output The HTML of the paginated content
		 * @param string $update The CSS selector specifying which element to place $output in
		 */
		function Respond($output, $update){
			$RQURI = rtrim(regex_replace(new RegExp('js=true(?:&|$)'),'',$_SERVER['REQUEST_URI']),'?');
			Response::Done(array(
				'output' => $output,
				'update' => $update,
				'pagination' => $this->HTML,
				'page' => $this->page,
				'request_uri' => $RQURI,
			));
		}

		/**
		 * Creates the LIMIT array that can be used with PostgresDb's get() method
		 *
		 * @return int[]
		 */
		function GetLimit(){
			return array($this->itemsPerPage*($this->page-1), $this->itemsPerPage);
		}

		/**
		 * Converts GetLimit()'s output to a string
		 *
		 * @return string
		 */
		function GetLimitString(){
			$limit = $this->GetLimit();
			return "LIMIT $limit[1] OFFSET $limit[0]";
		}
	}
