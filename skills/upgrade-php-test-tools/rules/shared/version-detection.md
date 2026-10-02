---
title: Detecting Installed Versions
tags: composer, versions, php, detection
---

## Detecting Installed Versions

Plan from what is installed, not from what `composer.json` allows. `"laravel/framework": "^11.0"` says nothing about whether the lock holds 11.0 or 11.57, and Composer constraints written years ago are often wider or narrower than what the project actually runs.

### Read the Lock

```
composer show --locked --direct            # every direct dependency and its locked version
composer show --locked laravel/framework   # one package
composer outdated --direct --major-only    # direct dependencies with a newer major available
```

If `vendor/` is missing, run `composer install` first. It installs what the lock says and changes nothing. If there is no `composer.lock`, say so and ask before running `composer update`, which resolves fresh versions and is itself an upgrade.

Record the starting point for the report: PHP, framework, PHPUnit or Pest, and every direct dependency whose major will have to move with the framework (first-party packages such as Sanctum, Horizon, Doctrine bundles; anything with `laravel/` or `symfony/` in its requires).

### Which PHP Counts

The target must run on the PHP the project deploys to, not the PHP on this machine. Check, in order:

1. `config.platform.php` in `composer.json` (Composer resolves as if this were the PHP version)
2. `require.php` in `composer.json`
3. Dockerfile, Sail runtime, `.ddev/config.yaml`, CI matrix, `.php-version`, hosting config

`php -v` on the host is the weakest signal. If the target needs a newer PHP than the project's environment provides, stop and report it: upgrading PHP is its own step, and the user decides when it happens. Don't raise `config.platform.php` or `require.php` without asking, and never get past a PHP requirement with `--ignore-platform-reqs`.

### Minimum PHP per Major

From each package's own `composer.json`:

| Package | Major | Minimum PHP |
|---|---|---|
| `laravel/framework` | 10 / 11 / 12 / 13 | 8.1 / 8.2 / 8.2 / 8.3 |
| `symfony/framework-bundle` | 6.4 / 7.4 / 8.x | 8.1 / 8.2 / 8.4 |
| `phpunit/phpunit` | 9 / 10 / 11 / 12 / 13 | 7.3 / 8.1 / 8.2 / 8.3 / 8.4.1 |
| `pestphp/pest` | 2 / 3 / 4 / 5 | 8.1 (8.2 for the last 2.x releases) / 8.2 / 8.3 / 8.4 |

A framework major can also force a test-tool major and the other way round. Those links are listed in the framework and test-tool rules; check them before planning the order of steps.
