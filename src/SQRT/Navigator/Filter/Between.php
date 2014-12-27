<?php

namespace SQRT\Navigator\Filter;

use SQRT\QueryBuilder\Conditions;
use SQRT\QueryBuilder\Query;
use SQRT\Tag\Input;
use SQRT\Navigator;
use SQRT\Navigator\Filter;

class Between extends Filter
{
  protected $less_or_equal = true;
  protected $greater_or_equal = true;
  protected $is_date = false;

  public function process()
  {
    if ($this->getCallable()) {
      parent::process();
    } else {
      $n = $this->getNavigator();
      $c = $n->conditions();

      $this->prepareFromCondition($c);
      $this->prepareToCondition($c);
    }
  }

  /** Подготовка условия для нижней границы диапазона */
  public function prepareFromCondition(Conditions $conditions = null, $val = null)
  {
    if (is_null($conditions)) {
      $conditions = new Conditions();
    }

    if (is_null($val)) {
      if ($val = $this->getCleanValueFrom()) {
        if ($this->isDate()) {
          $val = date('Y-m-d 00:00', strtotime($val));
        }
      }
    }

    if ($val) {
      if ($this->isGreaterOrEqual()) {
        $conditions->greaterOrEqual($this->getField(), $val);
      } else {
        $conditions->greater($this->getField(), $val);
      }
    }

    return $conditions;
  }

  /** Подготовка условия для верхней границы диапазона */
  public function prepareToCondition(Conditions $conditions = null, $val = null)
  {
    if (is_null($conditions)) {
      $conditions = new Conditions();
    }

    if (is_null($val)) {
      if ($val = $this->getCleanValueTo()) {
        if ($this->isDate()) {
          $val = date('Y-m-d 23:59:59', strtotime($val));
        }
      }
    }

    if ($val) {
      if ($val) {
        if ($this->isLessOrEqual()) {
          $conditions->lessOrEqual($this->getField(), $val);
        } else {
          $conditions->less($this->getField(), $val);
        }
      }
    }

    return $conditions;
  }

  /**
   * @param boolean $is_date
   */
  public function setIsDate($is_date)
  {
    $this->is_date = $is_date;

    return $this;
  }

  /**
   * @return boolean
   */
  public function isDate()
  {
    return $this->is_date;
  }

  /**
   * Генерация тега INPUT для фильтра от.
   * Значение будет подставлено если оно проходит валидацию
   * @return Input
   */
  public function asInputFrom($attr = null, $value = null)
  {
    if (is_null($value)) {
      $value = $this->getCleanValueFrom();
    }

    return new Input($this->getColumnFrom(), $value, $attr);
  }

  /**
   * Генерация тега INPUT для фильтра до.
   * Значение будет подставлено если оно проходит валидацию
   * @return Input
   */
  public function asInputTo($attr = null, $value = null)
  {
    if (is_null($value)) {
      $value = $this->getCleanValueTo();
    }

    return new Input($this->getColumnTo(), $value, $attr);
  }

  /** Получение чистого значения фильтра после валидации */
  public function getCleanValueFrom()
  {
    $val = $this->getNavigator()->getParameter($this->getColumnFrom());

    return $this->validate($val) ? $val : false;
  }

  /** Получение чистого значения фильтра после валидации */
  public function getCleanValueTo()
  {
    $val = $this->getNavigator()->getParameter($this->getColumnTo());

    return $this->validate($val) ? $val : false;
  }

  /** Имя параметра "от" */
  public function getColumnFrom()
  {
    return $this->getColumn() . '_from';
  }

  /** Имя параметра "до" */
  public function getColumnTo()
  {
    return $this->getColumn() . '_to';
  }

  /** Сравнение > или >= */
  public function setGreaterOrEqual($greater_or_equal)
  {
    $this->greater_or_equal = $greater_or_equal;

    return $this;
  }

  /** Сравнение > или >= */
  public function isGreaterOrEqual()
  {
    return $this->greater_or_equal;
  }

  /** Сравнение < или <= */
  public function setLessOrEqual($less_or_equal)
  {
    $this->less_or_equal = $less_or_equal;

    return $this;
  }

  /** Сравнение < или <= */
  public function isLessOrEqual()
  {
    return $this->less_or_equal;
  }
}