<?php

namespace Cautnew\QB;

use Cautnew\QB\QB;
use Exception;

/**
 * Prepare CREATE TABLE commands. You can set all details about the table.
 * definitions = [
 *   "column_name" => [
 *     "type" => "int" | "decimal" | "double" | "boolean" | "date" | "datetime" | "timestamp" | "time" | "year" | "char" | "varchar" | "text" | "tinytext" | "mediumtext" | "longtext" | "binary" | "varbinary" | "blob" | "tinyblob" | "mediumblob" | "longblob" | "enum" | "set",
 *     "length" => int (Optional. For type varchar, default 255. For type decimal, default is 10),
 *     "comment" => string (Optional),
 *     "default" => string | int (Optional. Default is null),
 *     "is_null" => true | false (Optional. Default is true),
 *     "is_primary_key" => true | false (Optional. Default is false),
 *     "is_auto_increment" => true | false (Optional. If true type must be int. Default is false),
 *     "is_unique" => true | false (Optional. Default is false),
 *     "check" => string (Optional. Default is null)
 *   ]
 * ]
 */
class CREATE_TABLE extends QB
{
  private string $table;
  private array $definitions = [];
  private array $triggers = [];
  private array $foreignKeys = [];
  private array $primaryKeys = [];
  private array $uniques = [];
  private string $principalPrimaryKey;
  private string $tableEngine;
  private string $tableSpace;
  private bool $isTemporary;
  private string $dataDirectory;

  private const PERMITTED_TYPES = [
    "int", "decimal", "double", "boolean", "date", "datetime", "timestamp", "time", "year", "char", "varchar", "text", "tinytext", "mediumtext", "longtext", "binary", "varbinary", "blob", "tinyblob", "mediumblob", "longblob", "enum", "set"
  ];

  private const TYPE_NO_LENGTH = [
    "date", "datetime", "timestamp", "time", "year", "text", "mediumtext", "longtext", "binary", "varbinary", "blob", "tinyblob", "mediumblob", "longblob"
  ];

  private const PERMITTED_CONSTRAINT_TYPES = [
    "primary_key", "foreign_key", "unique", "enum", "check", "index"
  ];

  public function __construct(string $table, array $definitions = []) {
    $this->table = $table;

    if (!empty($definitions)) {
      $this->definitions = $definitions;
    }
  }

  public function __toString() {
    return $this->render();
  }

  private function render(): string {
    if (empty($this->table)) {
      throw new Exception("Table name is required.");
    }

    $query = ($this->isTemporaryTable()) ? "CREATE TEMPORARY TABLE " : "CREATE TABLE ";

    $query .= $this->getTableName() . " (";

    foreach($this->getDefinitions() as $column => $definitions) {
      $query .= "`$column` ";
      if (isset($definitions['type'])) {
        $definition = $definitions['type'];

        if (isset($definitions['length'])) {
          $definition .= "(" . $definitions['length'] . ")";
        } else if ($definitions['type'] == "varchar") {
          $definition .= "(255)";
        } else if ($definitions['type'] == "decimal") {
          $definition .= "(10,2)";
        }
      }

      if ($this->isColumnNull($column)) {
        $definition .= " NULL";
      } else {
        $definition .= " NOT NULL";
      }

      if ($this->getColumnDefaultValue($column) !== null) {
        $definition .= " DEFAULT " . $this->getColumnDefaultValue($column);
      }

      if ($this->isColumnAutoIncrement($column)) {
        $definition .= " AUTO_INCREMENT";
      }
      
      if ($this->isColumnPrimaryKey($column)) {
        $definition .= " PRIMARY KEY";
      }

      if ($this->isColumnUnique($column)) {
        $definition .= " UNIQUE";
      }

      if ($this->getColumnComment($column) !== null) {
        $definition .= " COMMENT '{$this->getColumnComment($column)}'";
      }

      if ($this->getColumnCheck($column) !== null) {
        $definition .= " CHECK({$this->getColumnCheck($column)})";
      }

      $query .= $definition . ",";
    }

    foreach($this->getPrimaryKeys() as $primaryKey) {
      $query .= "PRIMARY KEY (`$primaryKey`),";
    }

    foreach($this->getForeignKeys() as $constraintName => $foreignKey) {
      $query .= "CONSTRAINT $constraintName FOREIGN KEY (`{$foreignKey['column_name']}`) REFERENCES `{$foreignKey['reference_table']}`(`{$foreignKey['reference_column']}`),";
    }

    foreach($this->getConstraints() as $constraintName => $constraint) {
      $query .= "CONSTRAINT $constraintName {$constraint['type']} {$constraint['definitions']},";
    }

    $query = rtrim($query, ",") . ");\n";

    if ($this->getTriggers() !== null) {
      foreach($this->getTriggers() as $trigger) {
        $query .= $trigger;
      }
    }

    return $query;
  }

  public function getTableName(): ?string {
    return $this->table;
  }

  public function getTable(): ?string {
    return $this->getTableName();
  }

  public function setDefinitions(array $definitions): self {
    $this->definitions = $definitions;

    return $this;
  }

  public function getDefinitions(): ?array {
    if (empty($this->definitions)) {
      return null;
    }

    return $this->definitions;
  }

  public function setTableEngine(string $tableEngine): self {
    $this->tableEngine = $tableEngine;

    return $this;
  }

  public function getTableEngine(): ?string {
    return $this->tableEngine ?? null;
  }

