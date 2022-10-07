<?php

namespace Conn\QB;

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

  protected bool $indAssoc;

  protected const COLUMN_SEPARATOR = ',';

  public function __construct(null | string $table = null)
  {
    if (!empty($table)) {
      $this->table($table);
    }

    $this->pendingRow = [];
    $this->pendingRows = [];

    $this->indAssoc = false;
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
    $this->pendingRow[$column] = $value;

    if (!$this->indAssoc) {
      return $this;
    }

    if (!in_array($column, $this->columns)) {
      $this->columns[] = $column;
    }

    return $this;
  }

  public function setColumns(array $columns): self
  {
    $this->columns = $columns;

    return $this;
  }

  public function setLimitPendingRows(int $limit): self
  {
    if ($limit < 1) {
      throw new Exception('Limit must be greater than 0');
    }

    $this->limitPendingRows = $limit;

    return $this;
  }

  public function indAssoc(bool $indAssoc = true): self
  {
    $this->indAssoc = $indAssoc;

    return $this;
  }

  public function addRow(array $row): self
  {
    if ($this->numPendingRows == $this->limitPendingRows) {
      $this->flush();
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

  public function prepareRow(): self
  {
    if (empty($this->pendingRow)) {
      return $this;
    }

    $this->addRow($this->pendingRow);
    $this->pendingRow = [];

    return $this;
  }

  public function table(string $table): self
  {
    $this->table = $table;

    return $this;
  }

  public function getColumns(): array
  {
    return $this->columns;
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

  private function joinValuesToCommands(array $row): void
  {
    array_walk($row, function(&$value) {
      $value = ($value === null) ? 'NULL' : "'{$value}'";
    });

    $joinedColumns = implode(self::COLUMN_SEPARATOR, $row);
    $this->commands[] = "({$joinedColumns}),";
  }

  private function renderRowsAssoc(): void
  {
    foreach($this->pendingRows as $row) {
      $row = array_combine_keys($this->columns, $row);

      $this->joinValuesToCommands($row);
    }
  }

  private function renderRowsNormal(): void
  {
    foreach($this->pendingRows as $row) {
      $values = array_values($row);

      $this->joinValuesToCommands($values);
    }
  }

  private function renderRows(): void
  {
    $this->prepareRow();

    $this->commands[] = 'VALUES';

    if ($this->indAssoc) {
      $this->renderRowsAssoc();
    } else {
      $this->renderRowsNormal();
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
