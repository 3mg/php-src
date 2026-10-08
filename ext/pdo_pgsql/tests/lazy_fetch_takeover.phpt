--TEST--
PDO PgSQL a competing lazy query is rejected without losing the first result
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
$pdo->setAttribute(PDO::ATTR_PREFETCH, 0);

$first = $pdo->prepare("VALUES (1), (2)");
$first->execute();

try {
    $pdo->prepare("VALUES (1), (2)")->execute();
} catch (PDOException $e) {
    echo $e->getCode(), "\n";
}

var_dump($first->fetchAll(PDO::FETCH_NUM));
?>
--EXPECT--
HY000
array(2) {
  [0]=>
  array(1) {
    [0]=>
    string(1) "1"
  }
  [1]=>
  array(1) {
    [0]=>
    string(1) "2"
  }
}
