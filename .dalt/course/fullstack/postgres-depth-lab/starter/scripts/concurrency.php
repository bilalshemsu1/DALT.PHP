<?php

declare(strict_types=1);

use Core\Database;

/**
 * Two connections, one row. Everything below is sequenced by hand so each step's effect
 * is observable rather than a matter of timing luck.
 */
$root = getenv('DALT_REPOSITORY_ROOT') ?: __DIR__;
while (!is_file($root . '/vendor/autoload.php')) {
    $parent = dirname($root);
    if ($parent === $root) {
        throw new RuntimeException('Run this lab from inside the DALT repository.');
    }
    $root = $parent;
}

define('BASE_PATH', $root . '/');
require $root . '/vendor/autoload.php';
require $root . '/framework/Core/functions.php';

$settings = [
    'driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55433,
    'dbname' => 'dalt_depth', 'username' => 'dalt',
    'password' => 'dalt-course-local', 'charset' => 'utf8',
];

// DALT's Database is a thin wrapper over one PDO connection, so two sessions means two
// of them. Anything sharing a connection shares a transaction.
$sessionA = (new Database($settings))->getConnection();
$sessionB = (new Database($settings))->getConnection();

$issueId = (int) $sessionA->query('SELECT id FROM issues ORDER BY id LIMIT 1')->fetchColumn();
$sessionA->exec("UPDATE issues SET priority = 1 WHERE id = {$issueId}");

function priorityOf(PDO $session, int $issueId): int
{
    return (int) $session->query("SELECT priority FROM issues WHERE id = {$issueId}")->fetchColumn();
}

echo "--- 1. two sessions read, both write, one increment disappears ---\n";
$sessionA->beginTransaction();
$sessionB->beginTransaction();
$readByA = priorityOf($sessionA, $issueId);
$readByB = priorityOf($sessionB, $issueId);
$sessionA->exec("UPDATE issues SET priority = {$readByA} + 1 WHERE id = {$issueId}");
$sessionA->commit();
$sessionB->exec("UPDATE issues SET priority = {$readByB} + 1 WHERE id = {$issueId}");
$sessionB->commit();
printf(
    "A read %d, B read %d, both added 1. Expected %d, actual %d.\n",
    $readByA,
    $readByB,
    $readByA + 2,
    priorityOf($sessionA, $issueId),
);

echo "\n--- 2. SELECT ... FOR UPDATE makes the second session wait ---\n";
$sessionA->exec("UPDATE issues SET priority = 1 WHERE id = {$issueId}");
$sessionA->beginTransaction();
$sessionA->query("SELECT priority FROM issues WHERE id = {$issueId} FOR UPDATE");

// Without a timeout B would simply block until A commits, which is correct and invisible.
$sessionB->exec("SET lock_timeout = '300ms'");
$sessionB->beginTransaction();
try {
    $sessionB->query("SELECT priority FROM issues WHERE id = {$issueId} FOR UPDATE");
    echo "B acquired the lock immediately, which should not happen.\n";
} catch (PDOException $error) {
    printf("B could not take the row lock: SQLSTATE %s.\n", $error->getCode());
    $sessionB->rollBack();
}

$sessionA->exec("UPDATE issues SET priority = 2 WHERE id = {$issueId}");
$sessionA->commit();

$sessionB->beginTransaction();
$readByB = (int) $sessionB->query("SELECT priority FROM issues WHERE id = {$issueId} FOR UPDATE")->fetchColumn();
$sessionB->exec("UPDATE issues SET priority = {$readByB} + 1 WHERE id = {$issueId}");
$sessionB->commit();
printf("B then read the committed %d and wrote %d. No update was lost.\n", $readByB, priorityOf($sessionA, $issueId));

echo "\n--- 3. SERIALIZABLE refuses a result no serial order could produce ---\n";
$sessionA->exec("SET default_transaction_isolation = 'serializable'");
$sessionB->exec("SET default_transaction_isolation = 'serializable'");
$sessionA->beginTransaction();
$sessionB->beginTransaction();

// Both sessions decide what to write by counting the same set of rows.
$countByA = (int) $sessionA->query("SELECT count(*) FROM issues WHERE project_id = 1 AND priority = 3")->fetchColumn();
$countByB = (int) $sessionB->query("SELECT count(*) FROM issues WHERE project_id = 1 AND priority = 3")->fetchColumn();
$sessionA->exec("UPDATE issues SET priority = 4 WHERE id = {$issueId}");
$sessionA->commit();

try {
    $sessionB->exec("UPDATE issues SET priority = 4 WHERE project_id = 1 AND priority = 3");
    $sessionB->commit();
    echo "B committed, so this pair did not conflict.\n";
} catch (PDOException $error) {
    printf("B was rolled back: SQLSTATE %s. Retry the whole transaction.\n", $error->getCode());
    if ($sessionB->inTransaction()) {
        $sessionB->rollBack();
    }
}
printf("Both sessions had counted the same %d rows before deciding what to write.\n", $countByA === $countByB ? $countByA : -1);

$sessionA->exec("SET default_transaction_isolation = 'read committed'");
$sessionB->exec("SET default_transaction_isolation = 'read committed'");

echo "\n--- 4. a failed statement aborts the whole transaction ---\n";
$sessionA->beginTransaction();
try {
    $sessionA->exec("INSERT INTO issues (workspace_id, project_id, title, body, status, priority)
        VALUES (1, 1, 'Bad status', 'body', 'sideways', 1)");
} catch (PDOException $error) {
    printf("The insert failed as designed: SQLSTATE %s.\n", $error->getCode());
}
try {
    $sessionA->query('SELECT 1');
    echo "The transaction still accepts statements, which should not happen.\n";
} catch (PDOException $error) {
    printf("Every later statement fails too: SQLSTATE %s. Roll back first.\n", $error->getCode());
}
$sessionA->rollBack();
printf("After rollback the connection works again: %s.\n", $sessionA->query('SELECT 1')->fetchColumn() === 1 ? 'yes' : 'no');
