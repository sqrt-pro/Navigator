<?php

require_once __DIR__ . '/../init.php';

use SQRT\URL;
use SQRT\Navigator;
use SQRT\DB\Manager;
use SQRT\Navigator\OrderBy;
use Symfony\Component\HttpFoundation\Request;

class orderbyTest extends PHPUnit_Framework_TestCase
{
  function testOrderByItem()
  {
    $o = new OrderBy();
    $o->setColumn('name');

    $this->assertEquals('/orderby:name/', $o->asUrl()->asString(), 'Адрес для сортировки без указанного URL');

    $o->setUrl(new URL('test', 'me', array('orderby' => 'id', 'id' => 2)));

    $this->assertEquals('/test/me/id:2/orderby:name/', $o->asUrl()->asString(), 'Адрес для сортировки с указанным URL');

    $this->assertEquals('`name` ASC', $o->asSQL(), 'Сортировка по имени колонки по-возрастанию');

    $this->assertEquals('name', $o->asUrlParameter(), 'Сортировка по-возрастанию');
    $this->assertEquals('_name', $o->asUrlParameter(false), 'Сортировка по-убыванию указанная явно');

    $o->setAsc(false);
    $this->assertEquals('_name', $o->asUrlParameter(), 'Сортировка по-убыванию указанная через свойство объекта');
    $this->assertEquals('`name` DESC', $o->asSQL(), 'Сортировка по имени колонки по-убыванию');

    $o->setSql('count(*)');
    $this->assertEquals('count(*) DESC', $o->asSQL(), 'Сортировка по указанному условию SQL по-убыванию');
  }

  function testOrderBy()
  {
    $n = new Navigator(Request::create('/hello/'), new Manager());

    $this->assertFalse($n->getOrderByOptions(), 'Опции для сортировки еще не указаны');

    $n->addOrderBy('id', 'ID');
    $n->addOrderBy('name')
      ->setTmplAscName('%s вверх')
      ->setTmplDescName('%s вниз');
    $n->addOrderBy('count', 'Total', 'count(*)');

    $this->assertCount(3, $n->getOrderByOptions(), 'Всего три опции для сортировки');
    $this->assertArrayHasKey('id', $n->getOrderByOptions(), 'Ключи массива - имена столбцов');


    $o = $n->getOrderByOption('name');
    $this->assertEquals('Name вверх', $o->getAscName(), 'Шаблон для имени сортировки вверх');
    $this->assertEquals('Name вниз', $o->getDescName(), 'Шаблон для имени сортировки вниз');

    $exp = '<select name="orderby"><option value="id">ID по-возрастанию</option>' . "\n"
      . '<option value="_id">ID по-убыванию</option>' . "\n"
      . '<option value="name">Name вверх</option>' . "\n"
      . '<option value="_name">Name вниз</option>' . "\n"
      . '<option value="count">Total по-возрастанию</option>' . "\n"
      . '<option value="_count">Total по-убыванию</option>' . "\n"
      . '</select>';

    $this->assertEquals($exp, $n->getOrderByAsSelect()->toHTML(), 'Генерация SELECT');
    $this->assertFalse($n->checkOrderByOptionExists('blabla'), 'Опция не существует');

    try {
      $n->getOrderByOption('blabla');

      $this->fail('Выбрасывается исключение на отсутствующий выбор');
    } catch (Navigator\Exception $e) {
      $this->assertEquals(Navigator\Exception::NO_ORDERBY_OPTION, $e->getCode(), 'Проверяем код ошибки');
    }

    $this->assertTrue($n->getOrderByOption('name') instanceof OrderBy, 'Получаем объект сортировки');
    $this->assertFalse($n->getOrderBy(), 'В адресе порядок сортировки не выбран');

    $n->setDefaultOrderBy('name', false);

    $this->assertNotEmpty($n->getDefaultOrderBy(), 'Прямое обращение к сортировке по-умолчанию');
    $this->assertNotEmpty($n->getOrderBy(), 'Обращение к текущей сортировке');
    $this->assertEquals('`name` DESC', $n->getOrderBy()->asSQL(), 'Сортировка по-умолчанию, как SQL');

    $n->setOrderBy('count', false);

    $this->assertEquals('count(*) DESC', $n->getOrderBy()->asSQL(), 'Явно указанная сортировка, как SQL');

    $s = $n->getOrderBy()->asUrl()->asString();
    $this->assertEquals('/hello/orderby:_count/', $s, 'Адрес для сортировки без лишних параметров');

    $ob = $n->addOrderBy('ololo');
    $this->assertEquals('`ololo` ASC', $ob->setAsc(true)->asSQL(), 'Сортировка в прямом порядке по возрастанию');
    $this->assertEquals('`ololo` DESC', $ob->setAsc(false)->asSQL(), 'Сортировка в прямом порядке по убыванию');

    $ob->setInverse(true);
    $this->assertEquals('`ololo` DESC', $ob->setAsc(true)->asSQL(), 'Сортировка в обратном порядке по возрастанию');
    $this->assertEquals('`ololo` ASC', $ob->setAsc(false)->asSQL(), 'Сортировка в обратном порядке по убыванию');
  }

  function testOrderByFromURL()
  {
    $n = new Navigator(Request::create('http://localhost/hello/orderby:_name/'), new Manager());
    $n->addOrderBy('id', 'ID');
    $n->addOrderBy('name');

    $o = $n->getOrderBy(true);
    $this->assertInstanceOf('SQRT\Navigator\OrderBy', $o);
    $this->assertEquals('`name` DESC', $o->asSQL(), 'Сортировка получена из URL');
  }
}