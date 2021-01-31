<?php

namespace App;

use ActiveRecord\SQLBuilder;
use HtmlGenerator\HtmlTag;
use League\Uri\Components\Query;
use League\Uri\Uri;
use League\Uri\UriModifier;
use RuntimeException;

/**
 * Class for writing out complex pagination HTML
 *  derived from http://codereview.stackexchange.com/a/10292/21877
 */
class Pagination {
  /** @var int|null */
  private $max_pages;
  /** @var int */
  private $items_per_page;
  /** @var int */
  private $context = 2;
  /** @var string */
  private $base_path;
  /** @var int */
  private $page = 1;
  /** @var string */
  private $query_prefix;
  /** @var int */
  private $entry_count;

  /**
   * @param string $base_path      The starting path of each paginated page
   * @param int    $items_per_page Number of items to display on a single page
   * @param int    $entry_count    Number of available entries
   * @param string $query_prefix   Specify a name to use for the query string parameter
   */
  public function __construct(string $base_path, int $items_per_page, ?int $entry_count = null, string $query_prefix = '') {
    $this->items_per_page = $items_per_page;
    $this->base_path = $base_path;
    $this->query_prefix = $query_prefix;
    $this->entry_count = $entry_count;
    $this->guessPage();

    if ($this->entry_count !== null)
      $this->calcMaxPages();
  }

  public function guessPage() {
    $page = $_GET["{$this->query_prefix}page"] ?? null;
    if ($page === null && !empty($_SERVER['REQUEST_URI'])){
      $uri = explode('/', strtok($_SERVER['REQUEST_URI'], '?'));
      $page = array_pop($uri);
    }
    if (is_numeric($page)){
      $page_int = (int)$page;
      if ($this->max_pages !== null)
        $page_int = min($page_int, $this->max_pages);
      $this->page = max($page_int, 1);
    }

    return $this->page;
  }

  /**
   * Set a specific page as the current
   *
   * @param int $page
   *
   * @return self
   */
  public function forcePage(int $page) {
    $this->page = max($page, 1);

    return $this;
  }

  /**
   * Calculate the number of maximum possible pages
   *
   * @param int|null $entry_count
   *
   * @return self
   */
  public function calcMaxPages(?int $entry_count = null) {
    if ($entry_count !== null){
      $this->entry_count = $entry_count;
    }
    $this->max_pages = (int)max(1, ceil($this->entry_count / $this->items_per_page));
    if ($this->page > $this->max_pages)
      $this->page = $this->max_pages;

    return $this;
  }

  /**
   * Collect page numbers for pagination
   *
   * @return array
   * @throws RuntimeException
   */
  private function _getLinks() {
    if ($this->max_pages === null)
      throw new RuntimeException('$this->maxPages must be defined');

    return array_unique(
      array_merge(
        [1],
        range(
          max($this->page - $this->context, 1),
          min($this->page + $this->context, $this->max_pages)
        ),
        [$this->max_pages]
      )
    );
  }

  private function _makeLink($i): string {
    $href = $this->toURI(false, $i);
    $get = $_GET;
    if (isset($get["{$this->query_prefix}page"]))
      unset($get["{$this->query_prefix}page"]);

    $query = Query::createFromParams($get);

    return (string) UriModifier::appendQuery($href, $query);
  }

  private function _makeItem(int $i, &$currentIndex = null, $nr = null) {
    $current = $i === (int)$this->page;
    if ($currentIndex !== null && $current)
      $currentIndex = $nr;

    /** @noinspection NullPointerExceptionInspection */
    return '<li>'.(
      !$current
        ? HtmlTag::createElement('a')->set('href', $this->_makeLink($i))->text($i)
        : "<strong>$i</strong>"
      ).'</li>';
  }

