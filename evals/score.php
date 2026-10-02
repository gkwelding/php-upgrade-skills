<?php

// Objective scores for one upgrade run, read from files run.sh saved. Prints one CSV fragment:
// passed,tests,dropped,failures,errors,skipped,issues,installed,reached,leftovers,files_changed,files_deleted
//
// Usage: php evals/score.php <junit.xml> <phpunit output> <composer.lock> <package> <target major>
//                            <baseline test count> <leftover regex> <leftover dirs, comma-separated> <name-status file>
// Paths are relative to the scratch app (native Windows php can't open MSYS /tmp paths).

[, $junit, $output, $lock, $package, $major, $before, $regex, $dirs, $nameStatus] = $argv + array_fill(0, 10, '');

// Counts come from the JUnit log, never from parsing console output (laravel/pao turns that into JSON).
$suite = is_file($junit) ? @simplexml_load_file($junit) : false;
$suite = $suite ? $suite->testsuite : null;
$tests = $suite ? (int) $suite['tests'] : '';
$failures = $suite ? (int) $suite['failures'] : '';
$errors = $suite ? (int) $suite['errors'] : '';
$skipped = $suite ? (int) $suite['skipped'] : '';
$passed = $suite && $tests > 0 && $failures + $errors === 0 ? 1 : 0;
$dropped = $suite && $before !== '' ? (int) $before - $tests : '';

// Deprecations, notices and warnings: JUnit doesn't carry them on PHPUnit 10+, the summary line does
// ("Tests: 15, Assertions: 21, Deprecations: 1, PHPUnit Deprecations: 9.").
$issues = '';
if (preg_match_all('/^(?:OK|Tests:|FAILURES|ERRORS|WARNINGS).*$/m', (string) @file_get_contents($output), $lines)) {
    preg_match_all('/(?:PHPUnit )?(?:Deprecations|Notices|Warnings): (\d+)/', implode("\n", $lines[0]), $m);
    $issues = array_sum($m[1]);
}

// Installed version of the package, from the lock rather than composer.json.
$installed = '';
foreach (['packages', 'packages-dev'] as $key) {
    foreach (json_decode((string) @file_get_contents($lock), true)[$key] ?? [] as $p) {
        if ($p['name'] === $package) {
            $installed = ltrim($p['version'], 'v');
        }
    }
}
$reached = $installed !== '' && (int) explode('.', $installed)[0] === (int) $major ? 1 : 0;

// Code the target major removes or deprecates, still present after the run.
$leftovers = '';
if ($regex !== '') {
    $leftovers = 0;
    foreach (array_filter(explode(',', $dirs)) as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $leftovers += preg_match_all("~{$regex}~", (string) file_get_contents($file->getPathname()));
            }
        }
    }
}

// git diff --name-status against the baseline, composer.lock excluded.
$changed = $deleted = 0;
foreach (file($nameStatus, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $changed++;
    $deleted += $line[0] === 'D' ? 1 : 0;
}

echo implode(',', [$passed, $tests, $dropped, $failures, $errors, $skipped, $issues, $installed, $reached, $leftovers, $changed, $deleted]), "\n";
