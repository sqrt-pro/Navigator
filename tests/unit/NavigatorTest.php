<?php

use Symfony\Component\HttpFoundation\Request;
use SQRT\Navigator;

class NavigatorTest extends PHPUnit_Framework_TestCase
{
  function testItems()
  {
    $this->fillTable(34);

    $c = new \SQRT\DB\Repository($this->getManager('test_'), 'pages');
    $n = $this->makeNavi('/test/orderby:_id/page:2/id_from:5/id_to:24/');
    $n->setRepository($c);
    $n->addOrderBy('id');
    $n->addFilterBetween('id', 'ID',  'is_numeric');

    $this->assertEquals(20, $n->getTotal(), 'Общее количество элементов');
    $this->assertEquals(1, $n->getTotalPages(), 'Количество страниц без пагинации');

    $n->setOnpage(5);
    $this->assertEquals(4, $n->getTotalPages(), 'Количество страниц с пагинацией');
    $this->assertEquals(2, $n->getPage(), 'Выбрана вторая страница');

    $arr = array();
    $n->each(
      function (\SQRT\DB\Item $item) use (&$arr) {
        $arr[] = $item->get('id');
      }
    );

    $this->assertEquals(range(19, 15), $arr, 'Список элементов в выборке - вторая страница id DESC');
  }

  function testUrl()
  {
    $url = '/wow/id:12/';

    $r = Request::create('http://localhost' . $url);
    $n = new Navigator($r, $this->getManager());

    $this->assertInstanceOf('SQRT\URLImmutable', $n->getUrl(), 'Объект URLImmutable');
    $this->assertEquals($url, $n->getUrl()->asString(), 'Адрес 1');

    $n = new Navigator(Request::create('/'), $this->getManager());
    $n->setUrl($url);
    $this->assertInstanceOf('SQRT\URLImmutable', $n->getUrl(), 'Объект URLImmutable');
    $this->assertEquals($url, $n->getUrl()->asString(), 'Адрес 2');

    $u = new \SQRT\URL($url);
    $n = new Navigator(Request::create('/'), $this->getManager());
    $n->setUrl($u);
    $this->assertInstanceOf('SQRT\URLImmutable', $n->getUrl(), 'Объект URLImmutable');
    $this->assertEquals($url, $n->getUrl()->asString(), 'Адрес 3');
  }

  function testGetParameter()
  {
    $n = $this->makeNavi('/hello/world/id:3/', array('one' => 'two'));

    $this->assertEquals('two', $n->getParameter('one'), 'Получаем параметр из Request');
    $this->assertEquals(3, $n->getParameter('id'), 'Получаем параметр из URL');
    $this->assertFalse($n->getParameter('blabla'), 'Несуществующий параметр');
    $this->assertTrue($n->getParameter('blabla', true), 'Несуществующий параметр и значение по-умолчанию');
  }

  function testCleanUrl()
  {
    $n = $this->makeNavi('/hello/one:two/id:1/id:2/bla:bla/page:3/orderby:name/');

    $this->assertEquals('/hello/', $n->getUrlClean()->asString(), 'В очищенном URL изначально нет лишних параметров');

    $n->addOrderBy('name');

    $this->assertEquals(array('name', true), $n->processOrderColumnFromUrl('name'), 'Прямая сортировка в URL');
    $this->assertEquals(array('name', false), $n->processOrderColumnFromUrl('_name'), 'Обратная сортировка в URL');

    $o = $n->getOrderBy();

    $this->assertNotEmpty($o, 'Значение сортировки получено из адреса');
    $this->assertEquals('/hello/orderby:name/', $o->asUrl()->asString(), 'Адрес значения корректный');
    $this->assertEquals('/hello/orderby:name/', $n->getUrlClean()->asString(), 'Добавили возможность сортировки по name');

    $n = $this->makeNavi('/hello/page:3/orderby:_name/');
    $n->addOrderBy('name');

    $o = $n->getOrderBy();

    $this->assertEquals('/hello/orderby:_name/', $n->getUrlClean()->asString(), 'Сортировка по name по-убыванию');
    $this->assertEquals('/hello/orderby:_name/', $o->asUrl()->asString(), 'Адрес значения по убыванию корректный');

    $n = $this->makeNavi('/hello/');
    $n->setDefaultOrderBy('name', false);
    $n->addFilterEqual('name');

    $this->assertEquals('_name', $n->getOrderBy()->asUrlParameter(), 'Сортировка по-умолчанию есть');
    $this->assertEquals('/hello/', $n->getUrlClean()->asString(), 'Сортировка по-умолчанию не подставляется в URL');

    $n = $this->makeNavi('/hello/name:0/');
    $this->assertEquals('/hello/', $n->getUrlClean()->asString(), 'Параметр не может быть нулем!');

    $n = $this->makeNavi('/hello/age_from:10/age_to:20/ololo:123/');
    $n->addFilterBetween('age');

    $exp = '/hello/age_from:10/age_to:20/';
    $this->assertEquals($exp, $n->getUrlClean()->asString(), 'Чистый урл для Between фильтров');
  }

