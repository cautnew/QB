<?php

namespace Conn\QB;

use Conn\QB\QB;
use PDO;
use Exception;

class DELETE extends QB
{
  protected string $table;

  protected ?string $tableAlias;
  protected string $sql;

  protected ?array $orderBy = [];
  protected ?int $limit;
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

  public function join(string $type, $table, ?string $alias): self
  {
    $type = strtoupper(trim($type));

    if (!in_array($type, self::PERMITTED_JOIN_TYPES)) {
      throw new Exception("Join type not recognized.");
    }

    $this->joins[$alias] = [
      "type" => $type,
      "table" => $table,
      "alias" => $alias
    ];

    $this->indRendered = false;

    return $this;
  }

  public function innerJoin($table, ?string $alias): self
  {
    return $this->join('INNER', $table, $alias);
  }

  public function leftJoin($table, ?string $alias): self
  {
    return $this->join('LEFT', $table, $alias);
  }

  public function rightJoin($table, ?string $alias): self
  {
    return $this->join('RIGHT', $table, $alias);
  }

  public function outterJoin($table, ?string $alias): self
  {
    return $this->join('OUTTER', $table, $alias);
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

  public function render(): self
  {
    $this->commands = ['DELETE', $this->table];

    $this->renderJoins();
    $this->renderWhereClause();
    $this->renderOrderByClause();
    $this->renderLimitClause();

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
