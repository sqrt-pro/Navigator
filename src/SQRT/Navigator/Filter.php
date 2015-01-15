<?php

namespace SQRT\Navigator;

use SQRT\URL;
use SQRT\Navigator;
use SQRT\Tag\Select;
use SQRT\Tag\Input;
use Stringy\StaticStringy;

/** Условие для выборки */
class Filter
{
  protected $name;
  protected $field;
  protected $column;
  protected $callable;
  protected $validator;

  protected $options;
  protected $options_ignore_keys;

  /** @var Navigator */
  protected $navigator;

  function __construct(Navigator $navigator, $column, $name = null, $validator = null, $callable = null)
  {
    if (!is_null($callable) && !is_callable($callable)) {
      Exception::ThrowError(Exception::FILTER_NOT_CALLABLE);
    }

    $this->navigator = $navigator;
    $this->column    = $column;
    $this->name      = $name;
    $this->callable  = $callable;
    $this->validator = $validator;
  }

  /** @return URL */
  public function asUrl($value)
  {
    return $this->getNavigator()->getUrlClean()->setParameter($this->getColumn(), $value);
  }

  /**
   * Генерация тега SELECT со списком опций
   * @return Select
   */
  public function asSelect($placeholder = null, $attr = null, $value = null)
  {
    if (!$options = $this->getOptions()) {
      return false;
    }

    $s = new Select($this->getColumn(), $options, is_null($value) ? $value : $this->getCleanValue(), $attr, $placeholder);

    if ($this->getOptionsIgnoreKeys()) {
      $s->setIgnoreOptionsKeys(true);
    }

    return $s;
  }

  /**
   * Генерация тега INPUT для фильтра. Значение будет подставлено если оно проходит валидацию
   * @return Input
   */
  public function asInput($attr = null, $value = null)
  {
    return new Input($this->getColumn(), is_null($value) ? $this->getCleanValue() : $value, $attr);
  }

  /** Получение чистого значения фильтра после валидации */
  public function getCleanValue()
  {
    $val = $this->getNavigator()->getParameter($this->getColumn());

    return $this->validate($val) ? $val : false;
  }

  /** Вызов фильтра, происходит только в случае успешной валидации */
  public function process()
  {
    if ($c = $this->callable) {
      return call_user_func_array($c, array($this->navigator, $this));
    }
  }

  /**
   * Проверка данных.
   * $value - значение или массив, тогда проверяются все значения массива
   * $validator - массив допустимых опций, callable или регулярка
   */
  public function validate($value)
  {
    if (is_array($value)) {
      foreach ($value as $v) {
        if (!$this->validate($v, $this->validator)) {
          return false;
        }
      }

      return true;
    } else {

      if ($arr = $this->getOptions()) {
        if (empty($value)) {
          return false;
        }

        if ($this->getOptionsIgnoreKeys()) {
          if (!in_array($value, $arr)) {
            return false;
          }
        } else {
          if (!array_key_exists($value, $arr)) {
            return false;
          }
        }
      }

      if (is_callable($this->validator)) {
        return (bool)call_user_func_array($this->validator, array($value));
      } elseif ($this->validator) {
        return (bool)preg_match($this->validator, $value);
      }

      return true;
    }
  }

  /** Список опций доступных для выбора */
  public function setOptions($array, $ignore_keys = false)
  {
    $this->options             = $array;
    $this->options_ignore_keys = $ignore_keys;

    return $this;
  }

  /** Список опций доступных для выбора */
  public function getOptions()
  {
    return $this->options ? : false;
  }

  /** Флаг, что в списке опций используются значения, а не ключи */
  public function setOptionsIgnoreKeys($ignore_keys = true)
  {
    $this->options_ignore_keys = $ignore_keys;

    return $this;
  }

  /** Флаг, что в списке опций используются значения, а не ключи */
  public function getOptionsIgnoreKeys()
  {
    return $this->options_ignore_keys;
  }

  public function setCallable($callable)
  {
    $this->callable = $callable;

    return $this;
  }

  public function getCallable()
  {
    return $this->callable;
  }

  /** Имя параметра в адресе */
  public function setColumn($col)
  {
    $this->column = $col;

    return $this;
  }

  /** Имя параметра в адресе */
  public function getColumn()
  {
    return $this->column;
  }

  /** Название фильтра */
  public function getName()
  {
    return $this->name ?: StaticStringy::humanize($this->getColumn());
  }

  /** Название фильтра */
  public function setName($name)
  {
    $this->name = $name;

    return $this;
  }

  /** Поле в SQL */
  public function setField($field)
  {
    $this->field = $field;

    return $this;
  }

  /** Поле в SQL */
  public function getField()
  {
    return $this->field ? : $this->getColumn();
  }

  /** Валидатор для значений. Callable или регулярное выражение */
  public function setValidator($validator)
  {
    $this->validator = $validator;

    return $this;
  }

  /** Валидатор для значений. Callable или регулярное выражение */
  public function getValidator()
  {
    return $this->validator;
  }

  public function setNavigator(Navigator $navigator)
  {
    $this->navigator = $navigator;

    return $this;
  }

  /** @return Navigator */
  public function getNavigator()
  {
    return $this->navigator;
  }
}