---
title: The Upgrade Loop
tags: composer, workflow, blockers, one-major-at-a-time
---

## The Upgrade Loop

### One Major at a Time

Laravel 10 to 13 is three upgrades, PHPUnit 9 to 12 is three, Symfony 6.4 to 8 is two. Each gets its own composer change, its own fixes, its own green test run and its own commit. Don't jump straight to the final constraint: the upgrade guides, Rector's version-bound rules and third-party compatibility all assume the previous step is complete, and a failure is far easier to place when only one major moved.

Within one major, go straight to its latest minor (Laravel 11.x latest, Symfony 7.4, PHPUnit 11.5). Intermediate minors don't need their own step.

### Before the First Step

1. **Clean tree.** `git status` must be clean. If it isn't, ask: the user's uncommitted work must not get mixed into upgrade commits.
2. **Branch.** Work on a branch (e.g. `upgrade/laravel-12`) unless the user says otherwise.
3. **Baseline.** Run the full test suite before changing anything and record passed, failed, skipped and the deprecation count (`verification.md`). Tests that already fail are not upgrade failures: report them separately and don't fix them as part of the upgrade.

### Each Step

1. **Prepare on the current version.** Fix what the next major removes while the old code still runs: deprecations (mandatory for Symfony and PHPUnit), plus anything the upgrade guide marks as needing to change first.
2. **Plan the composer change.** List every constraint you will change in `composer.json`: the framework, the packages the official guide names, and the third-party packages that must move with it. Show the list. Ask before adding or removing any package; changing an existing constraint to the version the guide names is part of the agreed upgrade.
3. **Dry run.** `composer update <packages> --with-all-dependencies --dry-run` shows what would move. Read it: a package jumping a major you didn't plan is a finding.
4. **Update.** The same command without `--dry-run`. Update only the packages in the plan plus their dependencies, not the whole lock, unless the user asks for a full `composer update`.
5. **Apply the code changes** from the version's rule file and the official guide. Use Rector where it applies (`rector.md`).
6. **Clear caches** (`php artisan optimize:clear`, `rm -rf var/cache/*`) before running anything.
7. **Verify** (`verification.md`): run the suite, compare with the baseline, read the deprecation output.
8. **Commit** the step on its own, e.g. `Upgrade to Laravel 12`.

### When Composer Refuses

Read the error. It names the package and the constraint that can't be met.

```
composer why-not laravel/framework ^12.0     # which installed packages forbid the target
composer show -a vendor/package              # every released version of a blocker
composer show -a vendor/package 3.0.0        # what that release requires
```

- **A compatible release of the blocker exists.** Plan it as part of the step. If it is a major bump of that package, read its upgrade notes and say so in the plan.
- **No compatible release exists.** Stop this step. Don't fork the package, edit `vendor/`, add an `inline alias`, use `--ignore-platform-reqs`, swap in an alternative package or remove it to get through. Report: the package, its installed version, what its latest release requires, and the options (wait for a release, use an open pull request or fork the user maintains, replace it). The user chooses.
- **"affected by security advisories".** Composer is refusing versions with known advisories. Pick a patched version. Don't add `audit.ignore` entries or set `audit.block-insecure` to `false` without asking.

### Report

At the end, or when blocked:

- Start and end versions (PHP, framework, test runner, every direct dependency that changed major)
- One line per completed step and its commit
- What blocked and why, with the evidence (`why-not` output, the blocker's latest requires)
- Behaviour changes the upgrade introduced that the tests don't cover (from the guide's "Likelihood Of Impact" items and the rule files), so the user can check them
- Optional modernisation you deliberately didn't do (e.g. Laravel 11 slim skeleton, Rector style sets)
- Test results against the baseline, and deprecations still reported
