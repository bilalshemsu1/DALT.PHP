<?php

declare(strict_types=1);

use Core\Authenticator;
use Core\Database;
use Core\Session;

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

$sessionDirectory = sys_get_temp_dir() . '/dalt-fs06-session-' . bin2hex(random_bytes(6));
if (!mkdir($sessionDirectory, 0700) && !is_dir($sessionDirectory)) {
    throw new RuntimeException("Could not create session directory: {$sessionDirectory}");
}

$config = [
    'driver' => 'file',
    'name' => 'dalt_fs06_session',
    'lifetime' => 120,
    'cookie' => [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ],
];

try {
    session_save_path($sessionDirectory);
    Session::start($config);

    $database = new Database(['driver' => 'sqlite', 'database' => ':memory:']);
    $database->getConnection()->exec(
        'CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL UNIQUE, password TEXT NOT NULL)',
    );
    $database->query(
        'INSERT INTO users (id, email, password) VALUES (?, ?, ?)',
        [7, 'alice@example.com', password_hash('correct password', PASSWORD_DEFAULT)],
    );

    $auth = new Authenticator($database);
    $anonymousId = session_id();
    $wrongAccepted = $auth->attempt('alice@example.com', 'wrong password');
    $correctAccepted = $auth->attempt('alice@example.com', 'correct password');
    $authenticatedId = session_id();
    $currentUser = $auth->user();

    $auth->logout();

    session_id($authenticatedId);
    $_COOKIE[$config['name']] = $authenticatedId;
    Session::start($config);
    $replayedUser = (new Authenticator($database))->user();
    Session::destroy();

    echo 'wrong credentials accepted: ', $wrongAccepted ? 'yes' : 'no', "\n";
    echo 'correct credentials accepted: ', $correctAccepted ? 'yes' : 'no', "\n";
    echo 'session rotated on login: ', $anonymousId !== $authenticatedId ? 'yes' : 'no', "\n";
    echo 'current user: ', $currentUser['email'] ?? 'none', "\n";
    echo 'old session authenticates after logout: ', $replayedUser === null ? 'no' : 'yes', "\n";
} finally {
    if (Session::active()) {
        session_destroy();
    }

    foreach (scandir($sessionDirectory) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            unlink($sessionDirectory . '/' . $entry);
        }
    }
    rmdir($sessionDirectory);
}
