---
name: upgrade-php-test-tools
description: "Upgrade PHPUnit (9 to 10 to 11 to 12 to 13) or Pest (2 to 3 to 4 to 5) one major at a time in any PHP project: reads installed versions from composer.lock, migrates phpunit.xml with --migrate-configuration, converts doc-comment annotations to attributes, makes data providers static, replaces withConsecutive(), returnValue() and stub expectations, fixes the PHPUnit 12.5 mock-without-expectations notices, uses Rector's composer-based PHPUnit set, runs the suite after each step and checks the test count so silently dropped tests are caught. Use when the user asks to upgrade, update or bump PHPUnit or Pest, or to fix PHPUnit deprecations, e.g. 'upgrade to PHPUnit 12', 'move our tests off annotations', 'withConsecutive is gone, fix it', 'bump Pest to v4', 'PHPUnit says metadata in doc-comments is deprecated'. Not for writing new tests, for upgrading Laravel or Symfony themselves (use upgrade-laravel / upgrade-symfony), or for other tools such as Codeception, PHPSpec or Behat."
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
---

# Upgrade PHP Test Tools

Upgrade PHPUnit or Pest to a newer major, one major at a time, without losing tests or weakening them.

**Target:** $ARGUMENTS

The target is a PHPUnit or Pest major (`phpunit 12`, `pest 4`) or "latest". If no target was given (the line above is empty or shows a literal `$ARGUMENTS` placeholder), detect the installed runner and version, propose the next major the project's PHP and framework allow, and ask before changing anything. If the user only asks to fix deprecations, do that on the installed version and stop.

## Quality Standards

