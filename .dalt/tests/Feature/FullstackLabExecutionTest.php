<?php

declare(strict_types=1);

/**
 * Runs every course-owned Fullstack lab the way the lesson tells the learner to run it.
 *
 * Structural assertions elsewhere ("package.json exists", "the file contains 19.2.3")
 * cannot tell you whether the learner's very first command works. They did not, and a
 * Part 03 lab shipped whose `npm test` failed before the learner had changed anything.
 * This file exists so that cannot happen again.
 *
 * Several labs are *supposed* to fail on the unmodified starter — that seeded failure is
 * the lesson. So a bare "exit code was non-zero" assertion is not enough: it would pass
 * just as happily for a lab broken by a missing dependency or a typo in a script name.
 * Every expected failure therefore also pins a marker string proving the lab failed for
 * the intended reason. That is the plausible-fake standard applied to the labs
 * themselves: the seeded failure fails, a genuine fix passes, and a lab that merely
 * happens to be broken does not read as either.
 *
 * Cost: roughly a minute, dominated by the `npm ci` runs. `DALT_SKIP_LAB_EXECUTION=1`
 * skips the file for tight inner loops — never in a run whose result you intend to report.
 */

/**
 * Every script in every lab, with what it must do on the untouched starter.
 *
 * 'pass'            exit 0.
 * ['fail', marker]  non-zero exit, and the combined output contains marker.
 *
 * Reasons are sourced from the lesson that owns the lab, not from observed behavior.
 * If a pin here disagrees with reality, decide which one is wrong before editing either.
 */
function fullstackLabExpectations(): array
{
    return [
        // FS02.1 — the starter ships a deliberate contract mismatch: IssueSummary.id is a
        // number, the changed requirement supplies the visible key 'ISS-19'. The learner
        // decides whether the caller or the contract is wrong. README.md step 1.
        'typescript-lab' => [
            'runtime' => ['fail', 'TypeError'],          // the JavaScript surprise the lesson opens with
            'stage-a' => ['fail', 'error TS'],           // the checker catching what JavaScript did not
            'typecheck' => ['fail', 'error TS2322'],     // the seeded mismatch
            'emit:erasure' => 'pass',
            'run:erasure' => 'pass',
            'build' => ['fail', 'error TS'],             // same seeded mismatch, through the emitting path
        ],

        // FS02.2 — each stage: script isolates one modeling mistake. stage:structural is the
        // exception and must succeed: the lesson says "It succeeds", because a richer object
        // satisfies a smaller shape structurally.
        'typescript-modeling-lab' => [
            'typecheck' => 'pass',
            'stage:required' => ['fail', 'error TS'],
            'stage:optional' => ['fail', 'error TS'],
            'stage:readonly' => ['fail', 'error TS'],
            'stage:status' => ['fail', 'error TS'],
            'stage:nested' => ['fail', 'error TS'],
            'stage:structural' => 'pass',
            'run' => 'pass',
        ],

        // FS02.3 — the --noEmit stages demonstrate a diagnostic; stage:truthiness and
        // stage:guard compile *and execute*, so they must succeed.
        'typescript-narrowing-lab' => [
            'typecheck' => 'pass',
            'run' => 'pass',
            'stage:union' => ['fail', 'error TS'],
            'stage:unknown' => ['fail', 'error TS'],
            'stage:truthiness' => 'pass',
            'stage:state' => ['fail', 'error TS'],
            'stage:exhaustive' => ['fail', 'error TS'],
            'stage:guard' => 'pass',
        ],

        // FS02.4 — "The stage:* commands intentionally show a compiler error; a non-zero
        // result there is evidence for the experiment, not a final failure." (lesson, §30)
        'typescript-functions-lab' => [
            'typecheck' => 'pass',
            'run' => 'pass',
            'stage:return-contract' => ['fail', 'error TS'],
            'stage:callbacks' => ['fail', 'error TS'],
            'stage:constraint' => ['fail', 'error TS'],
            'stage:utility-model-change' => ['fail', 'error TS'],
        ],

        // FS02.5 — parseIssue is an unimplemented TODO throw. `run` and `test` must fail
        // until the learner establishes runtime evidence; `unsafe` must fail at runtime
        // despite a clean typecheck, which is the entire point of the lesson.
        'typescript-runtime-boundaries-lab' => [
            'typecheck' => 'pass',
            'unsafe' => ['fail', 'TypeError'],
            'stage:unknown' => ['fail', 'error TS'],
            'stage:object-shape' => 'pass',
            'run' => ['fail', 'TODO: establish runtime evidence'],
            'test' => ['fail', 'TODO: establish runtime evidence'],
        ],

        // FS03.1–FS03.4 — the only lab with nothing seeded broken. The learner grows it
        // across four lessons, so everything must be green before they touch it.
        'react-foundations-lab' => [
            'typecheck' => 'pass',
            'test' => 'pass',
            'build' => 'pass',
        ],

        // FS07.3 — ProjectPage imports the API client directly instead of reading the
        // ApiContext seam, so the six component tests wrap it in a provider holding a
        // fake and the component ignores all of it and calls fetch. jsdom cannot resolve
        // a relative URL, which surfaces as "Failed to parse URL" — the honest error a
        // learner gets when the seam is missing, not a synthetic one.
        //
        // typecheck must PASS: the defect is a wiring mistake between two values of the
        // same type, so the compiler has nothing to say. That combination — green types,
        // red run — is the trap the lesson is built around, and pinning it here is what
        // stops someone "fixing" the lab by making it a type error instead.
        'frontend-testing-lab' => [
            'typecheck' => 'pass',
            'test' => ['fail', 'Failed to parse URL'],
            'test:components' => ['fail', 'Failed to parse URL'],
            'test:boundaries' => 'pass',
            'test:parsers' => 'pass',   // the cheapest level works before the seam exists
            'test:routing' => 'pass',
            'test:session' => 'pass',
            'build' => 'pass',
        ],

        // FS08.1-FS08.4 - nothing is seeded broken here. The lab's job is to make
        // ownership mistakes *visible while passing*: the audit suite asserts that a
        // stored derived count reads 2 while the computed one reads 1, and that two
        // private copies of ISS-1 disagree after a write. A red suite would say the lab
        // is broken; a green suite saying "2" and "1" at the same time is the finding.
        'state-architecture-lab' => [
            'typecheck' => 'pass',
            'test' => 'pass',
            'test:audit' => 'pass',
            'test:queries' => 'pass',
            'test:mutations' => 'pass',
            'build' => 'pass',
        ],
    ];
}

