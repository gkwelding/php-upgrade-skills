---
title: PHPUnit Configuration Migration
tags: phpunit, phpunit-xml, migrate-configuration, schema, source, coverage
---

## PHPUnit Configuration Migration

Each PHPUnit major can reject or warn about the previous major's `phpunit.xml`. Checked by migrating a PHPUnit 9 configuration with PHPUnit 10.5.65, 11.5.56 and 12.5.37.

### `--migrate-configuration`

```
vendor/bin/phpunit --migrate-configuration
```

Run it after the composer update, with the new PHPUnit installed. What it does to the file it loads (`phpunit.xml`, else `phpunit.xml.dist` / `phpunit.dist.xml`):

- Writes a backup next to it: `phpunit.xml.bak`. Delete it before committing; don't commit it.
- Rewrites the **whole file**: two-space indentation, all `<phpunit>` attributes on one line. Expect a large diff even for small changes. If the project's formatting matters, apply the real changes to the original by hand instead and use the migrated file as the reference.
- Moves `<coverage><include>` / `<exclude>` to `<source>` (10.0).
- Drops attributes that no longer exist (`verbose`, `convertDeprecationsToExceptions`, `convertErrorsToExceptions`, `processUncoveredFiles`, ...) and adds `cacheDirectory=".phpunit.cache"`.
- On PHPUnit 10 it pointed `xsi:noNamespaceSchemaLocation` at `https://schema.phpunit.de/10.5/phpunit.xsd`; 11 and 12 kept `vendor/phpunit/phpunit/phpunit.xsd`.

Then run the suite again and diff the old and new files: the migration only rewrites what it knows about.

### What the Migration Doesn't Do

- **`<listeners>`** (removed in 10): no automatic replacement. Each listener becomes a PHPUnit extension registered with `<extensions><bootstrap class="Vendor\Extension"/></extensions>`. Packages that hooked in as listeners need a release that ships a PHPUnit 10+ extension; check before the bump, and treat a missing one as a blocker (`shared/upgrade-loop.md`).
- **Removed behaviour**: dropping `convertDeprecationsToExceptions="true"` also drops the failing behaviour. To keep "deprecations fail the build", use `failOnDeprecation="true"` (and `failOnPhpunitDeprecation`, `failOnNotice`, `failOnWarning` as needed), or `--fail-on-deprecation` in CI. Ask before tightening.
- **Deprecation visibility**: `<source ignoreSuppressionOfDeprecations="true">` and friends (`shared/verification.md`) are opt-in.
- **`.phpunit.cache`** should be in `.gitignore`; the old `.phpunit.result.cache` file can go.

### Per Major

| Major | Config changes to check |
|---|---|
| 10 | `<coverage>` include/exclude to `<source>`; `<listeners>` removed; `forceCoversAnnotation` and `beStrictAboutCoversAnnotation` renamed `requireCoverageMetadata` / `beStrictAboutCoverageMetadata`; `printerClass`, `verbose`, `testdoxGroups`, `<text>` logging removed |
| 11 | Old cache configuration and `backupStaticAttributes` no longer accepted; include/exclude on `<coverage>` no longer accepted; a test can belong to only one configured test suite |
| 12 | `restrictDeprecations` on `<source>` removed |
| 13 | `cacheResult` deprecated for `recordTestRunHistory` (13.3); `executionOrder` values `duration`, `size`, `depends`, `no-depends` deprecated (13.2 to 13.4) |

`<php>` entries such as `<env>` survive the migration unchanged.
