# PHP Upgrade Skills

Agent skills for upgrading PHP projects across framework and test-tool major versions: Laravel 10 to 13, Symfony 6.4 to 8, PHPUnit 9 to 13 and Pest 2 to 5.

Models upgrading PHP projects tend to mix versions up: rewriting a Laravel 10 app into the Laravel 11 skeleton because "that's what 11 looks like", calling Rector set constants that no longer exist, bumping three majors in one `composer update`, or declaring a PHPUnit 12 suite green after it silently stopped running half its tests. These skills give the agent version-specific rules checked against the real packages, and a loop that moves one major at a time and proves each step with the test suite.

**Detect → Plan → One major per step (prepare, update, fix, test, commit) → Report**

## Skills

| Skill | What it does |
|---|---|
| `upgrade-laravel` | Laravel 10 → 11 → 12 → 13. Keeps the existing app structure, updates first-party packages, applies each guide's breaking changes (migration column changes, Carbon 3, UUIDv7, CSRF middleware rename, cache and session config traps), runs the suite with the deprecations Laravel hides during tests made visible. |
| `upgrade-symfony` | Symfony 6.4 → 7.x → 8.x, deprecation-driven: last minor first, deprecations cleared, then the major bump through `extra.symfony.require`, Flex recipe updates, and the UPGRADE file changes (annotations, native return types, changed config defaults, constraint named arguments, entity mapping, XML config removal). |
| `upgrade-php-test-tools` | PHPUnit 9 → 10 → 11 → 12 → 13 and Pest 2 → 3 → 4 → 5. `--migrate-configuration`, annotations to attributes, static data providers, `withConsecutive()` and `returnValue()` replacements, stubs vs mocks for the 12.5 notices, and a test-count check so dropped tests are caught. |

Each skill reads installed versions from `composer.lock`, uses Rector's composer-based sets where they apply, and stops with a report when a third-party package has no compatible release.

## What's Covered

**Shared (every skill):** installed versions and the deploy PHP rather than host PHP, minimum PHP per major, one major per step with its own commit, `composer why-not` / `show -a` for blockers, what to do (and not do) when a package blocks, Rector's `withComposerBased()` and why it must run after the composer update, which Rector changes are optional style, silenced deprecations in PHPUnit 10+ (`<source ignoreSuppressionOfDeprecations>`), Laravel dropping deprecations during tests (`LOG_DEPRECATIONS_WHILE_TESTING`), and the end-of-upgrade report.

**Laravel:** 10 → 11 (dependencies, package migrations no longer auto-loaded, `change()` dropping modifiers, `float()` / `double()` signatures, rate limits in seconds, new contract methods), Carbon 3 (`diffIn*` signed floats), optional move to the `bootstrap/app.php` structure (and the event discovery double-registration trap), 11 → 12 (Carbon 3 required, `HasUuids` v7, local disk root, SVG validation, container defaults), 12 → 13 (PHP 8.3, `PreventRequestForgery`, session `serialization` and cache `serializable_classes` defaults, cache prefix and cookie name fallbacks, queue event properties, Symfony 8 and polyfill side effects).

**Symfony:** last-minor-first workflow, `extra.symfony.require`, finding deprecations (tests, `debug:container --deprecations`), `patch-type-declarations`, Flex `recipes:update` review, 6.4 → 7 (annotations to attributes, return types, the 7.0 config default changes such as `http_method_override`, removed APIs), 7.4 → 8 (PHP 8.4, XML config removed, constraint options arrays to named arguments, Doctrine entity auto-mapping, `eraseCredentials()`, voter signature, console changes).

**PHPUnit / Pest:** config migration and what it misses, per-major removals and their replacements, the PHPUnit 11 invalid-provider and PHPUnit 12 ignored-annotation cases that drop tests without failing, the 12.5 mock notices and `#[AllowMockObjectsWithoutExpectations]`, PHPUnit 13 removals and `withParameterSetsInOrder()`, Pest's PHPUnit pinning, plugin majors and per-major changes, framework limits on PHPUnit versions.

## Install

### Claude Code plugin

```
/plugin marketplace add gkwelding/php-upgrade-skills
/plugin install php-upgrade-skills@php-upgrade-skills
```

Commands become `/php-upgrade-skills:upgrade-laravel <target>`, `/php-upgrade-skills:upgrade-symfony <target>` and `/php-upgrade-skills:upgrade-php-test-tools <target>`.

### Copy into a project or user skills folder