  public function setTemporaryTable(bool $isTemporary=true): self {
    $this->isTemporary = $isTemporary;

    return $this;
  }

  public function isTemporaryTable(): bool {
    return $this->isTemporary ?? false;
  }

  public function setTableSpace(string $tableSpace): self {
    $this->tableSpace = $tableSpace;

    return $this;
  }

  public function getTableSpace(): ?string {
    return $this->tableSpace ?? null;
  }

  public function setDataDirectory(string $dataDirectory): self {
    $this->dataDirectory = $dataDirectory;

    return $this;
  }

  public function getDataDirectory(): ?string {
    return $this->dataDirectory ?? null;
  }

  public function setColumnDefinitions(string $columnName, array $definitions): self {
    $this->definitions[$columnName] = $definitions;

    return $this;
  }

  public function setColumnDefinition(string $columnName, string $definitionName, $definition): self {
    if (!array_key_exists($columnName, $this->definitions)) {
      $this->definitions[$columnName] = [];
    }

    $this->definitions[$columnName][$definitionName] = $definition;

    return $this;
  }

  public function getColumnDefinition(string $columnName, string $definitionName) {
    if (!array_key_exists($columnName, $this->definitions)) {
      return null;
    }

    return $this->definitions[$columnName][$definitionName] ?? null;
  }

  public function getColumnDefinitions(string $columnName): ?array {
    if (!array_key_exists($columnName, $this->definitions)) {
      return null;
    }

    return $this->definitions[$columnName] ?? null;
  }

  public function setColumnType(string $columnName, string $type): self {
    return $this->setColumnDefinition($columnName, 'type', $type);
  }

  public function setColumnLength(string $columnName, int $length): self {
    return $this->setColumnDefinition($columnName, 'length', $length);
  }

  public function setColumnComment(string $columnName, string $comment): self {
    return $this->setColumnDefinition($columnName, 'comment', $comment);
  }

  public function getColumnComment(string $columnName): ?string {
    return $this->getColumnDefinition($columnName, 'comment') ?? null;
  }

  public function getColumnCheck(string $columnName): ?string {
    return $this->getColumnDefinition($columnName, 'check') ?? null;
  }

  public function setColumnIsNull(string $columnName, bool $isNull=true): self {
    return $this->setColumnDefinition($columnName, 'is_null', $isNull);
  }

  public function isColumnNull(string $columnName): bool {
    return $this->getColumnDefinition($columnName, 'is_null') ?? true;
  }

  public function setColumnInt(string $columnName): self {
    return $this->setColumnType($columnName, 'int');
  }

  public function setColumnVarchar(string $columnName, int $length=255): self {
    return $this->setColumnType($columnName, 'varchar')->setColumnLength($columnName, $length);
  }

  public function getColumnDefaultValue(string $columnName) {
    return $this->getColumnDefinition($columnName, 'default') ?? null;
  }

  public function setPrimaryKey(string $columnName, bool $isPrimaryKey=true): self {
    return $this->setColumnDefinition($columnName, 'is_primary_key', $isPrimaryKey);
  }

  public function isColumnPrimaryKey(string $columnName): bool {
    return $this->getColumnDefinition($columnName, 'is_primary_key') ?? false;
  }

  public function getPrincipalPrimaryKey(): ?string {
    return $this->principalPrimaryKey ?? null;
  }

  public function setPrincipalPrimaryKey(string $columnName): self {
    $this->principalPrimaryKey = $columnName;

    return $this;
  }

  public function isColumnAutoIncrement(string $columnName): bool {
    return $this->getColumnDefinition($columnName, 'is_auto_increment') ?? false;
  }

  public function isColumnUnique(string $columnName): bool {
    return $this->getColumnDefinition($columnName, 'is_unique') ?? false;
  }

  public function setTriggers(array $triggers): self {
    $this->triggers = $triggers;

    return $this;
  }

  public function addTrigger(CREATE_TRIGGER $trigger): self {
    $this->triggers[$trigger->getName()] = $trigger;

    return $this;
  }

  public function setTrigger(string $triggerName, CREATE_TRIGGER $trigger): self {
    $this->triggers[$triggerName] = $trigger;

    return $this;
  }

  public function removeTrigger(string $triggerName): self {
    if (isset($this->triggers[$triggerName])) {
      unset($this->triggers[$triggerName]);
    }

    return $this;
  }

  public function getTriggers(): ?array {
    return $this->triggers ?? null;
  }

  public function setConstraints(array $constraints): self {
    $this->constraints = $constraints;

    return $this;
  }

  public function setConstraint(string $constraintName, string $constraintType, string $constraintDefinitions): self {
    $this->constraints[$constraintName] = [
      'type' => strtoupper($constraintType),
      'definitions' => $constraintDefinitions
    ];

    return $this;
  }

  public function getConstraints(): ?array {
    return $this->constraints ?? null;
  }
  
  public function setForeignKeys(array $foreignKeys): self {
    $this->foreignKeys = $foreignKeys;

    return $this;
  }
  
  public function setForeignKey(string $constraintName, string $columnName, string $referenceTable, string $referenceColumn): self {
    $this->foreignKeys[$constraintName] = [
      'column_name' => $columnName,
      'reference_table' => $referenceTable,
      'reference_column' => $referenceColumn
    ];

    return $this;
  }

  public function setPrimaryKeys(array $primaryKeys): self {
    $this->primaryKeys = $primaryKeys;

    return $this;
  }
}
