<?php

use SQRT\Navigator;
use SQRT\DB\Manager;
use SQRT\Navigator\Filter;
use Symfony\Component\HttpFoundation\Request;

class FiltersTest extends PHPUnit_Framework_TestCase
{
  function testName()
  {
    $n = new Navigator(Request::create('/hello/'), new Manager());
    $f = new Filter($n, 'is_active');

    $this->assertEquals('Is active', $f->getName(), 'Имя генерится из названия столбца');

    $f->setName('Вкл');
    $this->assertEquals('Вкл', $f->getName(), 'Имя задано явно');

    $f = new Filter($n, 'is_active', 'Вкл');
    $this->assertEquals('Вкл', $f->getName(), 'Имя задано в конструкторе');
  }

  function testValidators()
  {
    $n = new Navigator(Request::create('http://localhost/test:1/'), new Manager());

    $alnum = 'abc123';
    $f = new Filter($n, 'test');
    $this->assertTrue($f->validate($alnum), 'Валидатор не задан');

    $f->setValidator('is_numeric');
    $this->assertEquals(1, $f->getCleanValue(), 'Корректное значение из URL после валидации');

    $this->assertFalse($f->validate($alnum), 'Только числа');
    $this->assertTrue($f->validate(123), 'Цифры можно');

    $f->setValidator('/^[a-z]+$/');
    $this->assertFalse($f->getCleanValue(), 'Некорректное значение не передается');

    $this->assertFalse($f->validate($alnum), 'Только буквы');
    $this->assertTrue($f->validate('abc'), 'Буквы можно');

    $f->setValidator(false);

    $f->setOptions(array(1 => 'one', 2 => 'two'));
    $this->assertTrue($f->validate(1), 'Верное значение - ключ массива');
    $this->assertFalse($f->validate('one'), 'Нет такого ключа в массиве');
    $this->assertFalse($f->validate(false), 'Отсутствующий параметр');

    $this->assertTrue($f->validate(array(1, 2)), 'Все значения массива есть среди допустимых');
    $this->assertFalse($f->validate(array(1, 3)), 'Один из элементов вне диапазона');

    $f->validate(1);

    $exp = '<select name="test">'
      . '<option selected="selected" value="1">one</option>' . "\n"
      . '<option value="2">two</option>' . "\n"
      . '</select>';
    $this->assertEquals($exp, $f->asSelect()->toHTML(), 'Генерация списка SELECT');

    $exp = '<input name="test" type="text" value="1" />';
    $this->assertEquals($exp, $f->asInput()->toHTML(), 'Фильтр как поле для ввода со значением по-умолчанию');

    $f->setValidator('/^[a-z]+$/');
    $exp = '<input name="test" type="text" value="" />';
    $this->assertEquals($exp, $f->asInput()->toHTML(), 'Значение не прошедшее валидацию не подставляется');

    $exp = '<input class="hello" name="test" type="text" value="" />';
    $this->assertEquals($exp, $f->asInput('hello')->toHTML(), 'Можно указать произвольные аттрибуты');

    $f->setOptions(array('one', 'two'), true);
    $this->assertTrue($f->validate('one'), 'Верное значение - значение массива');
    $this->assertFalse($f->validate('three'), 'Нет такого значения в массиве');
  }

  /** @dataProvider dataFilters() */
  function testFilters($url, $exp, $msg)
  {
    $n = new Navigator(Request::create('http://localhost' . $url), new Manager());
    $n->conditions(true)->expr('`id` > 7');
    $n->addFilterEqual('name', 'Имя', '/^[a-z]+$/');
    $n->addFilterLike('addr');
    $n->addFilterBetween('id', 'ID', 'is_numeric')
      ->setGreaterOrEqual(false); // Возвращается фильтр и его можно донастроить
    $n->addFilter(
      'date',
      'Дата',
      function (Navigator $navi, Filter $filter) {
        if ($val = $filter->getCleanValue()) {
          $navi->conditions()->greaterOrEqual('created_at', date('d.m.Y', strtotime('+' . $val . ' day')));
        }
      },
      'is_numeric'
    );

    $this->assertEquals($exp, $n->processFilters()->asSQL(), $msg);
  }

  function dataFilters()
  {
    return array(
      array('/hello/', '`id` > 7', 'Фильтры не указаны - условие по-умолчанию'),
      array('/hello/some:value/', '`id` > 7', 'Указаны не используемые параметры - условие по-умолчанию'),
      array('/hello/name:abc/', '`id` > 7 AND `name`="abc"', 'Условие Equal по-умолчанию'),
      array(
        '/hello/name:123/id_from:10/id_to:20/',
        '`id` > 7 AND `id`>10 AND `id`<=20',
        'Значение name не прошло фильтр, ID фильтруется с двух сторон'
      ),
      array('/hello/id_from:abc/id_to:10/', '`id` > 7 AND `id`<=10', 'ID фильтруется с одной стороны'),
      array(
        '/hello/date:1/',
        '`id` > 7 AND `created_at`>="' . date('d.m.Y', strtotime('+1 day')) . '"',
        'Пользовательский фильтр на дату'
      ),
      array('/hello/addr:при/', '`id` > 7 AND `addr` LIKE "при%"', 'Фильтр Like')
    );
  }

