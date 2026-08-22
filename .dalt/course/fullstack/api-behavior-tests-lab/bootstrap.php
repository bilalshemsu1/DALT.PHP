<?php

declare(strict_types=1);

// Minimal bootstrap for the FS06.1 lab. It loads Composer's autoloader for Pest and
// the one class under test. The lab deliberately does not boot DALT: the lesson is
// about what a behaviour test observes, and a smaller surface makes that visible.

$root = getenv('DALT_REPOSITORY_ROOT') ?: __DIR__;
while (!is_file($root . '/vendor/autoload.php')) {
    $parent = dirname($root);
    if ($parent === $root) {
        throw new RuntimeException('Run this lab from inside the DALT repository.');
    }
    $root = $parent;
}

require_once $root . '/vendor/autoload.php';
require_once __DIR__ . '/src/IssueApi.php';
