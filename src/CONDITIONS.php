<?php

interface CONDITIONS {
  public function clearConditions(): self;

  public function where(array $cond): self;

  public function addCondition(string $condition, string $connector = 'AND'): self;

  public function addConditions(array $conditions, string $connector = 'AND'): self;

  public function addConditionAnd(array $conditions): self;

  public function addConditionOr(array $conditions): self;

  public function addCondAnd(array $conditions): self;

  public function addCondOr(array $conditions): self;

  public function whereAnd(array $conditions): self;

  public function whereOr(array $conditions): self;
}
