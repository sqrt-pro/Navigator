<?php

namespace SQRT;

use SQRT\DB\Collection;
use SQRT\DB\Item;
use SQRT\Navigator\Exception;
use SQRT\Tag\Select;
use SQRT\DB\Manager;
use SQRT\Navigator\OrderBy;
use Stringy\StaticStringy;
use Symfony\Component\HttpFoundation\Request;
use SQRT\Navigator\Filter\Equal;
use SQRT\Navigator\Filter\Between;
use SQRT\Navigator\Filter\Like;
use SQRT\Navigator\Filter;
use SQRT\QueryBuilder\Conditions;
use SQRT\QueryBuilder\Condition;

class Navigator
{
  /** @var Request */
  protected $request;
  /** @var Manager */
  protected $manager;
  /** @var URLImmutable */
  protected $url;

  /** @var Collection */
  protected $collection;

  /** Количество элементов на странице */
  protected $onpage;

  /** Количество элементов всего */
  protected $total;

  /** Варианты сортировки */
  protected $orderby_options;

  /** Поле сортировки по-умолчанию */
  protected $default_orderby;
  protected $default_orderby_asc;

  /** Текущее значение сортировки */
  protected $orderby;
  protected $orderby_asc;

  /** @var Filter[] */
  protected $filters;

  /**
   * Условия от фильтров
   * @var Conditions
   */
  protected $conditions;

  /**
   * Предзаданные условия для выборки
   * @var Conditions
   */
  protected $default_conditions;

  /** Флаг, что выборка по запросу отсутствует */
  protected $searchable = true;

  function __construct(Request $request, Manager $manager, $collection = null)
  {
    $this->request = $request;
    $this->manager = $manager;
    $this->url     = new URLImmutable($request->getUri());

    if ($collection) {
      $this->setCollection($collection);
    }

    $this->init();
  }

  /** @return Collection|Item[] */
  public function getItems()
  {
    $c = $this->getCollection()->setItems(null);

    if ($this->isSearchable()) {
      $c->find(
        $this->processFilters(),
        $this->processOrderBy(),
        $this->getOnpage(),
        $this->getPage()
      );
    }

    return $c;
  }

  /** Применение $callable ко всем результатам выборки */
  public function each($callable)
  {
    return $this->getItems()->map($callable);
  }

  /**
   * Функция подсчета количества элементов.
   * Если указан класс Item то работает автоматически от фильтра
   * Может быть настроено имя функции через setFuncCount или переопределено при наследовании
   */
  protected function countTotal()
  {
    $this->total = null;

    if (!$c = $this->getCollection()) {
      $this->setSearchable(false);

      return false;
    }

    if (!$this->total = $c->countQuery($this->processFilters())) {
      $this->setSearchable(false);

      return false;
    }

    return $this->total;
  }

  /** @return Filter[] */
  public function getFilters()
  {
    return $this->filters ? : false;
  }

  public function checkFilterExists($col)
  {
    return isset($this->filters[$col]);
  }

  /** @return Filter|Equal|Between|Like */
  public function getFilter($col)
  {
    if (!$this->checkFilterExists($col)) {
      Exception::ThrowError(Exception::FILTER_NOT_EXISTS, $col);
    }

    return $this->filters[$col];
  }

  /**
   * В $callable фильтра при вызове передаются аргументы Navigator, Filter
   *
   * @param $col
   * @param null $name
   * @param null $callable
   * @param null $validator
   * @return Filter
   */
  public function addFilter($col, $name = null, $callable = null, $validator = null)
  {
    return $this->filters[$col] = new Filter($this, $col, $name, $validator, $callable);
  }

  /**
   * Фильтр значение равно
   *
   * @return Equal
   */
  public function addFilterEqual($col, $name = null, $validator = null)
  {
    return $this->filters[$col] = new Equal($this, $col, null, $validator);
  }

  /**
   * Фильтр значение между
   *
   * @return Between
   */
  public function addFilterBetween($col, $name = null, $validator = null)
  {
    return $this->filters[$col] = new Between($this, $col, null, $validator);
  }

  /**
   * Фильтр значение LIKE
   *
   * @return Like
   */
  public function addFilterLike($col, $name = null, $validator = null)
  {
    return $this->filters[$col] = new Like($this, $col, null, $validator);
  }

  /**
   * $default - зафиксировать условия по-умолчанию
   * @return Conditions
   */
  public function conditions($default = false)
  {
    if ($default) {
      if (is_null($this->default_conditions)) {
        $this->default_conditions = new Conditions();
      }

      return $this->default_conditions;
    } else {
      if (is_null($this->conditions)) {
        $this->conditions = new Conditions();
      }

      return $this->conditions;
    }
  }

