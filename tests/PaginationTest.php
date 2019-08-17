<?php

use App\Pagination;
use PHPUnit\Framework\TestCase;

class PaginationTest extends TestCase {
  public function testGuessPage() {
    $p = new Pagination('', 3, 30);

    $_GET['page'] = 3;
    $this->assertEquals(3, $p->guessPage(), 'Page within max page count');

    $_GET['page'] = 99999;
    $this->assertEquals(10, $p->guessPage(), 'Page clamped to last page');

    $_GET['page'] = -200;
    $this->assertEquals(1, $p->guessPage(), 'Page clamped to first page');

    unset($_GET['page']);

    $_SERVER['REQUEST_URI'] = '/test/2?noise';
    $p = new Pagination('', 3, 30);
    $this->assertEquals(2, $p->guessPage(), 'Can grab page from the path');

    $_SERVER['REQUEST_URI'] = '/test/test/test/test/test/2?test=test';
    $this->assertEquals(2, $p->guessPage(), 'Can grab page from a complex path');
  }

  public function testCalcMaxPages() {
    $p = new Pagination('', 5);

    $p->calcMaxPages(30);
    $this->assertEquals(6, $p->getMaxPages());

    $p->calcMaxPages(99);
    $this->assertEquals(20, $p->getMaxPages());

    $p->calcMaxPages(0);
    $this->assertEquals(1, $p->getMaxPages(), 'Always have at least one page');

    $p->calcMaxPages(-1);
    $this->assertEquals(1, $p->getMaxPages(), 'Always have at least one page');
  }

  public function testGetLimit() {
    $_GET['page'] = 3;
    $p = new Pagination('', 3, 30);
    $limit = $p->getLimit();
    $this->assertCount(2, $limit);
    $this->assertEquals(6, $limit[0]);
    $this->assertEquals(3, $limit[1]);
  }

  public function testToElastic() {
    $_GET['page'] = 3;
    $p = new Pagination('', 10, 30);
    $elastic = $p->toElastic();
    $this->assertArrayHasKey('from', $elastic);
    $this->assertArrayHasKey('size', $elastic);
    $this->assertEquals(20, $elastic['from']);
    $this->assertEquals(10, $elastic['size']);
  }

  public function testGetAssocLimit() {
    $_GET['page'] = 3;
    $p = new Pagination('', 10, 30);
    $assoc = $p->getAssocLimit();
    $this->assertArrayHasKey('offset', $assoc);
    $this->assertArrayHasKey('limit', $assoc);
    $this->assertEquals(20, $assoc['offset']);
    $this->assertEquals(10, $assoc['limit']);
  }

  public function testGetLimitString() {
    $_GET['page'] = 3;
    $p = new Pagination('', 10, 30);
    $this->assertEquals('LIMIT 10 OFFSET 20', $p->getLimitString());
  }

  public function testToURI() {
    $_GET['page'] = 1;
    $p = new Pagination('/test', 10, 30);
    $this->assertEquals('/test?page=%C2%A7', (string)$p->toURI());
    $this->assertEquals('/test', (string)$p->toURI(false));

    $_GET['page'] = 2;
    $_GET['eppage'] = 3;
    $_GET['moviepage'] = 4;
    $p = new Pagination('/test', 10, 200);
    $this->assertEquals('/test?page=2', (string)$p->toURI());
    $p = new Pagination('/test', 10, 200, 'ep');
    $this->assertEquals('/test?eppage=3', (string)$p->toURI());
    $p = new Pagination('/test', 10, 200, 'movie');
    $this->assertEquals('/test?moviepage=4', (string)$p->toURI());
  }
}
