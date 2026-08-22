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

$migrationsPath = dirname(__DIR__) . '/database/migrations';
$through = null;

foreach (array_slice($argv, 1) as $argument) {
    if (!str_starts_with($argument, '--through=')) {
        throw new InvalidArgumentException("Unknown option: {$argument}");
    }

    $through = substr($argument, strlen('--through='));
    if (preg_match('/^\d{3}$/', $through) !== 1) {
        throw new InvalidArgumentException('--through must be a three-digit migration prefix.');
    }
}

if ($through === null) {
    (new Migration($database, $migrationsPath))->runMigrations();
    return;
}

// The shared lab contains later lessons too. Build a temporary view of migration
// history so an earlier lesson can still execute exactly the files it has reached.
$stagedPath = sys_get_temp_dir() . '/dalt-pg-migrations-' . bin2hex(random_bytes(6));
if (!mkdir($stagedPath, 0700) && !is_dir($stagedPath)) {
    throw new RuntimeException("Could not create migration stage: {$stagedPath}");
}

try {
    foreach (glob($migrationsPath . '/*.sql') ?: [] as $file) {
        if (substr(basename($file), 0, 3) <= $through) {
            copy($file, $stagedPath . '/' . basename($file));
        }
    }

    (new Migration($database, $stagedPath))->runMigrations();
} finally {
    foreach (glob($stagedPath . '/*.sql') ?: [] as $file) {
        unlink($file);
    }
    rmdir($stagedPath);
}
