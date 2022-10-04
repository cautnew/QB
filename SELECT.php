<?php

namespace Conn\QB;

use Conn\QB\QB;
use PDO;
use Exception;

class SELECT extends QB
{
  protected $table;

  protected ?string $tableAlias;
  protected string $sql;

  protected bool $indAggregate = false;
  protected bool $indDistinct = false;

  protected ?array $with = [];
  protected ?array $columns = [];
  protected ?array $columnsAggregated = [];
  protected ?array $joins = [];
  protected ?array $having = [];
  protected ?array $orderBy = [];
  protected ?int $limit;
  protected ?int $offset;
  protected ?array $conditions = [];

  protected ?int $maxExecutionTime = null;
  
  const PERMITTED_JOIN_TYPES = ['INNER', 'LEFT', 'RIGHT', 'OUTTER', 'NATURAL'];
  const PERMITTED_COND_CONNECTORS = ['AND', 'OR'];
  const CONDITION_IN_LIMIT_ITEMS = 1000;
  const CONDITION_IN_SEPARATOR = ',';

  public function __construct(null | string $table = null, null | string $alias = null)
  {
    if (!empty($table)) {
      $this->from($table, $alias);
    }
  }

  public function __toString()
  {
    return $this->getQuery();
  }

  public static function whereBetween(string $columnName, string $valueFrom, string $valueTo): string
  {
    if (empty($columnName) || empty($valueFrom) || empty($valueTo)) {
      return '';
    }

    return "{$columnName} BETWEEN {$valueFrom} AND {$valueTo}";
  }

  public static function whereIn(string $columnName, array $values, int $limitItemsConditionIn=self::CONDITION_IN_LIMIT_ITEMS): string
  {
    if (empty($columnName) || empty($values)) {
      return '';
    }

    $qtdItems = count($values);
    
    if ($qtdItems > $limitItemsConditionIn) {
      $itemAtual = 0;
      $valReturn = '(';

      while ($itemAtual < $qtdItems) {
        $items = implode(self::CONDITION_IN_SEPARATOR, array_slice($values, $itemAtual, $limitItemsConditionIn));
        $valReturn .= "{$columnName} IN({$items}) OR ";
        $itemAtual += $limitItemsConditionIn;
      }

      return rtrim($valReturn, ' OR') . ')';
    }

    $items = implode(self::CONDITION_IN_SEPARATOR, $values);

    return "{$columnName} IN({$items})";
  }

  public function from(string $table, ?string $alias = null): self
  {
    $this->table = $table;
    $this->tableAlias = $alias;
    $this->indRendered = false;

    return $this;
  }

  public function setTableName(string $table, ?string $alias = null): self
  {
    return $this->from($table, $alias);
  }

  public function setDistinct(bool $indDistinct = true): self
  {
    $this->indDistinct = $indDistinct;
    $this->indRendered = false;

    return $this;
  }

  public function with(string $alias, $sql): self
  {
    if (!isset($this->with[$alias])) {
      $this->with[$alias] = $sql;
      $this->indRendered = false;
    }

    return $this;
  }

  public function setWith(string $alias, $sql): self
  {
    return $this->with($alias, $sql);
  }

  public function setMaxExecutionTime(int $time): self
  {
    if ($time < 0) {
      throw new Exception("Invalid time. \$time must be an int igual or greater then 0 in milliseconds.");
    } elseif ($time == 0) {
      $this->maxExecutionTime = null;
      return $this;
    }

    $this->maxExecutionTime = $time;

    return $this;
  }

  public function columns(array|string|null $columns = null): self
  {
    if (is_string($columns)) {
      $columns = explode(',', $columns);
    }

    $this->columns = $columns;
    $this->indRendered = false;

    return $this;
  }

  public function columnsAggregated(array|string|null $columnsAggregated): self
  {
    if (is_string($columnsAggregated)) {
      $columnsAggregated = explode(',', $columnsAggregated);
    }

    $this->columnsAggregated = $columnsAggregated;
    $this->indRendered = false;

    return $this;
  }

  public function setAggregatedQuery(bool $indAggregate): self
  {
    $this->indAggregate = $indAggregate;
    $this->indRendered = false;

    return $this;
  }

  public function select(array|string|null $columns): self
  {
    return $this->columns($columns);
  }

