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

		private function _toLink($i, &$currentIndex = null, $nr = null){
			$current = $i == $this->_page;
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
			if (!($this->_page === 1 && $this->_maxPages === 1)){
				$Items = array();
				$previousPage = 0;
				$nr = 0;
				$currentIndex = 0;

				if ($this->_maxPages < 7){
					for ($i = 1; $i <= $this->_maxPages; $i++){
						$Items[$nr] = $this->_toLink($i, $currentIndex, $nr++);
						$nr++;
					}
				}
				else foreach ($this->_getLinks() as $i) {
					if ($i != min($previousPage + 1, $this->_maxPages)){
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
	}
