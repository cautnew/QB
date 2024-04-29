<?php

use Cautnew\QB\CONDITIONS;
use Cautnew\QB\CONDITION as COND;
use Cautnew\QB\UPDATE;

error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../src/CONDITIONS.php';
require __DIR__ . '/../src/GROUP.php';
require __DIR__ . '/../src/CONDITION.php';

$cond3 = (new COND('g'))->greaterThen('h');
$cond = (new COND('a'))
  ->equals('b')
  ->and((new COND('x'))->isnull())
  ->or((new COND('y'))->greaterThen('z'))
  ->or($cond3);

echo $cond . "\n";
