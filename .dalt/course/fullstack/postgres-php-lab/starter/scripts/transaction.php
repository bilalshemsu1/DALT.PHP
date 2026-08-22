<?php

declare(strict_types=1);

use Core\Database;

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

$database = new Database([
    'driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55432,
    'dbname' => 'dalt_course', 'username' => 'dalt',
    'password' => 'dalt-course-local', 'charset' => 'utf8',
]);
$pdo = $database->getConnection();

$database->query('TRUNCATE issue_activity, issues, projects, workspaces RESTART IDENTITY CASCADE');
$workspace = $database->query(
    'INSERT INTO workspaces (name, slug) VALUES (?, ?) RETURNING id',
    ['DALT Course', 'dalt-course'],
)->find();
$project = $database->query(
    'INSERT INTO projects (workspace_id, name, slug) VALUES (?, ?, ?) RETURNING id',
    [$workspace['id'], 'Web application', 'web-app'],
)->find();

$pdo->beginTransaction();
try {
    $issue = $database->query(
        'INSERT INTO issues (project_id, title) VALUES (?, ?) RETURNING id',
        [$project['id'], 'Committed issue'],
    )->find();
    $activity = $database->query(
        'INSERT INTO issue_activity (issue_id, action) VALUES (?, ?) RETURNING id',
        [$issue['id'], 'created'],
    )->find();
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

echo "committed issue: {$issue['id']}\n";
echo "committed activity: {$activity['id']}\n";

$pdo->beginTransaction();
try {
    $failedIssue = $database->query(
        'INSERT INTO issues (project_id, title) VALUES (?, ?) RETURNING id',
        [$project['id'], 'Rolled back issue'],
    )->find();
    $database->query(
        'INSERT INTO issue_activity (issue_id, action) VALUES (?, ?)',
        [$failedIssue['id'], 'invented_action'],
    );
    $pdo->commit();
} catch (PDOException $exception) {
    $sqlState = $exception->errorInfo[0] ?? (string) $exception->getCode();
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "failure SQLSTATE: {$sqlState}\n";
}

$rolledBack = $database->query(
    'SELECT id FROM issues WHERE title = ?',
    ['Rolled back issue'],
)->get();
echo 'rolled back issue count: ', count($rolledBack), "\n";
