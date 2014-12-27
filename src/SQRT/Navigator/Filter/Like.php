<?php

namespace SQRT\Navigator\Filter;

use SQRT\Navigator;
use SQRT\Navigator\Filter;
use SQRT\QueryBuilder\Query;

class Like extends Filter
{
  protected $prepare;
  protected $like_template = '%s%%';
  protected $regular_cleaner = '/[^a-zа-яёЁ0-9]+/ui';

  public function process()
  {
    $n = $this->getNavigator();

    if ($this->getCallable()) {
      parent::process();
    } else {
      if ($val = $this->getCleanValue()) {
        $n->conditions()->like($this->getField(), sprintf($this->getLikeTemplate(), $this->cleanValue($val)));
      }
    }
  }

  /** Очистка значения от недопустимых символов по регулярке */
  public function cleanValue($value)
  {
    $str = preg_replace($this->regular_cleaner, ' ', $value);
    $str = preg_replace('/^[\s]+/u', '', $str);
    $str = preg_replace('/[\s]+$/u', '', $str);

    return str_replace(' ', '%', $str);
  }

  /** Шаблон для поиска. По умолчанию %s%% */
  public function setLikeTemplate($like_template)
  {
    $this->like_template = $like_template;

    return $this;
  }

  /** Шаблон для поиска. По умолчанию %s%% */
  public function getLikeTemplate()
  {
    return $this->like_template;
  }

  /** Регулярное выражение для очистки значения */
  public function setRegularCleaner($regular_cleaner)
  {
    $this->regular_cleaner = $regular_cleaner;

    return $this;
  }

  /** Регулярное выражение для очистки значения */
  public function getRegularCleaner()
  {
    return $this->regular_cleaner;
  }
}