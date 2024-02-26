<?php

namespace Cautnew\QB;

use Cautnew\QB\CONDITIONS;

class CONDITION extends CONDITIONS
{
  private string $termA;
  private $termB;

  private int $condition;

  private const COND_EQUALS = 1;
  private const COND_GREATERTHEN = 2;
  private const COND_LESSTHEN = 3;
  private const COND_ISNULL = 4;
  private const COND_IN = 5;
  private const COND_GREATEROREQUALSTHEN = 6;
  private const COND_LESSOREQUALSTHEN = 7;

  public function __construct(string $termA)
  {
    $this->termA = $termA;
  }

  public function __toString()
  {
    $instruction = trim($this->termA . ' ' . $this->getCondition() . ' ' . $this->termB);

    if (isset($this->previous)) {
      $instruction = $this->getConnector() . ' ' . $instruction;
    }

    if (isset($this->next)) {
      $instruction .= ' ' . $this->next;
    }

    return $instruction;
  }

  private function getCondition(): string
  {
    if (empty($this->condition)) {
      return '';
    }

    switch ($this->condition) {
      case self::COND_EQUALS:
        return '=';
      case self::COND_GREATERTHEN:
        return '>';
      case self::COND_LESSTHEN:
        return '<';
      case self::COND_ISNULL:
        return 'IS NULL';
      case self::COND_IN:
        return 'IN';
      case self::COND_GREATEROREQUALSTHEN:
        return '>=';
      case self::COND_LESSOREQUALSTHEN:
        return '<=';
    }

    return '';
  }

  public function equals(string $termB): self
  {
    $this->termB = $termB;
    $this->condition = self::COND_EQUALS;

    return $this;
  }

  public function isnull(): self
  {
    $this->condition = self::COND_ISNULL;

    return $this;
  }

  public function greaterThen(string $termB): self
  {
    $this->termB = $termB;
    $this->condition = self::COND_GREATERTHEN;

    return $this;
  }

  public function lessThen(string $termB): self
  {
    $this->termB = $termB;
    $this->condition = self::COND_LESSTHEN;

    return $this;
  }

  public function in(array $termB): self
  {
    $this->termB = $termB;
    $this->condition = self::COND_IN;

    return $this;
  }
}
