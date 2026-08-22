<?php

declare(strict_types=1);

use Core\Database;
use Core\Migration;

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
    'driver' => 'pgsql',
    'host' => '127.0.0.1',
    'port' => 55432,
    'dbname' => 'dalt_course',
    'username' => 'dalt',
    'password' => 'dalt-course-local',
    'charset' => 'utf8',
]);

$migration = new Migration($database, dirname(__DIR__) . '/database/migrations');
$migration->runMigrations();
