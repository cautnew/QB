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

  protected bool $indDistinct = false;

  const PERMITTED_JOIN_TYPES = ['INNER', 'LEFT', 'RIGHT', 'OUTTER', 'NATURAL'];
  const CONDITION_IN_LIMIT_ITEMS = 1000;
  const CONDITION_IN_SEPARATOR = ',';

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

  private function renderJoins(): void
  {
    if (empty($this->joins)) {
      return;
    }

    foreach ($this->joins as $join) {
      $this->commands[] = $join["type"] . " JOIN";

      if (gettype($join["table"]) === 'string') {
        $this->commands[] = $join["table"];
      } else {
        $table = $join["table"]->render()->getQuery();
        $this->commands[] = "({$table})";
      }

      if (!empty($join["alias"])) {
        $this->commands[] = $join["alias"];
      }

      if ($join["type"] != 'NATURAL') {
        $this->commands[] = 'ON';
        $this->commands[] = join(' ', $join["conditions"]);
      }
    }
  }

  private function render(): string
  {
    if (empty($this->table)) {
      throw new Exception("Table name is required.");
    }

    $query = "SELECT ";
    $query .= $this->getColumns();

    if (!empty($this->alias)) {
      $query .= "\nFROM " . $this->getTableName() . " AS " . $this->getTableAlias();
    } else {
      $query .= "\nFROM " . $this->getTableName();
    }

    foreach ($this->joins as $join) {
      $query .= "\n" . $join["type"] . " JOIN";

      if (gettype($join["table"]) === 'string') {
        $query .= ' ' . $join["table"];
      } else {
        $table = $join["table"]->render()->getQuery();
        $query .= " ({$table})";
      }

      if (!empty($join["alias"])) {
        $query .= ' AS ' . $join["alias"];
      }

      if ($join["type"] != 'NATURAL') {
        $query .= "\nON " . $join["conditions"];
      }
    }

    if (isset($this->condition)) {
      $query .= "\nWHERE " . $this->condition;
    }

    if ($this->limit > 0) {
      $query .= "\nLIMIT " . $this->limit;
    }

    if ($this->offset > 0) {
      $query .= "\nOFFSET " . $this->offset;
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

  public function join(string $type, $table, string $alias, ?array $conditions = null): self
  {
    $type = strtoupper(trim($type));

    if (!in_array($type, self::PERMITTED_JOIN_TYPES)) {
      throw new Exception("Join type not recognized.");
    }

    $this->joins[$alias] = [
      "type" => $type,
      "table" => $table,
      "alias" => $alias,
      "conditions" => $conditions
    ];

    $this->indRendered = false;

    return $this;
  }

  public function innerJoin($table, string $alias, array $conditions): self
  {
    return $this->join('INNER', $table, $alias, $conditions);
  }

  public function leftJoin($table, string $alias, array $conditions): self
  {
    return $this->join('LEFT', $table, $alias, $conditions);
  }

  public function rightJoin($table, string $alias, array $conditions): self
  {
    return $this->join('RIGHT', $table, $alias, $conditions);
  }

  public function outterJoin($table, string $alias, array $conditions): self
  {
    return $this->join('OUTTER', $table, $alias, $conditions);
  }

  public function naturalJoin($table, string $alias): self
  {
    return $this->join('NATURAL', $table, $alias);
  }

  public function clearJoins(): self
  {
    $this->joins = [];

    return $this;
  }
}