```
cp -r skills/upgrade-laravel skills/upgrade-symfony skills/upgrade-php-test-tools ~/.claude/skills/
# or per project:
cp -r skills/* .claude/skills/
```

Each skill folder is self-contained; copy only the ones you need.

### claude.ai

Build the packages, then upload `dist/upgrade-laravel.skill`, `dist/upgrade-symfony.skill` and `dist/upgrade-php-test-tools.skill` (Settings → Capabilities → Skills):

```
sh scripts/build-skills.sh
```

The script packages the committed files at `HEAD`; commit edits first.

## Usage

```
/upgrade-laravel 13
/upgrade-laravel                      # no target: detect, propose the next major, ask
/upgrade-symfony 7.4
/upgrade-symfony 8.1
/upgrade-php-test-tools phpunit 12
/upgrade-php-test-tools pest 4
```

Ask "what breaks if we go to Laravel 12?" to get the detection and plan without changes.

## Ground Rules the Skills Enforce

- One major per step, each with a green test run and its own commit
- Existing project structure and conventions come first: no slim-skeleton migration, attribute conversion or skeleton config copying unless asked
- Production behaviour never changes silently: changed defaults are kept explicitly or raised, and listed in the report
- `composer require`, adding or removing packages, and PHP version changes need the user's go-ahead
- A package with no compatible release stops the upgrade at the last green step, with a report; no forks, aliases, `--ignore-platform-reqs` or removals to get through
- Tests that were failing before the upgrade are reported, not fixed; tests are never loosened or skipped to get green, and test counts are compared

## Layout

```
skills/
├── upgrade-laravel/
│   ├── SKILL.md
│   └── rules/
│       ├── shared/              # shared rules (copy, CI-checked identical)
│       └── laravel/             # 10-to-11, 11-to-12, 12-to-13, carbon-3, slim-skeleton
├── upgrade-symfony/
│   ├── SKILL.md
│   └── rules/
│       ├── shared/              # shared rules (copy)
│       └── symfony/             # deprecation-workflow, flex-recipes, 6.4-to-7, 7-to-8
└── upgrade-php-test-tools/
    ├── SKILL.md
    └── rules/
        ├── shared/              # shared rules (copy)
        ├── phpunit/             # configuration, 9-to-10, 10-to-11, 11-to-12, 12-to-13
        └── pest/                # pest-upgrades
scripts/build-skills.sh          # packages dist/*.skill for claude.ai
evals/                           # with-vs-without-skill upgrade evals (see evals/README.md)
```

`rules/shared/` exists in every skill so each can be installed alone. CI (`.github/workflows/check-rules.yml`) fails if the copies differ. Check locally with:

```
diff -r skills/upgrade-laravel/rules/shared skills/upgrade-symfony/rules/shared
diff -r skills/upgrade-laravel/rules/shared skills/upgrade-php-test-tools/rules/shared
```

## Status

First version. The rules were written against the installed packages rather than from memory: `laravel/framework` 10.50, 11.57, 12.69 and 13.34 with their `laravel/laravel` skeletons, `symfony/framework-bundle` 6.4.47, 7.4.20 and 8.1.8 with `doctrine/doctrine-bundle` 3.3 and `symfony/flex` 2.11, `phpunit/phpunit` 9.6, 10.5, 11.5, 12.5 and 13.4, `pestphp/pest` 2.36, 3.8, 4.7 and 5.3, `rector/rector` 2.6 and `driftingly/rector-laravel` 2.6, plus the official upgrade guides and changelogs. Behaviour the rules describe (dropped tests, notices, Carbon results, deprecation visibility, config migration) was reproduced by running code against those versions. Expect gaps: upgrade guides list many low-impact changes, and the rules keep the ones agents get wrong.

## Evals

`evals/run.sh` upgrades small fixture projects twice with `claude -p`, once with the skill's slash command and once with a plain prompt, and scores both against the untouched baseline: suite green, target major installed (from `composer.lock`), test count kept (JUnit, so silently dropped tests show), deprecations and notices left, removed APIs still in the code, files changed and deleted, and cost, turns and minutes. A blind judge then compares the two diffs. Targets so far: a PHPUnit 9 library upgraded to PHPUnit 11, and a Laravel 10 app upgraded to 11.

```
evals/run.sh phpunit
```

See [evals/README.md](evals/README.md) for what each score means, how they were checked against deliberately bad upgrades, cost, Windows notes and the first result. Not covered yet: Symfony, Pest, PHPUnit 12+ and Laravel 12+, and the "stop at a blocking package" case.

## Licence

MIT. See [LICENSE](LICENSE).
