<?php

namespace Cautnew\QB;

use Exception;

class INSERT extends QB
{
  protected string $table;

  protected string $sql;

  protected array $columns = [];
  protected array $pendingRow;
  protected array $pendingRows;
  protected array $commands = [];

  protected int $numTotalInsertedRows = 0;
  protected int $numPendingRows = 0;
  protected int $limitPendingRows = 558;

  protected string $callableOnFlush;

  private bool $indAssoc = false;

  protected const COLUMN_SEPARATOR = ',';

  public function __construct(null | string $table = null)
  {
    parent::__construct();
    if (!empty($table)) {
      $this->table($table);
    }

    $this->pendingRow = [];
    $this->pendingRows = [];
  }

  public function __toString()
  {
    return $this->getQuery();
  }

  public function __set(string $column, $value): void
  {
    $this->set($column, $value);
  }

  public function set(string $column, $value): self
  {
    $this->addColumn($column);
    $this->pendingRow[$column] = $value;

    return $this;
  }

  public function setIndAssoc(bool $indAssoc): self
  {
    $this->indAssoc = $indAssoc;

    return $this;
  }

  public function getIndAssoc(): bool
  {
    return $this->indAssoc;
  }

  public function isAssoc(): bool
  {
    return $this->getIndAssoc();
  }

  public function setColumns(array $columns): self
  {
    $this->columns = $columns;

    return $this;
  }

  public function getColumns(): array
  {
    return $this->columns;
  }

  public function addColumn(string $columnName): self
  {
    if (empty($columnName)) {
      throw new Exception("Not valid column name. Empty value passed.");
    }

    if (!$this->isColumnSet($columnName)) {
      $this->columns[] = $columnName;
    }

    return $this;
  }

  public function isColumnSet(string $columnName): bool
  {
    return in_array($columnName, $this->columns);
  }

  public function setLimitPendingRows(int $limit): self
  {
    if ($limit < 1) {
      throw new Exception('Limit must be greater than 0');
    }

    $this->limitPendingRows = $limit;

    return $this;
  }

  public function addRow(array $row): self
  {
    if ($this->numPendingRows == $this->limitPendingRows) {
      $this->flush();
    }

    if ($this->isAssoc()) {
      $columns = array_keys($row);
      foreach($columns as $column) {
        $this->addColumn($column);
      }
    }

    $this->pendingRows[] = $row;
    $this->numPendingRows += 1;

    return $this;
  }

  public function addRows(array $rows): self
  {
    foreach ($rows as $row) {
      $this->addRow($row);
    }

    return $this;
  }

  public function clearPendingRow(): self
  {
    $this->pendingRow = [];

    return $this;
  }

  public function clearPendingRows(): self
  {
    $this->pendingRows = [];
    $this->numPendingRows = 0;

    return $this;
  }

  public function clearRows(): self
  {
    $this->clearPendingRow();
    $this->clearPendingRows();

    $this->numTotalInsertedRows = 0;

    return $this;
  }

  public function prepareRow(): self
  {
    if (empty($this->pendingRow)) {
      return $this;
    }

    $this->addRow($this->pendingRow);
    $this->clearPendingRow();

    return $this;
  }

  public function table(string $table): self
  {
    $this->table = $table;

    return $this;
  }

  public function flush()
  {
    if (isset($this->callableOnFlush)) {
      call_user_func($this->callableOnFlush, $this);
    }

    $this->numTotalInsertedRows += $this->numPendingRows;
    $this->pendingRows = [];
    $this->numPendingRows = 0;

    return $this;
  }

  public function onFlush(callable $callback): self
  {
    if (!is_callable($callback)) {
      throw new Exception('Callback must be callable');
    }

    $this->callableOnFlush = $callback;
    return $this;
  }

  private function renderColumns(): void
  {
    if (empty($this->columns)) {
      return;
    }

    $columns = '(' . join(self::COLUMN_SEPARATOR, $this->columns) . ')';
    $this->commands[] = $columns;
  }

  private function joinValuesToCommands(array $values): void
  {
    array_walk($values, function(&$value) {
      $value = (empty($value) || $value === null || $value == "null" || $value == "NULL") ? 'NULL' : $value;
    });

    $joinedColumns = implode(self::COLUMN_SEPARATOR, $values);

    $this->commands[] = "($joinedColumns),";
  }

  private function joinRowToCommands(array $row): void
  {
    if (!$this->isAssoc()) {
      $this->joinValuesToCommands(array_values($row));

      return;
    }

    $orderedValues = [];
    foreach($this->columns as $column) {
      $orderedValues[] = $row[$column] ?? null;
    }

    $this->joinValuesToCommands($orderedValues);
  }

  private function renderRows(): void
  {
    $this->prepareRow();

    $this->commands[] = 'VALUES';

    foreach($this->pendingRows as $row) {
      $this->joinRowToCommands($row);
    }

    $this->removeCommonsLastCommand();
  }

  public function render(): self
  {
    if (empty($this->table)) {
      throw new Exception('Table name is not set.');
    }

    $this->commands = ['INSERT INTO', $this->table];

    $this->renderColumns();
    $this->renderRows();

    $this->sql = join(' ', $this->commands);
    $this->indRendered = true;

    return $this;
  }

  public function getQuery(): string
  {
    if (!$this->indRendered) {
      $this->render();
    }

    return $this->sql;
  }
}
