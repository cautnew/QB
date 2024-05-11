<?php

namespace Cautnew\QB;

use Cautnew\QB\QB;
use Exception;

/**
 * Prepare CREATE TRIGGER commands.
 * Try to follow the best practices according to the name of the trigger.
 * This is an example for the trigger name: "tablename_before_insert".
 */
class CREATE_TRIGGER extends QB
{
  private string $triggerName;
  private string $table;
  private string $when;
  private string $operation;
  private string $instruction;

  private const TRIGGER_WHEN = [
    'BEFORE',
    'AFTER'
  ];

  private const TRIGGER_OPERATION = [
    'INSERT',
    'UPDATE',
    'DELETE'
  ];

  public function __construct(string $table, ?string $triggerName = null, ?string $when = null, ?string $operation = null, ?string $instruction = null)
  {
    $this->setTableName($table);
    $this->setTriggerName($triggerName);
    $this->setWhen($when);
    $this->setOperation($operation);
    $this->setInstruction($instruction);
  }

  public function __toString()
  {
    return $this->render();
  }

  public function getQuery(): string
  {
    return $this->render();
  }

  private function render(): string
  {
    if (empty($this->table)) {
      throw new Exception("Table name is required.");
    }

    $query = "CREATE TRIGGER ";
    $query .= "{$this->getTriggerName()} {$this->getWhen()} {$this->getOperation()} ";
    $query .= "ON {$this->getTableName()} FOR EACH ROW {$this->getInstruction()};";

    return $query;
  }

  public function setTriggerName(?string $triggerName): self
  {
    if (empty($triggerName)) {
      $triggerName = "trigger_{$this->getTableName()}_{$this->getWhen()}_{$this->getOperation()}";
    }

    $this->triggerName = $triggerName;

    return $this;
  }

  public function setName(?string $triggerName): self
  {
    return $this->setTriggerName($triggerName);
  }

  public function getTriggerName(): ?string
  {
    if (empty($this->triggerName)) {
      $this->setTriggerName(null);
    }

    return $this->triggerName;
  }

  public function getName(): ?string
  {
    return $this->getTriggerName();
  }

  public function setTableName(string $table): self
  {
    $this->table = $table;

    return $this;
  }

  public function setTable(string $table): self
  {
    return $this->setTableName($table);
  }

  public function getTableName(): string
  {
    return $this->table;
  }

  public function getTable(): string
  {
    return $this->getTableName();
  }

  public function setWhen(?string $triggerWhen): self
  {
    $triggerWhen = strtoupper($triggerWhen);

    if (empty($triggerWhen)) {
      $triggerWhen = "BEFORE";
    } elseif (!in_array($triggerWhen, self::TRIGGER_WHEN)) {
      throw new Exception("Invalid trigger when.");
    }

    $this->when = $triggerWhen;

    return $this;
  }

  public function getWhen(): string
  {
    if (!isset($this->when) || empty($this->when)) {
      $this->setWhen(null);
    }

    return $this->when;
  }

  public function setOperation(?string $triggerOperation): self
  {
    $triggerOperation = strtoupper($triggerOperation);

    if (empty($triggerOperation)) {
      $triggerOperation = "INSERT";
    } elseif (!in_array($triggerOperation, self::TRIGGER_OPERATION)) {
      throw new Exception("Invalid trigger operation.");
    }

    $this->operation = $triggerOperation;

    return $this;
  }

  public function getOperation(): string
  {
    if (!isset($this->operation) || empty($this->operation)) {
      $this->setOperation(null);
    }

    return $this->operation;
  }

  public function setInstruction(string $instruction): self
  {
    $this->instruction = $instruction;

    return $this;
  }

  public function getInstruction(): string
  {
    if (!isset($this->instruction) || empty($this->instruction)) {
      $this->setInstruction('');
    }

    return $this->instruction;
  }

  public function setTrigger(string $triggerInstruction, ?string $triggerWhen = null, ?string $triggerOperation = null): self
  {
    $this->setWhen($triggerWhen);
    $this->setOperation($triggerOperation);
    $this->setInstruction($triggerInstruction);

    return $this;
  }

  public function setBeforeInsert(string $triggerInstruction): self
  {
    return $this->setTrigger($triggerInstruction, 'BEFORE', 'INSERT');
  }

  public function setBeforeUpdate(string $triggerInstruction): self
  {
    return $this->setTrigger($triggerInstruction, 'BEFORE', 'UPDATE');
  }

  public function setBeforeDelete(string $triggerInstruction): self
  {
    return $this->setTrigger($triggerInstruction, 'BEFORE', 'DELETE');
  }

  public function setAfterInsert(string $triggerInstruction): self
  {
    return $this->setTrigger($triggerInstruction, 'AFTER', 'INSERT');
  }

  public function setAfterUpdate(string $triggerInstruction): self
  {
    return $this->setTrigger($triggerInstruction, 'AFTER', 'UPDATE');
  }

  public function setAfterDelete(string $triggerInstruction): self
  {
    return $this->setTrigger($triggerInstruction, 'AFTER', 'DELETE');
  }
}
