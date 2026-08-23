<?php

declare(strict_types=1);

require __DIR__ . '/../src/IssueStore.php';

header('Content-Type: application/json');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$store = new IssueStore((string) (getenv('DATABASE_URL') ?: ''));

switch ($path) {
    // Answers as long as the PHP process is alive. It says nothing about the database,
    // which is exactly the point FS10.5 makes about naive health checks.
    case '/health':
        echo json_encode(['status' => 'ok']);
        break;

    // Answers only when the work this container exists to do is actually possible.
    case '/ready':
        try {
            $store->connection()->query('SELECT 1');
            echo json_encode(['status' => 'ready']);
        } catch (Throwable $error) {
            http_response_code(503);
            echo json_encode(['status' => 'not-ready', 'error' => $error->getMessage()]);
        }
        break;

    case '/issues':
        try {
            echo json_encode($store->all());
        } catch (Throwable $error) {
            http_response_code(500);
            echo json_encode(['error' => $error->getMessage()]);
        }
        break;

    case '/whoami':
        echo json_encode([
            'uid' => trim((string) shell_exec('id -u')),
            'user' => trim((string) shell_exec('id -un')),
        ]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
}