/**
 * Build-milestone workspaces, under `.dalt/course/build/<ID>-<slug>/`.
 *
 * These were missed when this file was first written, which covered only
 * `.dalt/course/fullstack/`. The B02 specification told the learner to run
 * `npm run test` against a starter whose script was called `test:parser` — a
 * "Missing script" error on the milestone that teaches trust boundaries. The gap in
 * the guard is what let the gap in the content through, so the guard now covers
 * every runnable workspace the course ships, not one directory of them.
 *
 * Keys are `<milestone-dir>` or `<milestone-dir>/reference/<name>`.
 */
function fullstackBuildExpectations(): array
{
    return [
        // B02 stage 1 is "complete the model until it typechecks", so the untouched
        // starter must not typecheck — and everything downstream of tsc fails with it.
        'B02-type-the-future-application/starter' => [
            'typecheck' => ['fail', 'error TS'],
            'run' => ['fail', 'error TS'],
            'test' => ['fail', 'error TS'],
        ],

        // The author-facing worked solution. Its job is to prove the milestone is
        // completable at all; if it stops passing, the specification is asking for
        // something that cannot be built. Never reachable from learner navigation.
        'B02-type-the-future-application/reference/final' => [
            'typecheck' => 'pass',
            'run' => 'pass',
            'test' => 'pass',
        ],
    ];
}

/** Scripts that never terminate on their own and so cannot be asserted on. */
const FULLSTACK_LAB_LONG_RUNNING = ['dev', 'preview', 'watch'];

function fullstackLabRun(string $directory, array $command, int $timeoutSeconds = 300): array
{
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $directory,
        null,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        return [-1, 'could not start: ' . implode(' ', $command)];
    }

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $output = '';
    $deadline = microtime(true) + $timeoutSeconds;
    while (true) {
        $output .= (string) stream_get_contents($pipes[1]);
        $output .= (string) stream_get_contents($pipes[2]);

        $status = proc_get_status($process);
        if (!$status['running']) {
            break;
        }
        if (microtime(true) > $deadline) {
            proc_terminate($process, 9);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            return [-1, $output . "\n[timed out after {$timeoutSeconds}s]"];
        }
        usleep(20000);
    }

    $output .= (string) stream_get_contents($pipes[1]);
    $output .= (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $output];
}

function fullstackLabCopy(string $source, string $destination): void
{
    mkdir($destination, 0700, true);
    foreach (new FilesystemIterator($source, FilesystemIterator::SKIP_DOTS) as $entry) {
        $target = $destination . '/' . $entry->getFilename();
        if ($entry->isDir()) {
            fullstackLabCopy($entry->getPathname(), $target);
        } else {
            copy($entry->getPathname(), $target);
        }
    }
}

function fullstackLabRemove(string $path): void
{
    if (is_link($path) || is_file($path)) {
        unlink($path);

        return;
    }
    if (!is_dir($path)) {
        return;
    }
    foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $entry) {
        fullstackLabRemove($entry->getPathname());
    }
    rmdir($path);
}

dataset('fullstack labs', array_map(
    static fn (string $lab): array => [$lab],
    array_keys(fullstackLabExpectations()),
));

