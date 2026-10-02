---
title: Rector for Version Upgrades
tags: rector, automation, composer-based, phpunit, symfony, laravel
---

## Rector for Version Upgrades

Rector automates the mechanical part of an upgrade. Use it where a rule set exists for the step, always as a dry run first, and review the diff like any other change. Checked against `rector/rector` 2.6 and `driftingly/rector-laravel` 2.6.

### Install Only With Permission

If `rector/rector` isn't in `require-dev`, ask before `composer require --dev rector/rector`. `driftingly/rector-laravel` is a community package, not a Laravel one; say so when asking. If the user declines, make the changes by hand from the rule files.

### Use `withComposerBased()`, Not Old Set Names

Current Rector has no per-version PHPUnit or Symfony set constants (`PHPUnitSetList::PHPUNIT_100` and `SymfonySetList::SYMFONY_70` style names are gone). The upgrade rules live in one composer-based set per package, and each rule is bound to the package version its target API exists in:

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests'])
    ->withComposerBased(phpunit: true, symfony: true, laravel: true);
```

Pass only the packages the project uses. `laravel: true` does nothing unless `driftingly/rector-laravel` is installed. `LaravelSetList::LARAVEL_110` and `LaravelLevelSetList::UP_TO_LARAVEL_110` style constants still exist in rector-laravel but are marked deprecated in favour of `withComposerBased(laravel: true)`.

For annotation-to-attribute conversion use `->withAttributesSets(phpunit: true)` or `->withAttributesSets(symfony: true, doctrine: true)`.

### Run It After the Composer Update

Rector decides which rules are active from `vendor/composer/installed.json`, so it sees the version currently installed, not the one you are aiming for. The PHPUnit `withConsecutive()` rewrite, static data providers and annotation-to-attribute rules only switch on once PHPUnit 10 is installed. So, per step:

1. `composer update` to the next major
2. `vendor/bin/rector process --dry-run`
3. Review, then `vendor/bin/rector process`
4. Run the code style fixer the project uses; Rector output doesn't follow house style

`vendor/bin/rector composer-based` lists the rules the installed versions activate. Use it to check what a run will touch.

### What to Keep From the Diff

The composer-based sets mix required fixes with optional modernisation, and rules stay active from their version upwards. Keep the changes the upgrade needs; leave out style changes the user didn't ask for, or ask. Known cases:

- **rector-laravel, Laravel 13**: dozens of rules turn model, job, command and resource properties (`$fillable`, `$hidden`, `$table`, `$tries`, `$signature`, ...) into PHP attributes. The properties still work in Laravel 13; this is a style change across the whole codebase.
- **rector-laravel, Laravel 11 and 12**: `$casts` property to `casts()` method (from 11.0), `scopeX()` methods to `#[Scope]` attributes (from 12.4). Both old forms still work.
- **PHPUnit**: from PHPUnit 11 the set also rewrites `createMock()` to `createStub()` where no expectation is configured. That's in line with PHPUnit's direction, but check the diff in shared helpers and base classes.

### Check Semantics, Not Just Syntax

Rector's `withConsecutive()` replacement asserts each argument with `assertSame()`. `withConsecutive()` compared with `IsEqual` (loose `==`). For scalars that is fine; for objects it changes the test from "equal" to "the same instance", and tests passing fresh-but-equal objects start failing. Where that happens, switch those assertions to `assertEquals()` rather than changing production code.