  function testFilterItem()
  {
    $n = new Navigator(Request::create('http://localhost/some/'), new Manager());

    $this->assertFalse($n->getFilters(), 'Фильтры не заданы');
    $this->assertFalse($n->checkFilterExists('name'), 'Фильтра name еще нет');

    try {
      $n->getFilter('name');
      $this->fail('Попытка получения фильтра, которого еще нет');
    } catch (Exception $e) {
      $this->assertEquals(Navigator\Exception::FILTER_NOT_EXISTS, $e->getCode(), 'Верный код ошибки');
    }

    $eq  = $n->addFilterEqual('name');
    $btw = $n->addFilterBetween('date');
    $lk  = $n->addFilterLike('addr');

    $f = $n->getFilter('name');
    $this->assertTrue($f instanceof Filter, 'Фильтр является объектом Filter');
    $this->assertEquals('name', $f->getColumn(), 'Фильтр получен верно');

    $this->assertEquals('/some/name:john/', $f->asUrl('john')->asString(), 'Адрес фильтра верный');

    // Equal
    $this->assertEquals('name', $eq->getField(), 'По-умолчанию поле SQL равно имени параметра');
    $eq->setField('fullname');
    $this->assertEquals('fullname', $eq->getField(), 'Явно заданное поле SQL');

    // Between
    $this->assertEquals('date_from', $btw->getColumnFrom(), 'Параметр от');
    $this->assertEquals('date_to', $btw->getColumnTo(), 'Параметр до');

    // Like
    $this->assertEquals('оЛолё%ло%123', $lk->cleanValue('оЛолё"""   ло 123'), 'Очистка значения по-умолчанию');
    $lk->setRegularCleaner('/[^а-я]+/u');
    $this->assertEquals('оло%ло', $lk->cleanValue(' оло"""   ло 123 '), 'Очистка значения по своей регулярке');
  }

  function testFilterBetween()
  {
    $n = new Navigator(Request::create('http://localhost/some/age_from:today/age_to:20/'), new Manager());
    $f = $n->addFilterBetween('age');

    $this->assertEquals('today', $f->getCleanValueFrom(), 'Значение от');
    $this->assertEquals(20, $f->getCleanValueTo(), 'Значение до');

    $f->setValidator('/^[a-z]+$/');

    $this->assertEquals('today', $f->getCleanValueFrom(), 'Значение от проходит валидацию');
    $this->assertFalse($f->getCleanValueTo(), 'Значение от не проходит валидацию');

    $exp = '<input class="hello" name="age_from" type="text" value="today" />';
    $this->assertEquals($exp, $f->asInputFrom('hello'), 'Рендер инпута от');

    $exp = '<input id="123" name="age_to" type="text" value="" />';
    $this->assertEquals($exp, $f->asInputTo(array('id' => 123)), 'Рендер инпута до');
  }

  function testFilterBetweenDate()
  {
    $n = new Navigator(Request::create('http://localhost/hello/date_from:18.01.2014/date_to:20.01.2014/'), new Manager());
    $f = $n->addFilterBetween('date');

    $from = '`date`>=12345';
    $this->assertEquals($from, $f->prepareFromCondition(null, '12345')->asSQL(), 'Подстановка своего значения в выражение от');

    $from = '`date`>="18.01.2014"';
    $this->assertEquals($from, $f->prepareFromCondition()->asSQL(), 'Подготовленное выражение от');

    $to = '`date`<=12345';
    $this->assertEquals($to, $f->prepareToCondition(null, '12345')->asSQL(), 'Подстановка своего значения в выражение до');

    $to = '`date`<="20.01.2014"';
    $this->assertEquals($to, $f->prepareToCondition()->asSQL(), 'Подготовленное выражение до');

    $exp = $from . ' AND ' . $to;
    $this->assertEquals($exp, $n->processFilters()->asSQL(), 'По-умолчанию данные подставляются напрямую');

    $f->setIsDate(true);
    $exp = '`date`>="2014-01-18 00:00" AND `date`<="2014-01-20 23:59:59"';
    $this->assertEquals($exp, $n->processFilters()->asSQL(), 'Обработка диапазона дат');

    $f->setField('`some`.`date`');

    $exp = '`some`.`date`>="2014-01-18 00:00"';
    $this->assertEquals($exp, $f->prepareFromCondition()->asSQL(), 'Закавычивание колонки от');

    $exp = '`some`.`date`<="2014-01-20 23:59:59"';
    $this->assertEquals($exp, $f->prepareToCondition()->asSQL(), 'Закавычивание колонки до');
  }

  function testBetweenCallable()
  {
    $n = new Navigator(Request::create('http://localhost/hello/age_from:18/'), new Manager());
    $f = $n->addFilterBetween('age');

    $this->assertEquals('`age`>=18', $n->processFilters()->asSQL(), 'Фильтры по-умолчанию');

    $f->setCallable(
      function (Navigator $navi, Filter\Between $f) {
        $navi->conditions()->equal('two', $f->getCleanValueFrom());
      }
    );
    $exp = '`two`=18';
    $this->assertEquals($exp, $n->processFilters()->asSQL(), 'Свой фильтр');
  }

  function testLikeCallable()
  {
    $n = new Navigator(Request::create('http://localhost/hello/name:ололо/'), new Manager());
    $f = $n->addFilterLike('name');

    $this->assertEquals('`name` LIKE "ололо%"', $n->processFilters()->asSQL(), 'Фильтры по-умолчанию');

    $f->setCallable(
      function (Navigator $navi, Filter\Like $f) {
        $navi->conditions()->like('name', 'пыщпыщ%');
      }
    );
    $exp = '`name` LIKE "пыщпыщ%"';
    $this->assertEquals($exp, $n->processFilters()->asSQL(), 'Свой фильтр');
  }
}