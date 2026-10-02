---
name: upgrade-laravel
description: "Upgrade a Laravel application one major version at a time across Laravel 10, 11, 12 and 13: reads the installed versions from composer.lock, plans each step, updates composer constraints and first-party packages, applies the breaking changes from the official upgrade guides (migrations, Carbon 3, UUIDv7, CSRF middleware rename, cache and session config), uses Rector where it helps, runs the test suite after each step and stops with a report when a third-party package has no compatible release. Use when the user asks to upgrade, update, migrate or bump Laravel or laravel/framework, e.g. 'upgrade this app to Laravel 12', 'move us from Laravel 10 to 13', 'what breaks if we go to Laravel 11', 'bump laravel/framework'. Not for Lumen, not for upgrading PHPUnit or Pest on their own (use upgrade-php-test-tools), and not for upgrading only a single Laravel package such as Livewire or Filament."
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
---

# Upgrade Laravel

Upgrade a Laravel application to a newer major, one major at a time, without changing what the application does.

**Target:** $ARGUMENTS

The target is a Laravel major (`12`, `13`) or "latest". If no target was given (the line above is empty or shows a literal `$ARGUMENTS` placeholder), detect the installed version, propose the next major as the target, list the steps up to Laravel 13, and ask before changing anything. If the user only wants to know what would break, do Steps 1 and 2 and stop.

## Quality Standards

- Read the installed versions, the rules and the official guide for each step before editing. Don't upgrade from memory: Laravel 11, 12 and 13 changed defaults in ways that compile fine and behave differently.
- One major per step, each with a green test run and its own commit.
- Follow the project's existing structure and conventions. Don't migrate to the Laravel 11 slim skeleton, convert properties to attributes or copy new skeleton config files unless asked.
- Never change production behaviour silently. Where a new default would change behaviour (session serialisation, UUID version, storage root, cache prefixes), keep the old behaviour explicitly or ask, and list it in the report.
- Ask before `composer require`, adding or removing packages, or changing the PHP version.

---

## Step 1: Detect

1. **Versions** from `composer.lock` (`shared/version-detection.md`): PHP (deploy target, not host), `laravel/framework`, PHPUnit or Pest, `nesbot/carbon`, and every direct dependency that is tied to the framework major (first-party Laravel packages, Livewire, Inertia, Filament, Spatie packages, anything requiring `illuminate/*`).
2. **Structure**: Laravel 10 style (`app/Http/Kernel.php`) or 11+ style (`bootstrap/app.php` with `Application::configure()`). Both are valid on every target.
3. **How tests run** (Sail, DDEV, Docker, host) and whether the suite passes now.

## Step 2: Plan

For each major between installed and target, list:

- The composer constraints that change (framework, the packages the guide names, third-party packages that must move)
- Packages that may block, checked with `composer why-not laravel/framework ^N.0` and `composer show -a vendor/package` for the newest release's requires
- The breaking changes from the version's rule file that apply to this codebase (grep for them: `->change()`, `float(`, `diffIn`, `HasUuids`, `VerifyCsrfToken`, `JobAttempted`, ...)
- Whether PHPUnit or Pest must move first or can follow (`10-to-11.md`, `11-to-12.md`, `12-to-13.md`)

Print the plan, then continue. Stop and ask if the plan needs a new package, a package removal or a PHP change.

## Step 3: Upgrade, One Major at a Time

Follow `shared/upgrade-loop.md` for each step:

1. Baseline test run (first step only).
2. Prepare on the current version: grep and fix what the guide says breaks.
3. Edit constraints, `composer update ... --with-all-dependencies --dry-run`, then the real update.
4. Apply the version's rule file. Rector (`shared/rector.md`) if installed or approved.
5. `php artisan optimize:clear`, run the suite (`shared/verification.md`), including the deprecation check Laravel hides during tests.
6. Commit. Next major.

If Composer can't resolve because a package has no release for the target, stop at the last green step and report.

## Step 4: Report

Use the report in `shared/upgrade-loop.md`. Always list behaviour changes the tests don't cover, so the user can check them before deploying.

---

## Troubleshooting

**Not a Laravel app** (no `laravel/framework` in the lock). Say so and stop. Lumen: say this skill doesn't cover it.

**Already on the target.** Say so. Offer the next major or the deferred items (Carbon 3, structure migration).

**Composer blocked by a third-party package.** Stop at the last green step. Report the package, what its latest release requires and the options. Don't fork, alias, remove or replace it without the user.

**Composer blocked by PHP.** The target needs a newer PHP than the project's platform (`shared/version-detection.md`). Stop and report.

**Tests were already failing.** Record them in the baseline, don't fix them, and keep them out of the upgrade's pass/fail judgement.

**Suite passes but behaviour may have changed.** Expected: many Laravel 11 to 13 changes are silent. List each applicable one in the report with where it applies.

**No tests.** Say the upgrade can't be verified by tests. Do the upgrade only if the user agrees, and list every applicable breaking change for manual checking.

---

## Example

```
User: /upgrade-laravel 13

Step 1: composer.lock: PHP platform 8.3 (Dockerfile php:8.3-fpm), laravel/framework 11.44,
        laravel/sanctum 4.0, livewire 3.5, nesbot/carbon 2.72, phpunit 11.5. Laravel 10
        structure (app/Http/Kernel.php). Tests run through Sail; baseline 312 passed, 2 failed
        (pre-existing, ReportExportTest).

Step 2: Plan: 11 -> 12 -> 13.
        12: framework ^12.0; Carbon 3 required (14 diffIn* call sites, 3 in app/Billing);
            HasUuids on Order (v7 from now on; nothing checks the version); local disk defined
            in config/filesystems.php (unaffected).
        13: framework ^13.0, tinker ^3.0; PHP 8.3 ok; VerifyCsrfToken used in 4 tests;
            JobAttempted listener reads $exceptionOccurred; CACHE_PREFIX not set (fallback
            changes). No blockers: livewire 3.5 allows illuminate ^13.

Step 3: 12: composer update; (int) ... diffInDays(..., true) at 14 call sites; suite 312/2.
            Commit "Upgrade to Laravel 12".
        13: composer update; PreventRequestForgery in tests; $event->exception !== null in
            the listener; CACHE_PREFIX, REDIS_PREFIX, SESSION_COOKIE set to their 12 values in
            .env.example and noted for the deploy env. Suite 312/2. Commit "Upgrade to Laravel 13".

Step 4: Report: 11.44 -> 13.34; Carbon 2 -> 3; behaviour to check: new Order IDs are UUIDv7,
        cache prefix kept explicitly; not done: slim skeleton, property-to-attribute changes.
```

---

## Rules Reference

Paths are relative to `./rules/`.

> `rules/shared/` is duplicated in every skill in this repository. CI keeps the copies identical.

### Always

- `shared/version-detection.md` - lock file, deploy PHP, minimum PHP per major
- `shared/upgrade-loop.md` - one major per step, composer commands, blockers, report
- `shared/verification.md` - running tests, deprecations Laravel hides during tests
- `shared/rector.md` - `withComposerBased(laravel: true)`, which changes to keep

### By Step

| Step | Read |
|---|---|
| 10 to 11 | `laravel/10-to-11.md`, `laravel/carbon-3.md` |
| 11 to 12 | `laravel/11-to-12.md`, `laravel/carbon-3.md` if Carbon is still 2 |
| 12 to 13 | `laravel/12-to-13.md` |
| User asks to adopt the `bootstrap/app.php` structure | `laravel/slim-skeleton.md` |
