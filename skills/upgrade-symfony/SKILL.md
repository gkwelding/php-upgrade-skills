---
name: upgrade-symfony
description: "Upgrade a Symfony application across major versions (6.4 to 7.x to 8.x) the deprecation-driven way: reads installed versions from composer.lock, moves to the last minor, clears every deprecation there, then bumps the major through extra.symfony.require, updates Flex recipes, applies the breaking changes from the official UPGRADE files (annotations to attributes, native return types, changed config defaults, constraint named arguments, entity mapping, XML config removal), uses Rector where it applies, runs the tests after each step and stops with a report when a bundle has no compatible release. Use when the user asks to upgrade, update, migrate or bump Symfony, e.g. 'upgrade to Symfony 7.4', 'move this app to Symfony 8', 'fix the deprecations before we upgrade Symfony', 'what breaks going from 6.4 to 7'. Not for upgrading a single unrelated bundle, for Laravel (use upgrade-laravel), or for PHPUnit alone (use upgrade-php-test-tools)."
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
---

# Upgrade Symfony

Upgrade a Symfony application to a newer major by making it deprecation-free on the current major first, without changing what the application does.

**Target:** $ARGUMENTS

The target is a Symfony version (`7.4`, `8.1`) or "latest". If no target was given (the line above is empty or shows a literal `$ARGUMENTS` placeholder), detect the installed version, propose the next LTS or major, list the steps, and ask before changing anything. If the user only asks what would break or wants the deprecations fixed, stop after that part.

## Quality Standards

- Deprecations are the work list. Don't bump a major while the current minor still reports deprecations from the project's code.
- Read the installed versions, the rules and the UPGRADE file for each step before editing. Don't upgrade from memory.
- One major per step, each with a green test run and its own commit. Recipe updates get their own commits.
- Follow the project's conventions (YAML vs PHP config, attribute routing vs YAML routes, its directory layout).
- Never change production behaviour silently. Changed defaults (`http_method_override`, session cookies, UUID version, `UrlType` protocol) are kept explicitly in config or raised with the user, and listed in the report.
- Ask before `composer require`, adding or removing packages or bundles, or changing the PHP version.

---

## Step 1: Detect

1. **Versions** from `composer.lock` (`shared/version-detection.md`): PHP (deploy target), `symfony/framework-bundle`, `extra.symfony.require`, Flex, PHPUnit and whether `symfony/phpunit-bridge` is used, and every bundle (`doctrine/doctrine-bundle`, `api-platform/*`, `sonata-project/*`, `knplabs/*`, `friendsofsymfony/*`, ...).
2. **Config style**: YAML, PHP or XML in `config/`; annotations or attributes for routing, validation, serialisation and Doctrine mapping.
3. **How tests run**, whether they pass, and whether `phpunit.dist.xml` reports silenced deprecations (`shared/verification.md`).

## Step 2: Plan

List the steps (e.g. 6.2 to 6.4, clean 6.4, 6.4 to 7.4, clean 7.4, 7.4 to 8.1). For each:

- The constraints that change (`extra.symfony.require`, `symfony/*` constraints, bundles)
- Bundles that may block: `composer why-not symfony/framework-bundle 8.0.*`, and `composer show -a vendor/bundle <version>` for the newest release's requires
- The deprecations and breaking changes from the version's rule file that apply here (grep: `@Route`, `renderForm`, `MessageHandlerInterface`, `Assert\Length([`, `eraseCredentials`, `voteOnAttribute`, `.xml` config files, entity arguments on routes without `{id}`)

Print the plan, then continue. Stop and ask if it needs a new package, a removal or a PHP change.

## Step 3: Upgrade

Follow `shared/upgrade-loop.md` and `symfony/deprecation-workflow.md`:

1. Baseline test run.
2. Reach the last minor of the current major. Commit.
3. Remove deprecations: tests with `--display-all-issues`, `bin/console debug:container --deprecations`, Rector (`shared/rector.md`), `vendor/bin/patch-type-declarations` for 6.4. Commit.
4. Bump `extra.symfony.require` and `symfony/*` constraints, `composer update "symfony/*" --with-all-dependencies --dry-run`, then for real. `rm -rf var/cache/*`.
5. Apply the version's rule file. Update recipes one by one (`symfony/flex-recipes.md`).
6. Suite and deprecation check. Commit. Repeat for the next major.