- Tests are the product here. A step is green only when the test **count** matches the baseline as well as the results: PHPUnit 11 and 12 drop tests without failing (invalid data providers, `@test` annotations).
- Read the installed versions, the rules and the ChangeLog for each step before editing. Don't upgrade from memory.
- Clear the current major's deprecations under `--display-all-issues` before bumping.
- Keep assertions as strict as they were. Don't turn mocks with real expectations into stubs, don't loosen `assertSame()`, don't skip tests to get green.
- Follow the project's test conventions (naming, `#[Test]` vs `test` prefix, base classes, helper traits).
- Leave production code alone. If a test can only be fixed by changing production code (mocking a method that doesn't exist, a `final` class), report it.
- Ask before `composer require`, adding or removing packages, or changing `phpunit.xml` beyond what the migration requires.

---

## Step 1: Detect

1. **Versions** from `composer.lock` (`shared/version-detection.md`): PHP (deploy target), `phpunit/phpunit`, `pestphp/pest` and its plugins, `symfony/phpunit-bridge`, `mockery/mockery`, `brianium/paratest`, the framework and its version.
2. **Framework limits**: Laravel 10 can't use PHPUnit 11; Laravel 13 is the first Laravel on PHPUnit 13; Pest pins its PHPUnit (`pest/pest-upgrades.md`).
3. **Config**: which file PHPUnit loads (`phpunit.xml`, `phpunit.xml.dist`, `phpunit.dist.xml`), listeners or extensions, `<source>` settings.
4. **Baseline**: run the suite with `--display-all-issues` (PHPUnit 10+) and record tests, assertions, failures, skipped and every PHPUnit deprecation (`shared/verification.md`).

## Step 2: Plan

For each major between installed and target, list:

- Constraint changes (`phpunit/phpunit` or `pestphp/pest` plus plugins), and packages that may block (`composer why-not phpunit/phpunit ^12.0`)
- What the codebase uses that the step removes, found by grep: `withConsecutive`, `->at(`, `setMethods(`, `@test`, `@dataProvider`, non-static providers, `createStub(...)->expects`, `returnValue(`, `getMockForAbstractClass`, `addMethods(`, `assertContainsOnly(`, `isType(`, `<listeners>`
- Whether Rector is installed or should be proposed (`shared/rector.md`)

Print the plan, then continue. Stop and ask if it needs a new package, a removal, or a framework or PHP upgrade first.

## Step 3: Upgrade, One Major at a Time

Follow `shared/upgrade-loop.md`:

1. Clear the current major's PHPUnit deprecations.
2. Update the constraint (`composer update phpunit/phpunit --with-all-dependencies`, or Pest and its plugins together).
3. `vendor/bin/phpunit --migrate-configuration` (or `vendor/bin/pest --migrate-configuration`) and review the diff (`phpunit/configuration.md`). Delete the `.bak` file.
4. Rector `withComposerBased(phpunit: true)` dry run, review, apply. Fix the rest by hand from the version's rule file.
5. Run the suite with `--display-all-issues`. Compare results **and test count** with the baseline.
6. Commit. Next major.

## Step 4: Report

Use the report in `shared/upgrade-loop.md`, plus the test count before and after each step, PHPUnit deprecations and notices remaining, and any test that could only be fixed by changing production code.

---

## Troubleshooting

**Test count dropped after a step.** Look for "data provider ... is invalid" PHPUnit errors (11+) and leftover doc-comment metadata (12+). Fix them before going on.

**Composer conflict between Pest and PHPUnit.** A separate `phpunit/phpunit` constraint is fighting Pest's pin. Explain and ask before changing or removing it.

**Framework holds PHPUnit back** (Laravel 10 and PHPUnit 11; PHPUnit 13 below Laravel 13). Stop at the highest supported major and report; the framework upgrade comes first.

**PHP too old** for the target (PHPUnit 12 needs 8.3, 13 needs 8.4.1). Stop and report.

**Listener-based package with no PHPUnit 10+ extension.** A blocker: report it with the options.

**Hundreds of 12.5 mock notices.** Fix them with Rector's stub rules and by hand in the base classes first; they're notices, not failures, so ask whether to finish them in this step or a follow-up.

**Symfony `phpunit-bridge` projects.** `bin/phpunit` / `simple-phpunit` may install its own PHPUnit version, set by `SYMFONY_PHPUNIT_VERSION` (the 7.4 bridge defaults to 9.6 when it is unset). Check which PHPUnit actually runs before planning.

---

## Example

```
User: /upgrade-php-test-tools phpunit 12

Step 1: PHP 8.3 (CI matrix), phpunit/phpunit 10.5.20, Laravel 11.40, Mockery 1.6.
        phpunit.xml: PHPUnit 10.5 schema. Baseline: 640 tests, 1,902 assertions, all pass;
        9 PHPUnit deprecations (non-static data providers).

Step 2: Plan: 10 -> 11 -> 12. Grep: 131 @test, 38 @dataProvider, 9 non-static providers,
        4 createStub()->expects(), 22 ->will($this->returnValue()). Rector is installed.

Step 3: 11: composer update; --migrate-configuration (cache attributes only); Rector:
        attributes, static providers, willReturn(); 4 stubs with expects() -> createMock().
        640 tests, 0 PHPUnit deprecations. Commit "Upgrade to PHPUnit 11".
        12: composer update; migrate-configuration; grep finds no doc-comment metadata;
        640 tests pass; 58 "No expectations were configured" notices: 51 converted to
        createStub() by Rector, 7 in a shared base class fixed by hand.
        Commit "Upgrade to PHPUnit 12".

Step 4: Report: 10.5.20 -> 12.5.37, 640 tests before and after, 0 deprecations,
        0 notices.
```

---

## Rules Reference

Paths are relative to `./rules/`.

> `rules/shared/` is duplicated in every skill in this repository. CI keeps the copies identical.

### Always

- `shared/version-detection.md` - lock file, deploy PHP, minimum PHP per major
- `shared/upgrade-loop.md` - one major per step, composer commands, blockers, report
- `shared/verification.md` - `--display-all-issues`, silenced deprecations, test counts
- `shared/rector.md` - `withComposerBased(phpunit: true)`, `withConsecutive()` semantics
- `phpunit/configuration.md` - `--migrate-configuration`, what it doesn't migrate

### By Step

| Step | Read |
|---|---|
| PHPUnit 9 to 10 | `phpunit/9-to-10.md` |
| PHPUnit 10 to 11 | `phpunit/10-to-11.md` |
| PHPUnit 11 to 12 | `phpunit/11-to-12.md` |
| PHPUnit 12 to 13 | `phpunit/12-to-13.md` |
| Any Pest upgrade | `pest/pest-upgrades.md`, plus the PHPUnit file for the PHPUnit major it brings |
