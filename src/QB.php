<?php

namespace Cautnew\QB;

use PDO;
use PDOStatement;

class QB
{
  private PDO $con;
  private PDOStatement $stmt;
  private array $params;
  private string $sql;

  protected array $commands = [];
  protected array $joins = [];

  protected bool $indRendered = false;

  public function __construct(PDO | null $con = null) {
    $this->sql = '';

    if ($con !== null) {
      $this->setConn($con);
    }
  }

  protected function removeCommonsLastCommand(): void
  {
    $indexLastCommand = array_key_last($this->commands);
    $this->commands[$indexLastCommand] = rtrim($this->commands[$indexLastCommand], ',');
  }

  public function getQuery(): ?string
  {
    if (!$this->indRendered) {
      return null;
    }

    return $this->sql;
  }

  public function setConn(PDO $con): self
  {
    $this->con = $con;

    return $this;
  }

  public function setParams(array $params): self
  {
    $this->params = $params;

    return $this;
  }

  public function run(): PDOStatement
  {
    $this->stmt = $this->con->prepare($this->getQuery());
    $this->stmt->execute($this->params);

    return $this->stmt;
  }
}
