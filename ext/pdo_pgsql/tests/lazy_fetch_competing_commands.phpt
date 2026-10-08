--TEST--
PDO PgSQL competing commands preserve unread lazy rows and report an error
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
$pdo = PDOTest::test_factory(__DIR__ . '/common.phpt', Pdo\Pgsql::class);
$pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
$pdo->exec('CREATE TEMPORARY TABLE lazy_competing (id int)');

$commands = [
    'query' => fn () => $pdo->query('SELECT 42'),
    'exec' => fn () => $pdo->exec('INSERT INTO lazy_competing VALUES (42)'),
    'prepared' => fn () => $pdo->prepare('SELECT 42')->execute(),
    'commit' => fn () => $pdo->commit(),
    'rollback' => fn () => $pdo->rollBack(),
    'lastInsertId' => fn () => $pdo->lastInsertId(),
    'copyFromArray' => fn () => $pdo->copyFromArray('lazy_competing', ['42']),
    'copyToArray' => fn () => $pdo->copyToArray('lazy_competing'),
];
foreach ([PDO::ERRMODE_SILENT, PDO::ERRMODE_WARNING, PDO::ERRMODE_EXCEPTION] as $mode) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, $mode);
    foreach ($commands as $label => $command) {
        $pdo->beginTransaction();
        $first = $pdo->prepare('SELECT generate_series(1, 3)', [PDO::ATTR_PREFETCH => 0]);
        $first->execute();
        $first->fetchColumn();
        $warnings = [];
        set_error_handler(function ($severity, $message) use (&$warnings) { $warnings[] = $message; return true; });
        try {
            $result = $command();
            $rejected = $result === false;
        } catch (PDOException $error) {
            $rejected = $mode === PDO::ERRMODE_EXCEPTION && $error->getCode() === 'HY000';
        } finally {
            restore_error_handler();
        }
        if (!$rejected || count($warnings) !== ($mode === PDO::ERRMODE_WARNING ? 1 : 0)) {
            throw new Exception("Competing command was not rejected correctly: $mode/$label");
        }
        if ($first->fetchAll(PDO::FETCH_COLUMN) !== [2, 3] || $first->errorCode() !== '00000') {
            throw new Exception("Unread rows were lost: $mode/$label");
        }
        $pdo->rollBack();
    }
    echo "mode $mode: preserved\n";
}

foreach ([[], [PDO::ATTR_EMULATE_PREPARES => true], [Pdo\Pgsql::ATTR_DISABLE_PREPARES => true]] as $options) {
    $options[PDO::ATTR_PREFETCH] = 0;
    $first = $pdo->prepare('SELECT generate_series(1, 3)', $options);
    $first->execute();
    $first->fetchColumn();
    $first->closeCursor();
    $pdo->exec('INSERT INTO lazy_competing VALUES (7)');
    // Re-executing the same statement intentionally replaces its own result.
    $first->execute();
    $first->execute();
    if ($first->fetchAll(PDO::FETCH_COLUMN) !== [1, 2, 3]) {
        throw new Exception('Statement was not reusable');
    }
}
echo "close and re-execute: OK\n";
// Empty and command-only results have no unread rows to protect.
$pdo->setAttribute(PDO::ATTR_PREFETCH, 0);
$empty = $pdo->query('SELECT 1 WHERE false');
$insert = $pdo->prepare('INSERT INTO lazy_competing VALUES (9)');
$insert->execute();
$pdo->exec('DELETE FROM lazy_competing WHERE id = 9');
$insert->execute();
$insert->closeCursor();
$pdo->exec('DELETE FROM lazy_competing WHERE id = 9');
echo "empty and command results: OK\n";
var_dump($pdo->query('SELECT count(*) FROM lazy_competing')->fetchColumn());
?>
--EXPECT--
mode 0: preserved
mode 1: preserved
mode 2: preserved
close and re-execute: OK
empty and command results: OK
int(3)