  function testPagination()
  {
    $n = $this->makeNavi('/hello/page:3/some:value/');

    $this->assertEquals(3, $n->getPage(), 'Открыта страница №3');
    $this->assertEquals('/hello/page:3/', $n->getPageUrl()->asString(), 'Адрес текущей страницы');

    $this->assertEquals(0, $n->getTotal(), 'Количество неизвестно');

    $this->assertFalse($n->getTotalPages(), 'Количество страниц неизвестно');
    $this->assertFalse($n->getLastPage(), 'Неизвестна последняя страница');
    $this->assertFalse($n->getLastPageUrl(), 'Неизвестен адрес последней страницы');

    $this->assertEquals('/hello/page:15/', $n->getPageUrl(15)->asString(), 'Адрес для произвольной страницы');

    $n->setTotal(56);

    $this->assertEquals(1, $n->getTotalPages(), 'Если есть количество без onpage, то всегда будет 1 страница');

    $n->setOnpage(10);
    $this->assertEquals(6, $n->getTotalPages(), 'Количество страниц при 10 штуках на странице');

    $this->assertEquals(2, $n->getPrevPage(), 'Предыдущая страница');
    $this->assertEquals('/hello/page:2/', $n->getPrevPageUrl()->asString(), 'Адрес предыдущей страница');

    $this->assertEquals(4, $n->getNextPage(), 'Следующая страница');
    $this->assertEquals('/hello/page:4/', $n->getNextPageUrl()->asString(), 'Адрес следующей страница');

    $this->assertEquals(1, $n->getFirstPage(), 'Первая страница');
    $this->assertEquals('/hello/page:1/', $n->getFirstPageUrl()->asString(), 'Адрес первой страницы');

    $this->assertEquals(6, $n->getLastPage(), 'Последняя страница');
    $this->assertEquals('/hello/page:6/', $n->getLastPageUrl()->asString(), 'Адрес последней страница');

    $n->setPage(1);
    $this->assertFalse($n->getPrevPage(), 'Нет предыдущей страницы');
    $this->assertFalse($n->getPrevPageUrl(), 'Нет URL для предыдущей страницы');

    $n->setPage(6);
    $this->assertFalse($n->getNextPage(), 'Нет следующей страницы');
    $this->assertFalse($n->getNextPageUrl(), 'Нет URL для следующей страницы');

    $n->addFilterEqual('some');

    $this->assertEquals('/hello/page:2/some:value/', $n->getPageUrl(2)->asString(), 'Фильтры сохраняются при навигации');
  }

  protected function makeNavi($url, $post = array())
  {
    $req = Request::create('http://localhost' . $url, 'POST', $post);

    return new Navigator($req, $this->getManager('test_'));
  }

  protected function setUp()
  {
    $m = $this->getManager();
    $m->query('CREATE TABLE `test_pages` (`id` int(10) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, `name` VARCHAR(250))');
  }

  protected function tearDown()
  {
    $m = $this->getManager();
    $m->query('DROP TABLE IF EXISTS `test_pages`');
  }

  protected function fillTable($limit = 10)
  {
    $m = $this->getManager('test_');

    for ($i = 1; $i <= $limit; $i++) {
      $q = $m->getQueryBuilder()->insert('pages')
        ->setEqual('id', $i)
        ->setEqual('name', 'Item #' . $i);
      $m->query($q);
    }
  }

  protected function getManager($prefix = null, $conn = true)
  {
    $m = new \SQRT\DB\Manager();
    if ($prefix) {
      $m->setPrefix($prefix);
    }
    if ($conn) {
      $m->addConnection(TEST_HOST, TEST_USER, TEST_PASS, TEST_DB);
    }

    return $m;
  }
}