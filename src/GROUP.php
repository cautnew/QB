<?php

namespace Cautnew\QB;

use Cautnew\QB\CONDITIONS;

class GROUP extends CONDITIONS
{
  private CONDITION $firstCondition;

  public function __toString()
  {
    $instruction = '(';

    if (isset($this->previous)) {
      $instruction = $this->getConnector() . ' ' . $instruction;
    }

    $instruction .= $this->firstCondition . ')';

    if (isset($this->next)) {
      $instruction .= ' ' . $this->next;
    }

    return $instruction;
  }
}
