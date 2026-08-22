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

$database->query('TRUNCATE issues, projects, workspaces RESTART IDENTITY CASCADE');

$workspace = $database->query(
    'INSERT INTO workspaces (name, slug) VALUES (?, ?) RETURNING id',
    ['DALT Course', 'dalt-course'],
)->find();

$project = $database->query(
    'INSERT INTO projects (workspace_id, name, slug) VALUES (?, ?, ?) RETURNING id',
    [$workspace['id'], 'Web application', 'web-app'],
)->find();

$created = $database->query(
    'INSERT INTO issues (project_id, title) VALUES (?, ?)
     RETURNING id, project_id, title, status, priority',
    [$project['id'], "Don't interpolate me"],
)->find();

echo "created: {$created['id']} {$created['title']} [{$created['status']}]\n";

$issues = $database->query(
    'SELECT i.id, i.title, i.status, p.slug AS project_slug
     FROM issues AS i
     JOIN projects AS p ON p.id = i.project_id
     WHERE i.project_id = ? ORDER BY i.id',
    [$project['id']],
)->get();

echo 'listed: ', count($issues), "\n";

$updated = $database->query(
    'UPDATE issues SET status = ?, updated_at = CURRENT_TIMESTAMP
     WHERE id = ? RETURNING id, status',
    ['done', $created['id']],
)->find();

echo "updated: {$updated['id']} [{$updated['status']}]\n";

$deleted = $database->query(
    'DELETE FROM issues WHERE id = ? RETURNING id',
    [$created['id']],
)->find();

echo "deleted: {$deleted['id']}\n";

$remaining = $database->query('SELECT id FROM issues')->get();
echo 'remaining: ', count($remaining), "\n";
