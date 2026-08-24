<?php

declare(strict_types=1);

/**
 * Grouped as `course-shell` so it can be excluded where the course's own services
 * are not provisioned.
 *
 * This test shells out and runs the *entire* course suite — Docker containers, real
 * `npm install`, PostgreSQL. That is the right thing to do once, in a job built for
 * it. Running it inside a four-way PHP version matrix means four concurrent copies
 * of that workload on machines that were only set up to run the framework, and the
 * first CI run showed what that costs: one of the four jobs failed with `proc_open`
 * returning "could not start", while the identical work passed on the other three.
 * A fork failure under resource pressure is not a PHP version defect, but it is
 * indistinguishable from one in the summary line.
 */
test('the optional DALT suite runs with the normal project test command', function () {
    if (getenv('DALT_NESTED_TEST_RUN') === '1') {
        expect(true)->toBeTrue();

        return;
    }

    if (!is_dir(base_path('.dalt/tests'))) {
        expect(true)->toBeTrue();

        return;
    }

    if (!is_file(base_path('.dalt/vendor/autoload.php'))) {
        $this->markTestSkipped('DALT dependencies are not installed; run composer install --working-dir=.dalt.');
    }

    $environment = getenv();
    $environment = is_array($environment) ? [...$environment, 'DALT_NESTED_TEST_RUN' => '1'] : null;

    $process = proc_open(
        [
            PHP_BINARY,
            base_path('vendor/bin/pest'),
            base_path('.dalt/tests'),
            '--bootstrap=' . base_path('.dalt/bootstrap.php'),
            '--colors=never',
        ],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        base_path(),
        $environment,
        ['bypass_shell' => true],
    );

    expect($process)->not->toBeFalse();
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    expect(proc_close($process))->toBe(0, $stdout . $stderr);
})->group('course-shell');
