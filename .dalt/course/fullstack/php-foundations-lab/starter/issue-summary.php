<?php

declare(strict_types=1);

/** @param array{id: string, title: string, status: string} $issue */
function issueSummary(array $issue): string
{
    $allowedStatuses = ['todo', 'in_progress', 'done'];

    if (!in_array($issue['status'], $allowedStatuses, true)) {
        throw new InvalidArgumentException('Issue status is invalid.');
    }

    return sprintf(
        '#%s [%s] %s',
        $issue['id'],
        $issue['status'],
        $issue['title'],
    );
}

$issues = [
    ['id' => 'ISS-41', 'title' => 'Trace a request', 'status' => 'todo'],
    ['id' => 'ISS-42', 'title' => 'Return honest JSON', 'status' => 'blocked'],
];

foreach ($issues as $issue) {
    try {
        echo issueSummary($issue), PHP_EOL;
    } catch (InvalidArgumentException $exception) {
        echo 'Rejected issue: ', $exception->getMessage(), PHP_EOL;
    }
}
