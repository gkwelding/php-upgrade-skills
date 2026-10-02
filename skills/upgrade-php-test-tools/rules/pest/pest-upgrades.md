---
title: Pest Major Upgrades
tags: pest, phpunit, plugins, snapshots, datasets
---

## Pest Major Upgrades

Source: the official Pest upgrade guide (2.x to 5.x), checked against `pestphp/pest` 2.36.1, 3.8.7, 4.7.8 and 5.3.0.

### Pest Pins PHPUnit

Every Pest major is built on one PHPUnit major and pins it tightly through `require` and `conflict`:

| Pest | PHPUnit | PHP | Example pin (latest release) |
|---|---|---|---|
| 2 | 10 | 8.1 (8.2 for the last 2.x releases) | 2.36.1: `phpunit/phpunit ^10.5.63`, conflicts with `>10.5.63` |
| 3 | 11 | 8.2 | 3.8.7: `^11.5.56`, conflicts with `>11.5.56` |
| 4 | 12 | 8.3 | 4.7.8: `^12.5.33`, conflicts with `>12.5.33` |
| 5 | 13 | 8.4 | 5.3.0: `^13.3.6`, conflicts with `>13.3.6` |

Consequences:

- In a Pest project the PHPUnit upgrade **is** the Pest upgrade. Don't add or bump `phpunit/phpunit` separately: a higher constraint than Pest's conflict makes Composer fail, a lower one holds Pest back. Pest 2's guide says to remove `phpunit/phpunit` from `composer.json`; ask before removing it.
- Everything in the PHPUnit rule file for that major applies to code PHPUnit sees: `tests/TestCase.php`, PHPUnit-style classes mixed into the suite, `$this->createMock()` / `createStub()` and expectations inside Pest closures, `phpunit.xml`.
- Doc-comment metadata isn't a Pest concern (Pest tests are closures), but PHPUnit-style classes in the same suite lose their `@test` and `@dataProvider` methods on PHPUnit 12 exactly as in a pure PHPUnit project (`phpunit/11-to-12.md`).

### Plugins Move With Pest

All Pest-maintained plugins take the same major: `pestphp/pest-plugin-laravel`, `pest-plugin-arch`, `pest-plugin-livewire`, ... `^3.0` with Pest 3, `^4.0` with Pest 4, `^5.0` with Pest 5. Third-party Pest plugins can block; treat them like any blocker.

### 1 to 2

- PHP 8.1. `pestphp/pest ^2.0`; Laravel: `nunomaduro/collision ^7.0` (needs Laravel 10).
- Remove `brianium/paratest` and `pestphp/pest-plugin-parallel` (parallel is built in) and `pestphp/pest-plugin-global-assertions` (archived; use `$this->assertSame()` or `expect()`). Ask before removing packages.
- Faker plugin: `Pest\Faker\faker()` became `Pest\Faker\fake()`.
- `vendor/bin/pest --migrate-configuration` for `phpunit.xml` (PHPUnit 10 schema).
- `tap()` deprecated in favour of `defer()`.
- Datasets declared in a test file are scoped to that file; shared ones go in `tests/Pest.php` or `tests/Datasets.php`. Bound datasets with a single argument need the parameter type-hinted.

### 2 to 3

- PHP 8.2. `pestphp/pest ^3.0`; Laravel: `nunomaduro/collision ^8.0` (needs Laravel 11).
- `tap()` removed: `->defer(fn () => ...)`.
- `toHaveMethod()` / `toHaveMethods()` are architecture expectations now and take a class name, not an object: `expect($object::class)->toHaveMethod('method')`.
- PHPUnit 11 changes (`phpunit/10-to-11.md`).

### 3 to 4

- PHP 8.3. `pestphp/pest ^4.0`.
- Snapshot names changed: if the suite uses `toMatchSnapshot()`, run `vendor/bin/pest --update-snapshots` once and review the snapshot diff in git before committing (the regenerated snapshots must match the old content; only names should change).
- `pestphp/pest-plugin-watch` and `pestphp/pest-plugin-faker` are archived and not supported by Pest 4. Faker: use the framework's `fake()` helper or Faker directly. Ask before removing the plugins.
- PHPUnit 12 changes (`phpunit/11-to-12.md`).

### 4 to 5

- PHP 8.4. `pestphp/pest ^5.0`. Pest 5.3 conflicts with `laravel/boost <2.6.0`.
- PHPUnit 13 changes (`phpunit/12-to-13.md`).

### Laravel

Laravel 12's guide moves Pest to `^3.0`, Laravel 13's to `^4.0`. Pest 5 needs PHP 8.4 and PHPUnit 13; Laravel 13 is the first Laravel that supports PHPUnit 13.