If a bundle has no release for the target, stop at the last green step and report.

## Step 4: Report

Use the report in `shared/upgrade-loop.md`, plus: deprecations remaining (with their source package), recipes left outdated, and changed defaults kept or adopted.

---

## Troubleshooting

**Not a Symfony app** (no `symfony/framework-bundle`). Say so and stop. A library using Symfony components: the same deprecation-first process applies to the components it requires; bundles and libraries others extend must treat added return types as BC breaks (`deprecation-workflow.md`).

**No Flex / no `extra.symfony.require`.** Change each `symfony/*` constraint by hand. Skip recipe updates.

**`cache:clear` fails after the bump.** Expected when the container can't compile. `rm -rf var/cache/*`, then read the first error.

**Deprecations from vendor code.** Update that package within its current major if a release fixes them. If none does, note them and continue: they aren't the project's to fix, but check the package supports the target before bumping.

**Bundle blocks the bump.** Stop at the last green step. Report the bundle, what its latest release requires, and the options. Don't fork, alias, remove or replace it without the user.

**Composer blocked by PHP** (Symfony 8 needs 8.4). Stop at 7.4 and report.

**Tests were already failing.** Record them in the baseline and leave them.

---

## Example

```
User: /upgrade-symfony 7.4

Step 1: symfony/framework-bundle 6.4.12, extra.symfony.require "6.4.*", PHP 8.3 in
        Dockerfile, doctrine-bundle 2.13, PHPUnit 9.6 with phpunit-bridge. Routes and
        validation via annotations in 23 controllers / 9 DTOs; config in YAML.
        Baseline: 188 passed, 0 failed; bridge reports 41 deprecation notices.

Step 2: Plan: clean 6.4 -> 7.4. Blockers: none (doctrine-bundle 2.13 allows ^7.0).
        Deprecations: annotations, 6 missing return types (voters, a normalizer),
        Security\Core\Security in 3 services, renderForm() in 4 controllers.

Step 3: 6.4: Rector withAttributesSets(symfony, doctrine) + withComposerBased(symfony);
        patch-type-declarations; Security -> SecurityBundle\Security; render().
        0 deprecations from src/. Commit "Remove Symfony 6.4 deprecations".
        7.4: extra.symfony.require "7.4.*"; composer update "symfony/*" -W; recipes:update
        for framework-bundle, security-bundle, phpunit (each a commit). framework.yaml
        already sets http_method_override: false. Suite 188 passed.
        Commit "Upgrade to Symfony 7.4".

Step 4: Report: 6.4.12 -> 7.4.20; changed defaults: session cookie_samesite now lax
        (app didn't set it); 12 new 7.x deprecations in src/ (constraint option arrays,
        2 voters without $vote) to clear before Symfony 8; PHP 8.3 means 8.x waits for 8.4.
```

---

## Rules Reference

Paths are relative to `./rules/`.

> `rules/shared/` is duplicated in every skill in this repository. CI keeps the copies identical.

### Always

- `shared/version-detection.md` - lock file, deploy PHP, minimum PHP per major
- `shared/upgrade-loop.md` - one major per step, composer commands, blockers, report
- `shared/verification.md` - running tests, silenced deprecations in PHPUnit 10+
- `shared/rector.md` - `withComposerBased(symfony: true)`, which changes to keep
- `symfony/deprecation-workflow.md` - last minor first, `extra.symfony.require`, finding deprecations, return types
- `symfony/flex-recipes.md` - `recipes:update`, reviewing recipe diffs

### By Step

| Step | Read |
|---|---|
| 6.4 to 7.x | `symfony/6.4-to-7.md` |
| 7.4 to 8.x | `symfony/7-to-8.md` |
| Within 7.x (7.0 to 7.4) | `symfony/6.4-to-7.md` "Inside 7.x", then the `UPGRADE-7.x.md` files for each skipped minor |
