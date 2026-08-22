<?php

declare(strict_types=1);

use Core\Database;

$root = getenv('DALT_REPOSITORY_ROOT') ?: __DIR__;
while (!is_file($root . '/vendor/autoload.php') || !is_file($root . '/framework/Core/functions.php')) {
    $parent = dirname($root);
    if ($parent === $root) {
        throw new RuntimeException('Run this lab from inside the DALT repository.');
    }
    $root = $parent;
}

define('BASE_PATH', $root . '/');
require $root . '/vendor/autoload.php';
require $root . '/framework/Core/functions.php';

function issueEditStatus(Database $database, ?int $actorId, int $issueId): int
{
    if ($actorId === null) {
        return 401;
    }

    $issue = $database->query(
        'SELECT issues.creator_id, projects.workspace_id
         FROM issues
         JOIN projects ON projects.id = issues.project_id
         WHERE issues.id = ?',
        [$issueId],
    )->find();

    if ($issue === false) {
        return 404;
    }

    $membership = $database->query(
        'SELECT role FROM workspace_memberships WHERE workspace_id = ? AND user_id = ?',
        [$issue['workspace_id'], $actorId],
    )->find();

    if ($membership === false) {
        return 403;
    }

    $isCreator = (int) $issue['creator_id'] === $actorId;
    $isOwner = $membership['role'] === 'owner';

    return $isCreator || $isOwner ? 200 : 403;
}

function editIssue(Database $database, ?int $actorId, int $issueId, string $title): int
{
    $status = issueEditStatus($database, $actorId, $issueId);

    if ($status === 200) {
        $database->query('UPDATE issues SET title = ? WHERE id = ?', [$title, $issueId]);
    }

    return $status;
}

$database = new Database(['driver' => 'sqlite', 'database' => ':memory:']);
$connection = $database->getConnection();
$connection->exec('PRAGMA foreign_keys = ON');
$connection->exec(
    "CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL UNIQUE);
     CREATE TABLE workspaces (id INTEGER PRIMARY KEY, name TEXT NOT NULL);
     CREATE TABLE workspace_memberships (
         workspace_id INTEGER NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
         user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
         role TEXT NOT NULL CHECK (role IN ('member', 'owner')),
         PRIMARY KEY (workspace_id, user_id)
     );
     CREATE TABLE projects (
         id INTEGER PRIMARY KEY,
         workspace_id INTEGER NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
         name TEXT NOT NULL
     );
     CREATE TABLE issues (
         id INTEGER PRIMARY KEY AUTOINCREMENT,
         project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
         creator_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
         title TEXT NOT NULL
     );",
);

$connection->exec(
    "INSERT INTO users (id, email) VALUES
        (1, 'alice@example.com'),
        (2, 'bob@example.com'),
        (3, 'charlie@example.com'),
        (4, 'olivia@example.com');
     INSERT INTO workspaces (id, name) VALUES (10, 'Product');
     INSERT INTO workspace_memberships (workspace_id, user_id, role) VALUES
        (10, 1, 'member'),
        (10, 3, 'member'),
        (10, 4, 'owner');
     INSERT INTO projects (id, workspace_id, name) VALUES (20, 10, 'Web');
     INSERT INTO issues (id, project_id, creator_id, title)
        VALUES (30, 20, 1, 'Original title');",
);

$anonymous = editIssue($database, null, 30, 'Anonymous was here');
$nonMember = editIssue($database, 2, 30, 'Bob was here');
$ordinaryMember = editIssue($database, 3, 30, 'Charlie was here');
$titleAfterDenials = $database->query('SELECT title FROM issues WHERE id = ?', [30])->find()['title'];
$creator = editIssue($database, 1, 30, 'Creator update');
$database->query('DELETE FROM workspace_memberships WHERE workspace_id = ? AND user_id = ?', [10, 1]);
$formerCreator = editIssue($database, 1, 30, 'Former creator update');
$owner = editIssue($database, 4, 30, 'Owner update');

$submitted = ['title' => 'Server-owned identity', 'creator_id' => 2];
$authenticatedActorId = 1;
$database->query(
    'INSERT INTO issues (project_id, creator_id, title) VALUES (?, ?, ?)',
    [20, $authenticatedActorId, $submitted['title']],
);
$storedCreator = $database->query(
    'SELECT users.email FROM issues JOIN users ON users.id = issues.creator_id WHERE issues.id = ?',
    [(int) $connection->lastInsertId()],
)->find()['email'];

echo "anonymous edit: {$anonymous}\n";
echo "non-member edit: {$nonMember}\n";
echo "member non-creator edit: {$ordinaryMember}\n";
echo 'denied title unchanged: ', $titleAfterDenials === 'Original title' ? 'yes' : 'no', "\n";
echo "creator edit: {$creator}\n";
echo "former creator edit: {$formerCreator}\n";
echo "owner edit: {$owner}\n";
echo "forged creator stored as: {$storedCreator}\n";
