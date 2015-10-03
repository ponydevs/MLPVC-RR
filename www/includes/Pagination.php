<?php

	/**
	 * Class for writing out complex pagination HTML
	 *  derived from http://codereview.stackexchange.com/a/10292/21877
	 */
	class Pagination {
		var $_page, $_maxPages, $_wrap,
			$_basePath, $_context = 2;

		/**
		 * Constructor for setting initial values & outputting result
		 *
		 * @param int $Page        The page number for the current page.
		 * @param int $MaxPages    The total number of paginated pages.
		 * @param string $BasePath Path to use for generated links
		 * @param bool $Wrap       Add starting/closing tags to output
		 */
		public function __construct($Page, $MaxPages, $BasePath, $Wrap = true){
			foreach (array('Page','MaxPages','BasePath') as $var)
				if (!isset($$var))
					trigger_error("Missing variable \$$var", E_USER_ERROR);
			$this->_page = $Page;
			$this->_maxPages = $MaxPages;
			$this->_basePath = $BasePath;
			$this->_wrap = (bool) $Wrap;
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
						max($this->_page - $this->_context, 1),
						min($this->_page + $this->_context, $this->_maxPages)
					),
					range($this->_maxPages - $this->_context, $this->_maxPages)
				)
			);
		}

		/**
		 * Write the pagination links
		 *
		 * @return string
		 */
		public function __toString(){
			$Pagination = $this->_wrap ? "<ul class='pagination'>" : '';
			$links = $this->_getLinks();
			$previousPage = 0;

			if ($this->_page > 1){
				$Pagination .= "<li class='spec'><a href='/{$this->_basePath}/1'>&laquo;</a></li>";
				$prev = $this->_page-1;
				$Pagination .= "<li class='spec'><a href='/{$this->_basePath}/$prev'>&lsaquo;</a></li>";
			}
			foreach ($links as $i) {
				if ($i != min($previousPage + 1, $this->_maxPages))
					$Pagination .= "<li class='spec'><a>&hellip;</a></li>";
				$previousPage = $i;

				$Pagination .= '<li>'.(
					$i != $this->_page
					? "<a href='/{$this->_basePath}/$i'>$i</a>"
					: "<strong>$i</strong>"
				).'</li>';
			}
			if ($this->_page < $this->_maxPages){
				$next = $this->_page+1;
				$Pagination .= "<li class='spec'><a href='/{$this->_basePath}/$next'>&rsaquo;</a></li>";
				$Pagination .= "<li class='spec'><a href='/{$this->_basePath}/{$this->_maxPages}'>&raquo;</a></li>";
			}

			return $Pagination.($this->_wrap ? '</ul>' : '');
		}
	}
