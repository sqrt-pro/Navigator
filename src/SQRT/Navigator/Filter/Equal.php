<?php

namespace SQRT\Navigator\Filter;

use SQRT\Navigator;
use SQRT\Navigator\Filter;

class Equal extends Filter
{
  public function process()
  {
    $n = $this->getNavigator();

    if ($val = $this->getCleanValue()) {
      $n->conditions()->equal($this->getField(), $val);
    }
  }
}