  /**
   * Получение условия для выборки
   * @return Conditions
   */
  public function processFilters()
  {
    $this->conditions = clone $this->conditions(true);

    if ($this->filters) {
      foreach ($this->filters as $f) {
        $f->process($this);
      }
    }

    return $this->conditions();
  }

  /** Получение SQL для сортировки */
  public function processOrderBy()
  {
    if ($o = $this->getOrderBy()) {
      return $o->asSQL();
    }

    return null;
  }

  /**
   * Добавление возможности сортировки по колонке
   *
   * @return OrderBy
   */
  public function addOrderBy($column, $name = null, $sql = null)
  {
    return $this->orderby_options[$column] = new OrderBy($column, $name, $sql, $this);
  }

  /**
   * Текущее значение сортировки
   *
   * @return OrderBy
   */
  public function getOrderBy($no_default = false)
  {
    $this->process();

    if (!$this->orderby) {
      return $no_default ? false : $this->getDefaultOrderBy();
    }

    return $this
      ->getOrderByOption($this->orderby)
      ->setAsc($this->orderby_asc);
  }

  /**
   * Список возможных вариантов сортировки
   *
   * @return OrderBy[]
   */
  public function getOrderByOptions()
  {
    return $this->orderby_options ? : false;
  }

  /** Список возможных вариантов сортировки в виде списка для SELECT: col asc => name, col desc => name */
  public function getOrderByOptionsForSelect()
  {
    if (!$arr = $this->getOrderByOptions()) {
      return false;
    }

    $out = array();
    foreach ($arr as $o) {
      $out[$o->asUrlParameter(true)] = $o->getAscName();
      $out[$o->asUrlParameter(false)] = $o->getDescName();
    }

    return $out;
  }

  /**
   * Варианты выбора сортировки в виде SELECT
   *
   * @return Select
   */
  public function getOrderByAsSelect($placeholder = null, $selected = null, $attr = null)
  {
    if (is_null($selected)) {
      $o = $this->getOrderBy();

      $selected = $o ? $o->asUrlParameter() : null;
    }

    return new Select(OrderBy::URL_PARAM, $this->getOrderByOptionsForSelect(), $selected, $attr, $placeholder);
  }

  /**
   * Вариант сортировки по имени колонки
   *
   * @return OrderBy
   */
  public function getOrderByOption($column)
  {
    $opt = $this->getOrderByOptions();

    list($col, $asc) = $this->processOrderColumnFromUrl($column);

    if (!$this->checkOrderByOptionExists($col)) {
      Exception::ThrowError(Exception::NO_ORDERBY_OPTION, $col);
    }

    $o = $opt[$col];

    return $o->setAsc($asc);
  }

  /** Явно указываем поле для сортировки */
  public function setOrderBy($column, $asc = true)
  {
    $this->orderby     = $column;
    $this->orderby_asc = $asc;

    return $this;
  }

  /**
   * Установка сортировки по-умолчанию
   * Если такого поля для сортировка не было добавлено ранее - оно будет подставлено автоматически
   */
  public function setDefaultOrderBy($column, $asc = true)
  {
    if (!$this->checkOrderByOptionExists($column)) {
      $this->addOrderBy($column);
    }

    $this->default_orderby     = $column;
    $this->default_orderby_asc = $asc;

    return $this;
  }

  /** Сортировка по-умолчанию */
  public function getDefaultOrderBy()
  {
    if (!$this->default_orderby) {
      return false;
    }

    return $this->getOrderByOption($this->default_orderby)->setAsc($this->default_orderby_asc);
  }

  /** Определение колонки и порядка сортировки. Возвращает массив [colname, asc] */
  public function processOrderColumnFromUrl($column)
  {
    // Обратная сортировка
    if (StaticStringy::startsWith($column, '_')) {
      $col = StaticStringy::removeLeft($column, '_');
      if ($this->checkOrderByOptionExists($col)) {
        return array($col, false);
      }
    }

    return array($column, true);
  }

  /** Проверка, что опция сортировки существует */
  public function checkOrderByOptionExists($column)
  {
    $opt = $this->getOrderByOptions();

    return isset($opt[$column]);
  }

  /** Получение параметра из URL или из Request */
  public function getParameter($name, $default = false)
  {
    return $this->getUrl()->getParameter($name, null, $this->getRequest()->get($name, $default));
  }

  /** @return Request */
  public function getRequest()
  {
    return $this->request;
  }

  /** @return static */
  public function setRequest(Request $request)
  {
    $this->request = $request;

    return $this;
  }

  /** @return Manager */
  public function getManager()
  {
    return $this->manager;
  }

  /** @return static */
  public function setManager(Manager $manager)
  {
    $this->manager = $manager;

    return $this;
  }

