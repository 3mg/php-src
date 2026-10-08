--TEST--
PDO PgSQL a buffered query cannot take over an active lazy result
--EXTENSIONS--
pdo_pgsql
--SKIPIF--
<?php
require __DIR__ . '/config.inc';
require __DIR__ . '/../../../ext/pdo/tests/pdo_test.inc';
PDOTest::skip();
?>
--FILE--
<?php

require __DIR__ . '/../../../ext/pdo/tests/pdo_test.inc';
$pdo = PDOTest::test_factory(__DIR__ . '/common.phpt');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$first = $pdo->prepare("VALUES (1), (2), (3)", [PDO::ATTR_PREFETCH => 0]);
$first->execute();
$first->fetch();

try {
    $pdo->prepare("VALUES (99)")->execute();
} catch (PDOException $e) {
    echo $e->getCode(), "\n";
}
var_dump($first->fetchAll(PDO::FETCH_COLUMN));

$third = $pdo->prepare("VALUES (777), (888)", [PDO::ATTR_PREFETCH => 0]);
$third->execute();

var_dump($first->fetch(PDO::FETCH_NUM));
var_dump($third->fetchAll(PDO::FETCH_COLUMN));
?>
--EXPECT--
HY000
array(2) {
  [0]=>
  string(1) "2"
  [1]=>
  string(1) "3"
}
bool(false)
array(2) {
  [0]=>
  string(3) "777"
  [1]=>
  string(3) "888"
}
