<?php

declare(strict_types=1);

use Core\Middleware\Middleware;
use Core\Request;
use Core\Response;

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

$_SESSION = [];
$token = csrf_token();
$writes = 0;
$middleware = new Middleware();
$write = static function (Request $request) use (&$writes): Response {
    $writes++;
    return Response::text('changed');
};

$missing = $middleware->run(
    'csrf',
    new Request(server: ['REQUEST_METHOD' => 'POST']),
    $write,
);
$writesAfterMissing = $writes;

$matching = $middleware->run(
    'csrf',
    new Request(server: [
        'REQUEST_METHOD' => 'POST',
        'HTTP_X_CSRF_TOKEN' => $token,
    ]),
    $write,
);

$safe = $middleware->run(
    'csrf',
    new Request(server: ['REQUEST_METHOD' => 'GET']),
    static fn (Request $request): Response => Response::text('read'),
);

echo 'token characters: ', strlen($token), "\n";
echo 'missing token status: ', $missing->status(), "\n";
echo 'writes after missing token: ', $writesAfterMissing, "\n";
echo 'matching header status: ', $matching->status(), "\n";
echo 'writes after matching header: ', $writes, "\n";
echo 'safe GET status: ', $safe->status(), "\n";