  /** @return URLImmutable */
  public function getUrl()
  {
    return $this->url;
  }

  /** @return static */
  public function setUrl($url)
  {
    $this->url = new URLImmutable($url);

    return $this;
  }

  /**
   * Получение URL без лишних параметров и постраничности
   *
   * @return URLImmutable
   */
  public function getUrlClean()
  {
    $this->process();

    $u = $this->getUrl()->removeParameters();

    if ($o = $this->getOrderBy(true)) {
      $u = $u->addParameter('orderby', $o->asUrlParameter());
    }

    if ($this->filters) {
      foreach ($this->filters as $f) {
        if ($f instanceof Between) {
          if ($val = $f->getCleanValueFrom()) {
            $u = $u->addParameter($f->getColumnFrom(), $val);
          }
          if ($val = $f->getCleanValueTo()) {
            $u = $u->addParameter($f->getColumnTo(), $val);
          }
        } else {
          if ($val = $f->getCleanValue()) {
            $u = $u->addParameter($f->getColumn(), $val);
          }
        }
      }
    }

    return $u;
  }

  /** Общее количество элементов */
  public function getTotal($refresh = false)
  {
    if (!$this->isSearchable()) {
      return 0;
    }

    if ($refresh || !$this->total) {
      $this->total = $this->countTotal();
    }

    return $this->total ? : 0;
  }

  /** Общее количество элементов */
  public function setTotal($total)
  {
    $this->total = $total;
    $this->setSearchable(true);

    return $this;
  }

  /** Количество страниц */
  public function getTotalPages()
  {
    if (!$total = $this->getTotal()) {
      return false;
    }

    if (!$onpage = $this->getOnpage()) {
      return 1;
    }

    return ceil($total / $onpage);
  }

  /** Номер последней страницы */
  public function getLastPage()
  {
    return $this->getTotalPages();
  }

  /** @return bool|URL */
  public function getLastPageUrl()
  {
    $p = $this->getLastPage();

    return $p ? $this->getPageUrl($p) : false;
  }

  /** Номер первой страницы */
  public function getFirstPage()
  {
    return 1;
  }

  /** @return bool|URL */
  public function getFirstPageUrl()
  {
    $p = $this->getFirstPage();

    return $p ? $this->getPageUrl($p) : false;
  }

  /** Номер следующей страницы */
  public function getNextPage()
  {
    $p = $this->getPage();

    return $p < $this->getTotalPages() ? $p + 1 : false;
  }

  /** @return bool|URL */
  public function getNextPageUrl()
  {
    $p = $this->getNextPage();

    return $p ? $this->getPageUrl($p) : false;
  }

  /** Номер предыдущей страницы */
  public function getPrevPage()
  {
    $p = $this->getPage();

    return $p > 1 ? $p - 1 : false;
  }

  /** @return bool|URL */
  public function getPrevPageUrl()
  {
    $p = $this->getPrevPage();

    return $p ? $this->getPageUrl($p) : false;
  }

  /** Явное указание текущего номера страницы */
  public function setPage($page)
  {
    $this->setUrl($this->getUrl()->setParameter('page', $page));

    return $this;
  }

  /** Получение текущего номера страницы */
  public function getPage()
  {
    return $this->getUrl()->getPage();
  }


  public function setOnpage($onpage)
  {
    $this->onpage = $onpage;

    return $this;
  }

  public function getOnpage()
  {
    return $this->onpage;
  }

  /** Есть ли результаты выборки */
  public function setSearchable($searchable)
  {
    $this->searchable = $searchable;

    return $this;
  }

  /** Есть ли результаты выборки */
  public function isSearchable()
  {
    return $this->searchable;
  }

  /**
   * Ссылка на страницу с нужным номером
   * Если номер не указан - будет возвращен адрес текущей страницы
   *
   * @return URL
   */
  public function getPageUrl($page = null)
  {
    if (is_null($page)) {
      $page = $this->getPage();
    }

    return $this->getUrlClean()->setParameter('page', $page);
  }

  /** @return Collection */
  public function getCollection()
  {
    return $this->collection;
  }

  /**
   * $collection - объект коллекции или название для получения коллекции из менеджера БД
   * @return static
   */
  public function setCollection($collection)
  {
    $this->collection = $collection instanceof Collection
      ? $collection
      : $this->getManager()->getCollection($collection);

    return $this;
  }

  /** Обработка URL и Request для получения параметров */
  protected function process()
  {
    if ($orderby = $this->getParameter(OrderBy::URL_PARAM)) {
      list($col, $asc) = $this->processOrderColumnFromUrl($orderby);
      if ($this->checkOrderByOptionExists($col)) {
        $this->setOrderBy($col, $asc);
      }
    }
  }

  protected function init()
  {

  }
}