  public function addColumn(string $alias, string $column): self
  {
    if (!isset($this->columns[$alias])) {
      $this->columns[$alias] = $column;
      $this->indRendered = false;
    }

    return $this;
  }

  public function setColumn(string $alias, ?string $column): self
  {
    if (isset($this->columns[$alias])) {
      if ($column === null) {
        unset($this->columns[$alias]);
      } else {
        $this->columns[$alias] = $column;
      }
      $this->indRendered = false;
    }

    return $this;
  }

  public function removeColumn(string $column): self
  {
    if (isset($this->columns[$column])) {
      unset($this->columns[$column]);
      $this->indRendered = false;
    }

    return $this;
  }

  public function removeColumnAggregated(string $columnsAggregated): self
  {
    if (isset($this->columnsAggregated[$columnsAggregated])) {
      unset($this->columnsAggregated[$columnsAggregated]);
      $this->indRendered = false;
    }

    return $this;
  }

  public function join(string $type, $table, ?string $alias, ?array $conditions): self
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

  public function innerJoin($table, ?string $alias, ?array $conditions): self
  {
    return $this->join('INNER', $table, $alias, $conditions);
  }

  public function leftJoin($table, ?string $alias, ?array $conditions): self
  {
    return $this->join('LEFT', $table, $alias, $conditions);
  }

  public function rightJoin($table, ?string $alias, ?array $conditions): self
  {
    return $this->join('RIGHT', $table, $alias, $conditions);
  }

  public function outterJoin($table, ?string $alias, ?array $conditions): self
  {
    return $this->join('OUTTER', $table, $alias, $conditions);
  }

  public function naturalJoin($table, ?string $alias): self
  {
    return $this->join('NATURAL', $table, $alias, null);
  }

  public function removeJoin(string $alias): self
  {
    if (isset($this->joins[$alias])) {
      unset($this->joins[$alias]);
      $this->indRendered = false;
    }

    return $this;
  }

  public function clearConditions(): self
  {
    $this->conditions = [];

    return $this;
  }

  public function where(array $cond): self
  {
    $this->conditions = $cond;
    $this->indRendered = false;

    return $this;
  }

  public function addCondition(string $condition, string $connector = 'AND'): self
  {
    if (empty($condition) || empty($connector)) {
      return $this;
    }

    if (count($this->conditions) > 0) {
      $this->conditions[] = $connector;
    }

    $this->conditions[] = $condition;
    $this->indRendered = false;

    return $this;
  }

  public function addConditions(array $conditions, string $connector = 'AND'): self
  {
    foreach ($conditions as $condition) {
      $this->addCondition($condition, $connector);
    }

    return $this;
  }

  public function addConditionAnd(array $conditions): self
  {
    foreach ($conditions as $condition) {
      $this->addCondition($condition, 'AND');
    }

    return $this;
  }

  public function addConditionOr(array $conditions): self
  {
    foreach ($conditions as $condition) {
      $this->addCondition($condition, 'OR');
    }

    return $this;
  }

  public function addCondAnd(array $conditions): self
  {
    return $this->addConditionAnd($conditions);
  }

  public function addCondOr(array $conditions): self
  {
    return $this->addConditionOr($conditions);
  }

  public function whereAnd(array $conditions): self
  {
    return $this->addConditionAnd($conditions);
  }

  public function whereOr(array $conditions): self
  {
    return $this->addConditionOr($conditions);
  }

  public function orderBy(array $listOrderBy): self
  {
    foreach ($listOrderBy as $orderBy) {
      $this->orderBy[] = $orderBy . ',';
    }

    $this->indRendered = false;

    return $this;
  }

  public function limit(int $limit): self
  {
    if ($limit >= 1) {
      $this->limit = $limit;
    } elseif ($limit == 0) {
      $this->limit = null;
    }

    $this->indRendered = false;

    return $this;
  }

  public function offset(int $offset): self
  {
    if ($offset >= 1) {
      $this->offset = $offset;
    } elseif ($offset == 0) {
      $this->offset = null;
    }

    $this->indRendered = false;

    return $this;
  }

  public function numColumns(): int
  {
    if (empty($this->columns)) {
      return 0;
    }

    return count($this->columns);
  }