dataset('build workspaces', array_map(
    static fn (string $workspace): array => [$workspace],
    array_keys(fullstackBuildExpectations()),
));

/**
 * Copy a workspace to a temp directory, install it, and assert every pinned script.
 *
 * Shared by both datasets so a runnable workspace cannot be covered by one and not
 * the other — which is exactly how the B02 starter went unchecked.
 */
function fullstackAssertWorkspace(object $test, string $label, string $source, array $expectations): void
{
    if (getenv('DALT_SKIP_LAB_EXECUTION') === '1') {
        $test->markTestSkipped('DALT_SKIP_LAB_EXECUTION=1.');
    }

    expect(is_dir($source))->toBeTrue("'{$label}' has no directory at {$source}.");
    expect(is_file($source . '/package-lock.json'))
        ->toBeTrue("'{$label}' has no lockfile, so `npm ci` cannot pin what the learner installs.");

    $npm = fullstackLabRun($source, ['npm', '--version'], 30);
    if ($npm[0] !== 0) {
        $test->markTestSkipped('npm is not available on this machine.');
    }

    $workspace = sys_get_temp_dir() . '/dalt-lab-' . bin2hex(random_bytes(6));

    try {
        fullstackLabCopy($source, $workspace);

        // A failed install is an environment problem (no network, cold cache), not a
        // defect in the workspace. Skip rather than fail — but never treat it as a pass.
        [$installExit, $installOutput] = fullstackLabRun(
            $workspace,
            ['npm', 'ci', '--prefer-offline', '--no-audit', '--no-fund'],
            600,
        );
        if ($installExit !== 0) {
            $test->markTestSkipped("`npm ci` failed for '{$label}'; treating as an offline environment.\n" . $installOutput);
        }

        $scripts = json_decode(
            (string) file_get_contents($workspace . '/package.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        )['scripts'] ?? [];

        $assertable = array_values(array_diff(array_keys($scripts), FULLSTACK_LAB_LONG_RUNNING));

        // An unpinned script is a hole in this guard, so the pin list and the workspace
        // must agree in both directions. The reverse check is the one that catches a
        // specification referring to a script that does not exist.
        expect(array_values(array_diff($assertable, array_keys($expectations))))
            ->toBe([], "'{$label}' has scripts with no pinned expectation. Add them to the expectation list.");
        expect(array_values(array_diff(array_keys($expectations), $assertable)))
            ->toBe([], "The expectation list pins scripts that '{$label}' does not have.");

        foreach ($expectations as $script => $expected) {
            [$exit, $output] = fullstackLabRun($workspace, ['npm', 'run', '--silent', $script], 300);

            if ($expected === 'pass') {
                expect($exit)->toBe(
                    0,
                    "`npm run {$script}` must succeed on the untouched '{$label}', "
                    . "because the lesson or specification tells the learner to run it and read a clean result.\n{$output}",
                );

                continue;
            }

            [, $marker] = $expected;
            expect($exit)->not->toBe(
                0,
                "`npm run {$script}` must fail on the untouched '{$label}' — that seeded "
                . "failure is the lesson. It succeeded, so the seed is gone.\n{$output}",
            );
            // str_contains rather than toContain(): Pest's toContain() is variadic over
            // needles and would read a failure message as a second thing to look for.
            expect(str_contains($output, $marker))->toBeTrue(
                "`npm run {$script}` failed for the wrong reason in '{$label}'. Expected the seeded "
                . "failure containing \"{$marker}\", which is what makes this an exercise rather "
                . "than a broken workspace.\n{$output}",
            );
        }
    } finally {
        fullstackLabRemove($workspace);
    }
}

test('the lab runs exactly as its lesson promises', function (string $lab) {
    fullstackAssertWorkspace(
        $this,
        $lab,
        base_path(".dalt/course/fullstack/{$lab}/starter"),
        fullstackLabExpectations()[$lab],
    );
})->with('fullstack labs');

test('the FS07.3 API seam has one genuine fix and rejects a plausible fake', function () {
    if (getenv('DALT_SKIP_LAB_EXECUTION') === '1') {
        $this->markTestSkipped('DALT_SKIP_LAB_EXECUTION=1.');
    }

    $source = base_path('.dalt/course/fullstack/frontend-testing-lab/starter');
    $npm = fullstackLabRun($source, ['npm', '--version'], 30);
    if ($npm[0] !== 0) {
        $this->markTestSkipped('npm is not available on this machine.');
    }

    $workspace = sys_get_temp_dir() . '/dalt-fs073-' . bin2hex(random_bytes(6));

    try {
        fullstackLabCopy($source, $workspace);
        [$installExit, $installOutput] = fullstackLabRun(
            $workspace,
            ['npm', 'ci', '--prefer-offline', '--no-audit', '--no-fund'],
            600,
        );
        if ($installExit !== 0) {
            $this->markTestSkipped("`npm ci` failed for the FS07.3 proof.\n" . $installOutput);
        }

        $projectPage = $workspace . '/src/ProjectPage.tsx';
        $broken = (string) file_get_contents($projectPage);
        $fixed = str_replace(
            ["import { issueApi } from './issueApi';", 'const api = issueApi;'],
            ["import { useIssueApi } from './ApiContext';", 'const api = useIssueApi();'],
            $broken,
            $replacementCount,
        );

        expect($replacementCount)->toBe(2, 'The two learner-facing FS07.3 seam markers drifted.');
        file_put_contents($projectPage, $fixed);

        [$fixedExit, $fixedOutput] = fullstackLabRun(
            $workspace,
            ['npm', 'run', '--silent', 'test:components'],
            300,
        );
        expect($fixedExit)->toBe(0, "The genuine API seam fix must pass all component tests.\n{$fixedOutput}");

        $fake = str_replace("draft.title.trim() === ''", "draft.title === ''", $fixed, $fakeCount);
        expect($fakeCount)->toBe(1, 'The plausible fake mutation no longer reaches title validation.');
        file_put_contents($projectPage, $fake);

        [$fakeExit, $fakeOutput] = fullstackLabRun(
            $workspace,
            ['npm', 'run', '--silent', 'test:components'],
            300,
        );
        expect($fakeExit)->not->toBe(0, 'A seam-only fake that breaks whitespace validation must fail.')
            ->and(str_contains($fakeOutput, 'rejects a whitespace-only title'))->toBeTrue(
                "The plausible fake failed for the wrong reason.\n{$fakeOutput}",
            );
    } finally {
        fullstackLabRemove($workspace);
    }
});

test('the build workspace runs exactly as its milestone specification promises', function (string $workspace) {
    fullstackAssertWorkspace(
        $this,
        $workspace,
        base_path(".dalt/course/build/{$workspace}"),
        fullstackBuildExpectations()[$workspace],
    );
})->with('build workspaces');

test('the FS05.1 PHP foundations lab executes both success and exception paths', function () {
    $lab = base_path('.dalt/course/fullstack/php-foundations-lab/starter');

    [$exit, $output] = fullstackLabRun($lab, [PHP_BINARY, 'issue-summary.php'], 30);

    expect($exit)->toBe(0, "The FS05.1 PHP script did not run cleanly:\n{$output}")
        ->and($output)->toBe(
            "#ISS-41 [todo] Trace a request\nRejected issue: Issue status is invalid.\n",
        );
});

test('the Batch 7 PostgreSQL lab executes each lesson against the real database', function () {
    if (getenv('DALT_SKIP_LAB_EXECUTION') === '1') {
        $this->markTestSkipped('DALT_SKIP_LAB_EXECUTION=1.');
    }

    $source = base_path('.dalt/course/fullstack/postgres-php-lab/starter');
    $workspace = sys_get_temp_dir() . '/dalt-postgres-' . bin2hex(random_bytes(6));
    fullstackLabCopy($source, $workspace);

    $compose = ['docker', 'compose', '-f', $workspace . '/compose.yaml'];
    [$dockerExit] = fullstackLabRun($workspace, ['docker', 'version'], 30);
    if ($dockerExit !== 0) {
        fullstackLabRemove($workspace);
        $this->markTestSkipped('Docker is unavailable; the PostgreSQL lab cannot start.');
    }

    try {
        [$upExit, $upOutput] = fullstackLabRun($workspace, [...$compose, 'up', '-d', '--wait'], 600);
        expect($upExit)->toBe(0, "The PostgreSQL lab did not become healthy:\n{$upOutput}");

        [$schemaExit, $schemaOutput] = fullstackLabRun($workspace, [
            ...$compose, 'exec', '-T', 'db', 'psql', '-U', 'dalt', '-d', 'dalt_course',
            '-v', 'ON_ERROR_STOP=1', '-f', '/course/database/migrations/001_create_relations.sql',
        ], 60);
        expect($schemaExit)->toBe(0, "FS05.4's schema did not apply:\n{$schemaOutput}");

        [$observeExit, $observeOutput] = fullstackLabRun($workspace, [
            ...$compose, 'exec', '-T', 'db', 'psql', '-U', 'dalt', '-d', 'dalt_course',
            '-v', 'ON_ERROR_STOP=1', '-f', '/course/database/observe-relations.sql',
        ], 60);
        expect($observeExit)->toBe(0, "FS05.4's relation observation failed:\n{$observeOutput}")
            ->and($observeOutput)->toContain('DALT Course')
            ->and($observeOutput)->toContain('Trace a request');

        [$orphanExit, $orphanOutput] = fullstackLabRun($workspace, [
            ...$compose, 'exec', '-T', 'db', 'psql', '-U', 'dalt', '-d', 'dalt_course',
            '-v', 'ON_ERROR_STOP=1', '-c', "INSERT INTO issues (project_id, title) VALUES (999, 'Orphan');",
        ], 60);
        expect($orphanExit)->not->toBe(0)
            ->and($orphanOutput)->toContain('issues_project_fk');

        fullstackLabRun($workspace, [...$compose, 'down', '-v'], 120);
        [$freshExit, $freshOutput] = fullstackLabRun($workspace, [...$compose, 'up', '-d', '--wait'], 120);
        expect($freshExit)->toBe(0, "FS05.5 could not start a clean database:\n{$freshOutput}");

        [$migrationExit, $migrationOutput] = fullstackLabRun($workspace, [
            'env', 'DALT_REPOSITORY_ROOT=' . base_path(), PHP_BINARY, $workspace . '/scripts/migrate.php', '--through=002',
        ], 60);
        expect($migrationExit)->toBe(0, "DALT could not apply FS05.5's migrations:\n{$migrationOutput}")
            ->and($migrationOutput)->toContain('001_create_relations.sql')
            ->and($migrationOutput)->toContain('002_add_constraints_and_indexes.sql')
            ->and($migrationOutput)->toContain('Ran 2 migrations.');

        [$secondExit, $secondOutput] = fullstackLabRun($workspace, [
            'env', 'DALT_REPOSITORY_ROOT=' . base_path(), PHP_BINARY, $workspace . '/scripts/migrate.php', '--through=002',
        ], 60);
        expect($secondExit)->toBe(0)
            ->and($secondOutput)->toContain('No migrations to run.');

        [$constraintExit, $constraintOutput] = fullstackLabRun($workspace, [
            ...$compose, 'exec', '-T', 'db', 'psql', '-U', 'dalt', '-d', 'dalt_course',
            '-v', 'ON_ERROR_STOP=1', '-c',
            "INSERT INTO workspaces (name, slug) VALUES ('One', 'same'), ('Two', 'same');",
        ], 60);
        expect($constraintExit)->not->toBe(0)
            ->and($constraintOutput)->toContain('workspaces_slug_unique');

        [$indexExit, $indexOutput] = fullstackLabRun($workspace, [
            ...$compose, 'exec', '-T', 'db', 'psql', '-U', 'dalt', '-d', 'dalt_course',
            '-At', '-c', "SELECT indexname FROM pg_indexes WHERE indexname = 'issues_project_id_idx';",
        ], 60);
        expect($indexExit)->toBe(0)
            ->and(trim($indexOutput))->toBe('issues_project_id_idx');

        [$crudExit, $crudOutput] = fullstackLabRun($workspace, [
            'env', 'DALT_REPOSITORY_ROOT=' . base_path(), PHP_BINARY, $workspace . '/scripts/crud.php',
        ], 60);
        expect($crudExit)->toBe(0, "FS05.6's PDO CRUD sequence failed:\n{$crudOutput}")
            ->and($crudOutput)->toBe(
                "created: 1 Don't interpolate me [todo]\n"
                . "listed: 1\n"
                . "updated: 1 [done]\n"
                . "deleted: 1\n"
                . "remaining: 0\n",
            );

        [$thirdMigrationExit, $thirdMigrationOutput] = fullstackLabRun($workspace, [
            'env', 'DALT_REPOSITORY_ROOT=' . base_path(), PHP_BINARY, $workspace . '/scripts/migrate.php', '--through=003',
        ], 60);
        expect($thirdMigrationExit)->toBe(0, "FS05.7's activity migration failed:\n{$thirdMigrationOutput}")
            ->and($thirdMigrationOutput)->toContain('003_create_issue_activity.sql')
            ->and($thirdMigrationOutput)->toContain('Ran 1 migrations.');

        [$transactionExit, $transactionOutput] = fullstackLabRun($workspace, [
            'env', 'DALT_REPOSITORY_ROOT=' . base_path(), PHP_BINARY, $workspace . '/scripts/transaction.php',
        ], 60);
        expect($transactionExit)->toBe(0, "FS05.7's transaction proof failed:\n{$transactionOutput}")
            ->and($transactionOutput)->toBe(
                "committed issue: 1\n"
                . "committed activity: 1\n"
                . "failure SQLSTATE: 23514\n"
                . "rolled back issue count: 0\n",
            );
    } finally {
        fullstackLabRun($workspace, [...$compose, 'down', '-v'], 120);
        fullstackLabRemove($workspace);
    }
});

test('every command a milestone specification names actually exists', function () {
    // The defect this catches: B02's specification said `npm run test` three times
    // while its starter only defined `test:parser`. Structural conformance passed,
    // the lab test did not cover build workspaces, and the learner would have hit
    // "Missing script" on the milestone about trust boundaries.
    foreach (\Core\BuildMilestone::all() as $id => $milestone) {
        $body = \Core\BuildMilestone::specification($id);
        preg_match_all('/`npm run ([a-z][a-z0-9:-]*)`/', $body, $matches);
        $referenced = array_unique($matches[1]);
        if ($referenced === []) {
            continue;
        }

        // A milestone may legitimately name scripts belonging to the repository root
        // (B03 works there) as well as to its own starter. Both are valid targets.
        $available = [];
        foreach ([$milestone['path'] . '/starter/package.json', base_path('package.json')] as $manifest) {
            if (is_file($manifest)) {
                $decoded = json_decode((string) file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
                $available = [...$available, ...array_keys($decoded['scripts'] ?? [])];
            }
        }

        foreach ($referenced as $script) {
            expect(in_array($script, $available, true))->toBeTrue(
                "Build {$id} tells the learner to run `npm run {$script}`, but no package.json "
                . 'it can reach defines that script. Either the specification or the starter is wrong.',
            );
        }
    }
});

test('the FS06.1 behaviour-test lab passes, and its sabotages fail', function () {
    $sourceLab = base_path('.dalt/course/fullstack/api-behavior-tests-lab');
    expect(is_dir($sourceLab))->toBeTrue('FS06.1 needs a runnable lab; a lesson about tests must ship tests.');

    $lab = sys_get_temp_dir() . '/dalt-api-behavior-' . bin2hex(random_bytes(6));
    fullstackLabCopy($sourceLab, $lab);

    $run = static function () use ($lab): array {
        $process = proc_open(
            [
                'env', 'DALT_REPOSITORY_ROOT=' . base_path(), PHP_BINARY, base_path('vendor/bin/pest'),
                $lab . '/tests', '--bootstrap=' . $lab . '/bootstrap.php',
            ],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
        );
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    };

    try {
        [$status, $output] = $run();
        expect($status)->toBe(0, "The FS06.1 lab does not pass as shipped:\n" . $output);
        expect(str_contains($output, '10 passed'))->toBeTrue(
            "The FS06.1 lab should run ten tests. If a test was added or removed, update the\n"
            . "lab README's table and this expectation together.\n" . $output,
        );

        // The plausible-fake standard, applied to a clean lab copy. The canonical
        // course artifact is never edited by its own execution test.
        $source = $lab . '/src/IssueApi.php';
        $original = (string) file_get_contents($source);

        $sabotages = [
            'rollback removed' => ['$this->pdo->rollBack();', '$this->pdo->commit();'],
            'validation bypassed' => ['if ($errors !== []) {', 'if (false) {'],
        ];

        foreach ($sabotages as $label => [$from, $to]) {
            file_put_contents($source, str_replace($from, $to, $original));
            [$sabotagedStatus, $sabotagedOutput] = $run();

            expect($sabotagedStatus)->not->toBe(
                0,
                "The FS06.1 lab still passes with {$label}. Its tests do not prove what the "
                . "README says they prove.\n" . $sabotagedOutput,
            );
        }

        file_put_contents($source, $original);

        [$restoredStatus] = $run();
        expect($restoredStatus)->toBe(0, 'The lab was left broken after the sabotage check.');
    } finally {
        fullstackLabRemove($lab);
    }
})->skip(
    !is_file(base_path('vendor/bin/pest')),
    'Pest is not installed; the FS06.1 lab cannot be executed.',
);

test('the Batch 8 authentication-boundaries lab proves passwords, sessions, CSRF, and authorization', function () {
    $source = base_path('.dalt/course/fullstack/auth-boundaries-lab/starter');
    $workspace = sys_get_temp_dir() . '/dalt-auth-boundaries-' . bin2hex(random_bytes(6));
    fullstackLabCopy($source, $workspace);

    try {
        [$exit, $output] = fullstackLabRun($workspace, [PHP_BINARY, 'scripts/passwords.php'], 30);

        expect($exit)->toBe(0, "FS06.2's password experiment failed:\n{$output}")
            ->and($output)->toBe(
                "stored plaintext: no\n"
                . "same password, same hash: no\n"
                . "correct password verifies: yes\n"
                . "wrong password verifies: no\n"
                . "public fields: id,email\n",
            );

        [$sessionExit, $sessionOutput] = fullstackLabRun(
            $workspace,
            ['env', 'DALT_REPOSITORY_ROOT=' . base_path(), PHP_BINARY, 'scripts/sessions.php'],
            30,
        );

        expect($sessionExit)->toBe(0, "FS06.3's server-session experiment failed:\n{$sessionOutput}")
            ->and($sessionOutput)->toBe(
                "wrong credentials accepted: no\n"
                . "correct credentials accepted: yes\n"
                . "session rotated on login: yes\n"
                . "current user: alice@example.com\n"
                . "old session authenticates after logout: no\n",
            );

        [$csrfExit, $csrfOutput] = fullstackLabRun(
            $workspace,
            ['env', 'DALT_REPOSITORY_ROOT=' . base_path(), PHP_BINARY, 'scripts/csrf.php'],
            30,
        );

        expect($csrfExit)->toBe(0, "FS06.4's CSRF experiment failed:\n{$csrfOutput}")
            ->and($csrfOutput)->toBe(
                "token characters: 64\n"
                . "missing token status: 419\n"
                . "writes after missing token: 0\n"
                . "matching header status: 200\n"
                . "writes after matching header: 1\n"
                . "safe GET status: 200\n",
            );

        [$authorizationExit, $authorizationOutput] = fullstackLabRun(
            $workspace,
            ['env', 'DALT_REPOSITORY_ROOT=' . base_path(), PHP_BINARY, 'scripts/authorization.php'],
            30,
        );

        expect($authorizationExit)->toBe(0, "FS06.5's authorization experiment failed:\n{$authorizationOutput}")
            ->and($authorizationOutput)->toBe(
                "anonymous edit: 401\n"
                . "non-member edit: 403\n"
                . "member non-creator edit: 403\n"
                . "denied title unchanged: yes\n"
                . "creator edit: 200\n"
                . "former creator edit: 403\n"
                . "owner edit: 200\n"
                . "forged creator stored as: alice@example.com\n",
            );
    } finally {
        fullstackLabRemove($workspace);
    }
});

test('the Part 04 fixture API executes the documented issue lifecycle', function () {
    $fixture = base_path('.dalt/course/fullstack/react-server-fixture/fixture-api.php');
    expect(is_file($fixture))->toBeTrue('Part 04 needs its resettable fixture API.');

    $port = random_int(18000, 24000);
    $process = proc_open(
        ['php', '-S', "127.0.0.1:{$port}", $fixture],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname($fixture),
        null,
        ['bypass_shell' => true],
    );
    expect(is_resource($process))->toBeTrue('Could not start the Part 04 fixture server.');

    try {
        $base = "http://127.0.0.1:{$port}";
        // Wait on the socket rather than polling with file_get_contents: `@` hides the
        // "Connection refused" message but Pest's error handler still records it, and
        // a warning-coloured pass is a pass nobody reads.
        // Pest promotes even `@`-suppressed warnings, and every refused connect during
        // startup would colour this test WARN. Silence the wait loop specifically, then
        // restore — a warning-coloured pass is a pass nobody reads.
        set_error_handler(static fn (): bool => true);
        try {
            $deadline = microtime(true) + 5;
            while (microtime(true) < $deadline) {
                $probe = fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
                if (is_resource($probe)) {
                    fclose($probe);
                    break;
                }
                usleep(50000);
            }
        } finally {
            restore_error_handler();
        }

        $initial = file_get_contents("{$base}/api/issues");
        expect($initial)->not->toBeFalse('Part 04 fixture did not accept GET /api/issues.');
        $issues = json_decode((string) $initial, true, flags: JSON_THROW_ON_ERROR);
        expect($issues)->toHaveCount(3);

        // The domain must survive Part 04. B02 types `Project` and puts `projectId` on
        // CreateIssueInput, and FS05.4 models projects relationally; a fixture without the
        // field would drop it from the domain for one whole part, so the learner's own
        // Issue type would stop matching the server through no fault of theirs.
        expect(array_keys($issues[0]))->toBe(['id', 'projectId', 'title', 'status', 'priority']);

        $request = static function (string $method, string $path, ?array $body = null) use ($base): array {
            $context = stream_context_create(['http' => [
                'method' => $method,
                'header' => "Content-Type: application/json\r\n",
                'content' => $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR),
                'ignore_errors' => true,
            ]]);
            $response = file_get_contents($base . $path, false, $context);
            $status = $http_response_header[0] ?? '';
            return [$status, $response];
        };

        [$createdStatus, $createdBody] = $request('POST', '/api/issues', ['title' => 'Prove a mutation', 'priority' => 'high']);
        expect($createdStatus)->toContain('201');
        $created = json_decode((string) $createdBody, true, flags: JSON_THROW_ON_ERROR);
        expect($created['title'])->toBe('Prove a mutation');

        // FS03.3 makes the learner build a priority select and verify the new issue
        // "appears with the chosen priority". B04 Stage 2 then points that form at this
        // fixture and says to render the returned 201 issue rather than a guessed copy.
        // While the fixture hardcoded 'medium', doing exactly what both documents say
        // silently deleted the feature, and the learner would hunt for it in their own
        // request body. Found by performing B04; same class as the projectId gap above.
        expect($created['priority'])->toBe(
            'high',
            'The Part 04 fixture ignored the priority the learner sent. FS03.3 requires the '
            . 'chosen priority to survive creation.',
        );

        // str_contains rather than toContain(): toContain() is variadic over needles and
        // would read the failure message as a second thing to look for.
        [$badPriorityStatus] = $request('POST', '/api/issues', ['title' => 'Bad priority', 'priority' => 'urgent']);
        expect(str_contains($badPriorityStatus, '422'))->toBeTrue(
            'A priority outside the union must be rejected, not stored. The learner parses '
            . 'this response against their own Priority type.',
        );

        [$defaultedStatus, $defaultedBody] = $request('POST', '/api/issues', ['title' => 'No priority sent']);
        expect($defaultedStatus)->toContain('201')
            ->and(json_decode((string) $defaultedBody, true, flags: JSON_THROW_ON_ERROR)['priority'])->toBe('medium');

        [$invalidStatus, $invalidBody] = $request('POST', '/api/issues', ['title' => '   ']);
        expect($invalidStatus)->toContain('422')
            ->and(json_decode((string) $invalidBody, true, flags: JSON_THROW_ON_ERROR)['error']['code'])->toBe('validation_failed')
            ->and(json_decode((string) $invalidBody, true, flags: JSON_THROW_ON_ERROR)['error']['fields']['title'])->toBe('title is required');

        $malformedContext = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => '{"title":',
            'ignore_errors' => true,
        ]]);
        $malformedBody = file_get_contents($base . '/api/issues', false, $malformedContext);
        expect($http_response_header[0] ?? '')->toContain('400')
            ->and(json_decode((string) $malformedBody, true, flags: JSON_THROW_ON_ERROR)['error']['code'])->toBe('invalid_json');

        [$patchedStatus, $patchedBody] = $request('PATCH', '/api/issues/' . $created['id'], ['status' => 'done']);
        expect($patchedStatus)->toContain('200')
            ->and(json_decode((string) $patchedBody, true, flags: JSON_THROW_ON_ERROR)['status'])->toBe('done');

        [$deletedStatus, $deletedBody] = $request('DELETE', '/api/issues/' . $created['id']);
        expect($deletedStatus)->toContain('204')->and($deletedBody)->toBe('');

        // FS04.2, FS04.3, FS05.3 and B04 tell the learner that a 204
        // carries no body and must not be handed to .json(). The fixture emitted
        // `null` after the status line for exactly as long as nobody ran this.
        [, $reDeletedBody] = $request('DELETE', '/api/issues/' . $created['id']);
        expect($reDeletedBody)->not->toBe(
            'null',
            'The Part 04 fixture put a JSON body on a bodiless response. Part 04 teaches '
            . 'the opposite in four places.',
        );

        // 404 and 405 are different facts about a request, and FS05.3 makes the learner
        // implement both. A fixture that answers 405 for an unknown path teaches the
        // learner that their typo was a method problem.
        [$missingStatus, $missingBody] = $request('GET', '/api/issues/ISS-9999');
        expect($missingStatus)->toContain('404')
            ->and(json_decode((string) $missingBody, true, flags: JSON_THROW_ON_ERROR)['error']['code'])->toBe('not_found');

        [$unroutedStatus] = $request('GET', '/api/nope');
        expect($unroutedStatus)->toContain('404');

        [$wrongMethodStatus] = $request('PUT', '/api/issues/ISS-42');
        expect($wrongMethodStatus)->toContain('405');

        [$detailStatus, $detailBody] = $request('GET', '/api/issues/ISS-42');
        expect($detailStatus)->toContain('200')
            ->and(json_decode((string) $detailBody, true, flags: JSON_THROW_ON_ERROR)['id'])->toBe('ISS-42');

        // The Part 03 lab serves on :5174 and this fixture allowed only :5173, so the
        // learner's first fetch in Part 04 died on CORS. Both loopback ports must work
        // and a foreign origin must not.
        $originHeaders = static function (string $origin) use ($base): string {
            $context = stream_context_create(['http' => [
                'method' => 'GET',
                'header' => "Origin: {$origin}\r\n",
                'ignore_errors' => true,
            ]]);
            file_get_contents($base . '/api/issues', false, $context);

            return implode("\n", $http_response_header ?? []);
        };

        foreach (['http://localhost:5173', 'http://localhost:5174', 'http://127.0.0.1:5173'] as $origin) {
            expect(str_contains($originHeaders($origin), 'Access-Control-Allow-Origin: ' . $origin))->toBeTrue(
                "The Part 04 fixture did not allow the local dev origin {$origin}. Part 03 serves "
                . 'on 5174; a fixture that only knows 5173 makes the learner debug the course.',
            );
        }

        expect(str_contains($originHeaders('http://evil.example'), 'Access-Control-Allow-Origin'))->toBeFalse(
            'The Part 04 fixture reflected a non-loopback origin. Reflecting any origin with '
            . 'Allow-Credentials is the bug this fixture should not be teaching.',
        );
    } finally {
        proc_terminate($process);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }
});
