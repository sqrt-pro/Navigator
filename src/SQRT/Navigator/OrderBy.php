<?php

namespace SQRT\Navigator;

use SQRT\Navigator;
use SQRT\URL;
use SQRT\URLImmutable;
use Stringy\StaticStringy;

/** Представление для сортировки */
class OrderBy
{
  /** Имя параметра в URL */
  const URL_PARAM = 'orderby';

  protected $column;
  protected $name;
  protected $sql;
  protected $asc = true;
  protected $inverse;

  /** @var Navigator */
  protected $navigator;

  /** @var URL */
  protected $url;

  protected $tmpl_asc_name  = '%s по-возрастанию';
  protected $tmpl_desc_name = '%s по-убыванию';

  function __construct($column = null, $name = null, $sql = null, Navigator $navigator = null)
  {
    $this->setColumn($column);
    $this->setName($name);
    $this->setSql($sql);

    if ($navigator) {
      $this->setNavigator($navigator);
    }
  }

  /** Получение имени для передачи как параметра в URL */
  public function asUrlParameter($asc = null)
  {
    if (is_null($asc)) {
      $asc = $this->getAsc();
    }

    return ($asc ? '' : '_') . $this->getColumn();
  }

  /**
   * Ссылка на вариант сортировки
   *
   * @return URL
   */
  public function asUrl($asc = null)
  {
    return $this->getUrl()->setParameter(static::URL_PARAM, $this->asUrlParameter($asc));
  }

  /** Получение в виде инструкции для сортировки SQL */
  public function asSQL()
  {
    $q = $this->getInverse()
      ? ($this->getAsc() ? 'DESC' : 'ASC')
      : ($this->getAsc() ? 'ASC' : 'DESC');

    return ($this->getSql() ?: '`' . $this->getColumn() . '`') . ' ' . $q;
  }

  public function setColumn($column)
  {
    $this->column = $column;

    return $this;
  }

  public function getColumn()
  {
    return $this->column;
  }

  public function setName($name)
  {
    $this->name = $name;

    return $this;
  }

  public function getName()
  {
    return $this->name ?: StaticStringy::humanize($this->getColumn());
  }

  public function setSql($sql)
  {
    $this->sql = $sql;

    return $this;
  }

  public function getSql()
  {
    return $this->sql;
  }

  public function setAsc($asc)
  {
    $this->asc = $asc;

    return $this;
  }

  public function getAsc()
  {
    return $this->asc;
  }

  public function setUrl(URL $url)
  {
    $this->url = $url;

    return $this;
  }

  public function getUrl()
  {
    if ($this->url) {
      return $this->url;
    }

    if ($this->navigator) {
      return $this->navigator->getUrlClean();
    }

    return new URLImmutable;
  }

  public function setNavigator(Navigator $navigator)
  {
    $this->navigator = $navigator;

    return $this;
  }

  /** Обратная сортировка */
  public function setInverse($inverse)
  {
    $this->inverse = $inverse;

    return $this;
  }

  /** Обратная сортировка */
  public function getInverse()
  {
    return $this->inverse;
  }

  /** Имя сортировки по-возрастанию */
  public function getAscName()
  {
    return sprintf($this->tmpl_asc_name, $this->getName());
  }

  /** Имя сортировки по-убыванию */
  public function getDescName()
  {
    return sprintf($this->tmpl_desc_name, $this->getName());
  }

  /** Шаблон для названия сортировки по-возрастанию. По умолчанию: %s по-возрастанию */
  public function setTmplAscName($asc_name)
  {
    $this->tmpl_asc_name = $asc_name;

    return $this;
  }

  /** Шаблон для названия сортировки по-возрастанию. По умолчанию: %s по-возрастанию */
  public function getTmplAscName()
  {
    return $this->tmpl_asc_name;
  }

  /** Шаблон для названия сортировки по-убыванию. По умолчанию: %s по-убыванию */
  public function setTmplDescName($desc_name)
  {
    $this->tmpl_desc_name = $desc_name;

    return $this;
  }

  /** Шаблон для названия сортировки по-убыванию. По умолчанию: %s по-убыванию */
  public function getTmplDescName()
  {
    return $this->tmpl_desc_name;
  }
}