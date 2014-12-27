<?php

namespace SQRT\Navigator;

class Exception extends \SQRT\Exception
{
  const NO_ORDERBY_OPTION   = 10;
  const FILTER_NOT_EXISTS   = 20;
  const FILTER_NOT_CALLABLE = 30;

  protected static $errors_arr = array(
    self::NO_ORDERBY_OPTION   => 'Поля "%s" нет в опциях для сортировки',
    self::FILTER_NOT_EXISTS   => 'Фильтр "%s" не задан',
    self::FILTER_NOT_CALLABLE => 'В фильтр передано не callable значение',
  );
}