  public function toHTML(bool $wrap = WRAP):string {
    if ($this->max_pages === null){
      CoreUtils::logError(__METHOD__.": maxPages peroperty must be defined\nData: ".var_export($this, true)."\nTrace:\n".(new RuntimeException())->getTraceAsString());

      return '';
    }

    if (!($this->page === 1 && $this->max_pages === 1)){
      $Items = [];
      $previousPage = 0;
      $currentIndex = 0;

      if ($this->max_pages < 7){
        for ($i = 1; $i <= $this->max_pages; $i++){
          $Items[] = $this->_makeItem($i, $currentIndex, count($Items));
        }
      }
      else {
        foreach ($this->_getLinks() as $i){
          if ($i !== min($previousPage + 1, $this->max_pages)){
            $diff = $i - ($previousPage + 1);
            if ($diff > 1){
              /** @noinspection NullPointerExceptionInspection */
              $item = HtmlTag::createElement('li')->set('class', 'spec');
              $item->addElement('a')->text("\u{2026}");
              $item->attr('data-baseurl', $this->_makeLink('*'));
            }
            else $item = $this->_makeItem($previousPage + 1);
            $Items[] = $item;
          }
          $previousPage = $i;

          $Items[] = $this->_makeItem($i, $currentIndex, count($Items));
        }
      }

      $Items = implode('', $Items);
    }
    else $Items = '';

    $path = CoreUtils::aposEncode($this->base_path);

    return $wrap ? "<ul class='pagination' data-for='$path'>$Items</ul>" : $Items;
  }

  /**
   * Write the pagination links
   *
   * @return string
   */
  public function __toString() {
    return $this->toHTML();
  }

  public function toElastic():array {
    $limit = $this->getLimit();

    return [
      'from' => $limit[0],
      'size' => $limit[1],
    ];
  }

  /**
   * Creates the LIMIT array that can be used with PostgresDb's get() method
   *
   * @return int[] Array in the format [offset, limit]
   */
  public function getLimit():array {
    return [($this->page - 1) * $this->items_per_page, $this->items_per_page];
  }

  /**
   * Creates the associative array that can be used ActiveRecord's find() method
   *
   * @return array
   */
  public function getAssocLimit():array {
    $limit = $this->getLimit();

    return ['offset' => $limit[0], 'limit' => $limit[1]];
  }

  /**
   * Apply the limit and offset attributes on an SQLBuilder
   *
   * @param SQLBuilder $query
   */
  public function applyAssocLimit(SQLBuilder $query) {
    $assoc = $this->getAssocLimit();
    foreach ($assoc as $k => $v)
      $query->{$k} = $v;
  }

  /**
   * Converts GetLimit()'s output to a string
   *
   * @return string
   */
  public function getLimitString():string {
    $limit = $this->getLimit();

    return "LIMIT $limit[1] OFFSET $limit[0]";
  }

  /**
   * Returns the raw query string parameter for the page
   *
   * @param string|int|null $page         Page number, '*', or current page if not specified
   * @param bool     $force_fixpath_empty Force an "empty" value so fixPath knows to remove the parameter
   *
   * @return string
   */
  public function getPageQueryString($page = null, bool $force_fixpath_empty = true):string {
    $page_number = $page ?? $this->page;
    if ($page_number === 1){
      if (!$force_fixpath_empty)
        return '';
      $page_number = '§';
    }

    return "{$this->query_prefix}page=$page_number";
  }

  public function toURI(bool $force_fixpath_empty = true, $force_page = null): Uri {
    $query_string = $this->getPageQueryString($force_page, $force_fixpath_empty);
    $url = Uri::createFromString($this->base_path);

    if ($query_string !== null)
      $url = UriModifier::appendQuery($url, $query_string);

    return $url;
  }

  public function getPage():?int {
    return $this->page;
  }

  public function getItemsPerPage():?int {
    return $this->items_per_page;
  }

  public function getMaxPages():?int {
    return $this->max_pages;
  }

  public function getEntryCount():?int {
    return $this->entry_count;
  }
}
