--TEST--
PDO PgSQL a competing query cannot discard unread rows from a chunked result
--EXTENSIONS--
pdo_pgsql
--SKIPIF--
<?php
require __DIR__ . '/config.inc';
require __DIR__ . '/../../../ext/pdo/tests/pdo_test.inc';
PDOTest::skip();
if (!defined('Pdo\Pgsql::ATTR_CHUNK_SIZE')) die('skip libpq >= 17 required');
?>
--FILE--
<?php

require __DIR__ . '/../../../ext/pdo/tests/pdo_test.inc';
$pdo = PDOTest::test_factory(__DIR__ . '/common.phpt');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(Pdo\Pgsql::ATTR_CHUNK_SIZE, 5);

$first = $pdo->prepare("SELECT * FROM generate_series(1, 10)");
$first->execute();
echo "first row: ", $first->fetch(PDO::FETCH_NUM)[0], "\n";

try {
    $pdo->query("SELECT * FROM generate_series(1, 10)")->fetch();
} catch (PDOException $e) {
    echo $e->getCode(), "\n";
}

$rest = [];
while (($row = $first->fetch(PDO::FETCH_NUM))) {
    $rest[] = $row[0];
}
echo "rows afterwards: ", var_export(implode(',', $rest), true), "\n";
echo "error code: ", $first->errorCode(), "\n";
?>
--EXPECT--
first row: 1
HY000
rows afterwards: '2,3,4,5,6,7,8,9,10'
error code: 00000