  public function numColumnsAggregated(): int
  {
    if (empty($this->columnsAggregated)) {
      return 0;
    }

    return count($this->columnsAggregated);
  }

  public function numColumnsTotal(): int
  {
    return $this->numColumns() + $this->numColumnsAggregated();
  }

  private function renderWithClause(): void
  {
    if (empty($this->with)) {
      return;
    }

    $this->commands[] = "WITH";
    foreach ($this->with as $alias => $sql) {
      if (gettype($sql) === 'string') {
        $this->commands[] = "{$alias} AS ({$sql}),";
      } else {
        $_sql = $sql->render()->getQuery();
        $this->commands[] = "{$alias} AS ({$_sql}),";
      }
    }

    $this->removeCommonsLastCommand();
  }

  private function renderColumns(): void
  {
    if ($this->numColumnsTotal() == 0) {
      $this->commands[] = '*';
      return;
    }

    if (!empty($this->columns)) {
      foreach ($this->columns as $alias => $column) {
        if ($column == $alias || is_numeric($alias)) {
          $this->commands[] = "{$column},";
        } else {
          $this->commands[] = "{$column} AS {$alias},";
        }
      }
    }

    if (!empty($this->columnsAggregated)) {
      foreach ($this->columnsAggregated as $alias => $column) {
        $this->commands[] = "{$column} AS {$alias},";
      }
    }

    $this->removeCommonsLastCommand();
  }

  private function renderFrom(): void
  {
    $this->commands[] = 'FROM';

    if (gettype($this->table) === 'string') {
      if (empty($this->table)) {
        throw new Exception("Table name is not set.");
      }

      $this->commands[] = $this->table;
    } else {
      $table = $this->table->render()->getQuery();

      if (empty($this->table)) {
        throw new Exception("Table is not set.");
      }

      $this->commands[] = "({$table})";
    }

    if (!empty($this->tableAlias)) {
      $this->commands[] = $this->tableAlias;
    }
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

  private function renderWhereClause(): void
  {
    if (empty($this->conditions)) {
      return;
    }

    $this->commands[] = 'WHERE';
    $this->commands[] = join(' ', $this->conditions);
  }

  private function renderAggregatedClause(): void
  {
    if ($this->indAggregate === false && empty($this->columnsAggregated)) {
      return;
    }

    if (empty($this->columns)) {
      return;
    }

    $this->commands[] = 'GROUP BY';
    foreach ($this->columns as $column) {
      $this->commands[] = "{$column},";
    }

    $this->removeCommonsLastCommand();
  }

  private function renderHavingClause(): void
  {
    if (empty($this->having)) {
      return;
    }

    $this->commands[] = 'HAVING';
    $this->commands[] = join(' ', $this->having);
  }

  private function renderOrderByClause(): void
  {
    if (empty($this->orderBy)) {
      return;
    }

    $this->commands[] = 'ORDER BY';
    $this->commands[] = join(' ', $this->orderBy);

    $this->removeCommonsLastCommand();
  }

  private function renderLimitClause(): void
  {
    if (empty($this->limit)) {
      return;
    }

    if ($this->limit < 0) {
      return;
    }

    $this->commands[] = 'LIMIT';
    $this->commands[] = $this->limit;
  }

  private function renderOffsetClause(): void
  {
    if (empty($this->offset)) {
      return;
    }

    $this->commands[] = 'OFFSET';
    $this->commands[] = $this->offset;
  }

  public function render(): self
  {
    $this->commands = [];

    $this->renderWithClause();

    $this->commands[] = 'SELECT';

    if (!empty($this->maxExecutionTime)) {
      $this->commands[] = "/*+ MAX_EXECUTION_TIME({$this->maxExecutionTime}) */";
    }

    if ($this->indDistinct) {
      $this->commands[] = 'DISTINCT';
    }

    $this->renderColumns();
    $this->renderFrom();
    $this->renderJoins();
    $this->renderWhereClause();
    $this->renderAggregatedClause();
    $this->renderHavingClause();
    $this->renderOrderByClause();
    $this->renderLimitClause();
    $this->renderOffsetClause();

    $this->sql = join(' ', $this->commands);
    $this->indRendered = true;

    return $this;
  }

  public function getQuery(): ?string
  {
    if (!$this->indRendered) {
      $this->render();
    }

    return $this->sql;
  }
}
