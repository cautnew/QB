<?php

namespace Cautnew\QB;

use Cautnew\QB\QB;
use Cautnew\QB\CONDITION;
use Exception;

/**
 * Prepare SELECT commands. You can set all details about the query.
 */
class SELECT extends QB
{
  private string $table;
  private string $alias;
  private array $columns = [];
  private array $columnsAliases = [];
  private array $columnsOrder = [];
  private array $columnsGroup = [];
  private CONDITION $condition;
  private int $limit = 0;
  private int $offset = 0;

  public function __construct(string $table, string $alias = "")
  {
    $this->table = $table;

    if (!empty($alias)) {
      $this->alias = $alias;
    }
  }

  public function __toString()
  {
    return $this->render();
  }

  private function render(): string
  {
    if (empty($this->table)) {
      throw new Exception("Table name is required.");
    }

    $query = "SELECT ";
    $query .= $this->getColumns();

    if (!empty($this->alias)) {
      $query .= " FROM " . $this->getTableName() . " AS " . $this->getTableAlias();
    } else {
      $query .= " FROM " . $this->getTableName();
    }

    if (isset($this->condition)) {
      $query .= " WHERE " . $this->condition;
    }

    if ($this->limit > 0) {
      $query .= " LIMIT " . $this->limit;
    }

    if ($this->offset > 0) {
      $query .= " OFFSET " . $this->offset;
    }

    return $query;
  }

  public function setColumns(array $columns): self
  {
    $this->columns = $columns;

    return $this;
  }

  public function getTableName(): ?string
  {
    return $this->table;
  }

  public function getTable(): ?string
  {
    return $this->getTableName();
  }

  public function getTableAlias(): ?string
  {
    return $this->alias;
  }

  private function getColumns(): ?string
  {
    if (empty($this->columns)) {
      return '*';
    }

    $arrColumns = array();

    foreach ($this->columns as $column) {
      if (isset($this->columnsAliases[$column])) {
        $arrColumns[] = "{$column} AS {$this->columnsAliases[$column]}";
      } else {
        $arrColumns[] = $column;
      }
    }

    return implode(',', $arrColumns);
  }

  public function setColumnsAliases(array $columnsAliases): self
  {
    $this->columnsAliases = $columnsAliases;

    return $this;
  }

  public function setColumnsOrder(array $columnsOrder): self
  {
    $this->columnsOrder = $columnsOrder;

    return $this;
  }

  public function setCondition(CONDITION $condition): self
  {
    $this->condition = $condition;

    return $this;
  }

  public function getCondition(): CONDITION
  {
    return $this->condition;
  }

  public function getWhere(): CONDITION
  {
    return $this->getCondition();
  }

  public function where(CONDITION $condition): self
  {
    $this->condition = $condition;

    return $this;
  }

  public function limit(int $limit): self
  {
    $this->limit = $limit;

    return $this;
  }

  public function offset(int $offset): self
  {
    $this->offset = $offset;

    return $this;
  }
}
