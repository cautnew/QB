<?php

namespace Cautnew\QB;

use Cautnew\QB\CONDITION;
use Cautnew\QB\GROUP;

class CONDITIONS
{
  protected int $connector;

  protected CONDITION | GROUP $previous;
  protected CONDITION | GROUP $next;

  protected const CONN_AND = 1;
  protected const CONN_OR = 2;

  public function getConnector(): string
  {
    if (empty($this->connector)) {
      return '';
    }

    switch ($this->connector) {
      case self::CONN_AND:
        return 'AND';
      case self::CONN_OR:
        return 'OR';
    }

    return '';
  }

  public function setConnector(int $connector): self
  {
    $this->connector = $connector;

    return $this;
  }

  public function setAnd(): self
  {
    $this->setConnector(self::CONN_AND);

    return $this;
  }

  public function setOr(): self
  {
    $this->setConnector(self::CONN_OR);

    return $this;
  }

  public function and(CONDITION | GROUP $condition): self
  {
    $condition->setAnd();
    $this->setNext($condition);

    return $this;
  }

  public function or(CONDITION | GROUP $condition): self
  {
    $condition->setOr();
    $this->setNext($condition);

    return $this;
  }

  public function setPrevious(CONDITION | GROUP $previous): self
  {
    $this->previous = $previous;

    return $this;
  }

  public function addPrevious(CONDITION | GROUP $previous): self
  {
    return $this->setPrevious($previous);
  }

  public function getPrevious(): CONDITION | GROUP
  {
    return $this->previous;
  }

  public function setNext(CONDITION | GROUP $next, bool $currentPosition = false): self
  {
    if (isset($this->next)) {
      if ($currentPosition) {
        $next->setNext($this->next);
        $next->setPrevious($this);
        $this->next->setPrevious($next);
      } else {
        $this->next->setNext($next);
        $next->setPrevious($this->next);

        return $this;
      }
    }

    $this->next = $next;
    $this->next->setPrevious($this);

    return $this;
  }

  public function addNext(CONDITION | GROUP $next): self
  {
    return $this->setNext($next);
  }

  public function getNext(): CONDITION | GROUP
  {
    return $this->next;
  }
}
