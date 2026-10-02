---
title: Verifying Each Step
tags: tests, deprecations, phpunit, laravel, symfony, verification
---

## Verifying Each Step

A step is done when the suite is back to its baseline and you have read the deprecation output. "The tests pass" is not enough if the runner was hiding deprecations.

### Where Tests Run

Use the same runner and prefix the project uses (`vendor/bin/sail test`, `ddev exec vendor/bin/phpunit`, `docker compose exec <service> ...`, a `composer test` or Makefile target). The host PHP may not be the project's PHP. Ask before starting containers; don't change compose files or images.

### Show Every Issue

PHPUnit 10.5+ hides details by default. Run with:

```
vendor/bin/phpunit --display-all-issues
```

`--display-all-issues` covers deprecations, notices, warnings and PHPUnit's own deprecations and notices. PHPUnit 9 has no `--display-*` options.

### Deprecations PHPUnit Can't See

**Silenced deprecations.** Symfony's `trigger_deprecation()` and most libraries call `@trigger_error(..., E_USER_DEPRECATED)`. PHPUnit 10+ ignores `@`-silenced deprecations unless the `<source>` element says otherwise:

```xml
<source ignoreSuppressionOfDeprecations="true">
    <include>
        <directory>src</directory>
    </include>
</source>
```

The attribute belongs on `<source>`, not on `<phpunit>`. The current Symfony PHPUnit recipe ships this setting; older projects often don't have it. Adding it is a change to `phpunit.xml`, so ask, or pass a temporary copy with `-c` for the check and leave the project file alone.

**Laravel swallows deprecations in tests.** Laravel's error handler replaces PHPUnit's once the application boots, and while running tests it drops deprecations unless `LOG_DEPRECATIONS_WHILE_TESTING` is set (`HandleExceptions::shouldIgnoreDeprecationErrors()`, Laravel 10 to 13). A Feature test that hits deprecated code reports nothing, even with `--display-deprecations`. To see them:

```
LOG_DEPRECATIONS_WHILE_TESTING=true LOG_DEPRECATIONS_CHANNEL=single vendor/bin/phpunit
# then read storage/logs/laravel.log
```

Or, for one test, `$this->withoutDeprecationHandling();` turns each deprecation into an exception (a 500 in an HTTP test unless exception handling is also off).

**Symfony** kernel and web tests do reach PHPUnit: FrameworkBundle registers its error handler without replacing PHPUnit's. Also run `bin/console debug:container --deprecations` for deprecations raised while compiling the container, which tests can miss.

### Compare With the Baseline

- New failures: caused by this step. Fix them before the next step.
- Failures from the baseline: still not yours. Leave them and report.
- Tests that vanished: count the tests, not just the failures. A drop in the test count after a PHPUnit major usually means tests PHPUnit no longer discovers (doc-comment `@test` in PHPUnit 12).
- Deprecations: the ones the next major will remove are the work list for the next step's preparation.

### Fixing Failures

Fix the code the upgrade broke: application code that used a removed API, tests that used a removed PHPUnit API, config that changed keys. Don't:

- Loosen assertions or skip tests to get green
- Change behaviour silently to match a new default. If a framework default changed (session serialisation, UUID version, a form widget default), either keep the old behaviour explicitly in config or ask; and list it in the report
- Pin a package to an older patch release or add a `conflict` entry to dodge an error, without asking
