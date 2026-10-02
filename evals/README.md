# Evals

Checks whether an upgrade done with a skill ends up better than one done from a plain prompt: on the target version, with every test still running and passing, no removed or deprecated APIs left behind, and no more change than the upgrade needs.

For each target, `run.sh` builds a small project at the starting version, commits it as `baseline`, and asks `claude -p` to upgrade it twice, resetting to the baseline (and `composer install`ing its lock) in between:

| Variant | Prompt |
|---|---|
| `without` | A plain request, e.g. "Upgrade PHPUnit in this project to version 11." |
| `with` | The skill's slash command, e.g. `/upgrade-php-test-tools phpunit 11`, with this repo's `skills/` copied into the project's `.claude/skills/` |

Both get the same appended system prompt saying nobody can answer questions, so the skills' "ask before ..." points become "choose the safest option and say so".

## What's scored

Each run, and the untouched baseline, is scored the same way (`score.php`) and gets one row in `results.csv`:

| Column | Meaning | Why |
|---|---|---|
| `passed` | 1 if the suite ran at least one test with no failures or errors (JUnit log) | The minimum bar |
| `tests`, `dropped` | Tests in the JUnit log, and the baseline count minus that | PHPUnit 11 skips tests with a non-static data provider and PHPUnit 12 ignores `@test`, without failing. A green suite with fewer tests is the classic silent upgrade failure |
| `failures`, `errors`, `skipped` | From the JUnit log | |
| `issues` | Deprecations, notices and warnings (PHP's and PHPUnit's) from PHPUnit's summary line | What the next major will break. The JUnit log doesn't carry them on PHPUnit 10+ |
| `installed`, `reached` | The package's version in `composer.lock`, and 1 if its major is the target | `composer.json` says what's allowed, the lock what's installed |
| `leftovers` | Matches of a per-target regex for APIs the target major removed or deprecated (PHPUnit: `withConsecutive`, `setMethods(`, `returnValue(`, `onConsecutiveCalls(`, `assertObjectHasAttribute`, doc-comment `@test`/`@dataProvider`/`@testWith`/`@covers`/`@group`) | Code that runs today and breaks on the next bump |
| `files_changed`, `files_deleted` | Files changed against the baseline (commits included, `composer.lock` and `.claude/` excluded) | Minimality. A deleted file in a Laravel 10 to 11 upgrade usually means an unrequested move to the slim skeleton |
| `cost_usd`, `turns`, `minutes` | From the `claude -p` result | |

At the end `judge.sh` asks one tool-less `claude -p` per target to compare the two diffs, shown as A and B in random order, on `correctness` (complete for the target, behaviour unchanged, no tests dropped or weakened), `minimality`, `idiomatic` (what the target version's docs recommend) and `overall`. Verdicts are mapped back to `with`/`without` in `judge.csv` with a one-sentence reason each. `JUDGE=0` skips it; `evals/judge.sh evals/.work/results/<timestamp>` re-judges a saved run.

Every run also keeps the full `claude` transcript (`<target>-<variant>.transcript.jsonl`, stream-json), its final message, the diff, the PHPUnit output and the JUnit log.

## Targets

| Target | Start → goal | What it exercises |
|---|---|---|
| `phpunit` | A plain PHP library on PHPUnit 9.6 → PHPUnit 11 | 15 tests across three classes: `withConsecutive()`, `setMethods()`, `->will($this->returnValue())` and `onConsecutiveCalls()`, `assertObjectHasAttribute()`, `expects()` on a `createStub()`, a non-static `@dataProvider` (four rows), `@test`-only methods, `@testWith`, class-level `@covers` and `@group`, and a PHPUnit 9 `phpunit.xml` (`verbose`, `convertDeprecationsToExceptions`, `<coverage processUncoveredFiles>`). Bumping the constraint and nothing else scores 11 tests (4 dropped), 3 errors, 10 PHPUnit deprecations and 14 leftovers |
| `laravel` | `laravel/laravel` 10 skeleton with fixture code → Laravel 11 | 10 feature tests over code that keeps working on 11 only if the upgrade handles it: middleware registered in `app/Http/Kernel.php`, a `renderable()` in `app/Exceptions/Handler.php`, a `$casts` property, `diffInDays()` (Carbon 3 returns a signed float), `new Limit($key, 2, 5)` (minutes on 10, seconds on 11) and a `->nullable()->change()` migration (11 drops the `default` and `unsigned` it doesn't repeat; 10 needed `doctrine/dbal` for it). Bumping the constraints and nothing else scores 4 failures |

Fixtures live in `fixtures/<target>/`: the whole project for `phpunit`, the files added to or replacing the skeleton's for `laravel`. A target is a `configure` entry (package, target major, prompts, leftover regex) and a `scaffold` entry in `run.sh`.

### Proving the scores can fail

Before trusting a score, it was run on a deliberately bad result. A stub `claude` (a shell script on `PATH`) applied a constraint-only upgrade for `without` and a correct one for `with`:

```
target   variant   passed  tests  dropped  failures  errors  issues  installed  reached  leftovers  files_changed  files_deleted
phpunit  baseline  1       15              0         0       1       9.6.37     0        14         0              0
phpunit  without   0       11     4        0         3       10      11.5.56    1        14         1              0
phpunit  with      1       15     0        0         0       0       11.5.56    1        0          5              0
laravel  baseline  1       10              0         0       1       10.50.3    0                   0              0
laravel  without   0       10     0        0         9       3       11.57.0    1                   2              1
laravel  with      1       10     0        0         0       3       11.57.0    1                   4              0
```

(The bad `laravel` run also deleted `app/Exceptions/Handler.php`. The 1 and 3 `issues` on Laravel are PHP 8.5 deprecating `PDO::MYSQL_ATTR_SSL_CA` in the skeleton's and the framework's `config/database.php`; they are there in every run.)

## Running

Needs `composer`, `git` and the `claude` CLI. The first run builds the projects into `evals/.work/` (gitignored) and reuses them afterwards; delete `evals/.work/<target>` to rebuild after changing its fixtures or to pick up newer releases.

```
evals/run.sh             # both targets
evals/run.sh phpunit     # one target
MODEL=claude-sonnet-5-5 BUDGET_USD=3 evals/run.sh laravel
```

**Cost:** 2 `claude -p` runs per target, each capped by `BUDGET_USD` (default 5), plus one judge call per target capped by `JUDGE_BUDGET_USD` (default 1). Claude can edit files and run `php`, `composer`, `vendor/bin/phpunit`, `vendor/bin/rector`, `git`, `grep`, `ls`, `diff` and `rm phpunit.xml.bak` in the scratch project.

**Composer advisories:** every Laravel 10 and 11 release, and some PHPUnit releases, carry security advisories, and Composer 2.9 refuses to resolve them. `run.sh` exports `COMPOSER_NO_SECURITY_BLOCKING=1` for the scaffold and the `claude` runs, so the project the agent sees has no audit config of its own. Scratch projects only.

**Noise:** one run per variant is one sample. Run a few times before trusting a difference, and compare like with like (same model, same package releases).

### Windows (Git Bash)

- The prompt is passed on stdin. As an argument, Git Bash rewrites `/upgrade-php-test-tools ...` into a file path; `MSYS_NO_PATHCONV=1` fixes that but is inherited by Claude's own Bash tool, where the `composer` wrapper then can't find `composer.phar`.
- `php` is given paths relative to the scratch project (`run.sh` `cd`s into it), because native Windows PHP can't open MSYS `/tmp/...` paths.
- Scoring runs PHPUnit with `-d auto_prepend_file=` (Laravel Herd sets one), `--colors=never` and `PAO_DISABLE=1`: `laravel/pao`, shipped by Laravel 13 skeletons, turns PHPUnit output into JSON when it detects an agent. Counts come from the JUnit log either way.
- The scratch repos set `core.autocrlf=false` and `core.longpaths=true`; `git clean` keeps `vendor/` and `.env`, so its patterns are written without a leading `/` (Git Bash would rewrite that too).
- To test the plumbing with a stub `claude`, put its directory on `PATH` with `$(cygpath -u ...)` (a `C:/` entry breaks on the colon) and check `command -v claude` points at the stub before running.

## First result (one sample)

`phpunit` target, default model (Opus 5.5), `BUDGET_USD=3`, 2026-10-02:

```
target   variant   passed  tests  dropped  failures  errors  issues  installed  reached  leftovers  files_changed  files_deleted  cost_usd  turns  minutes
phpunit  baseline  1       15              0         0       1       9.6.37     0        14         0              0
phpunit  without   1       15     0        0         0       0       11.5.56    1        0          5              0              0.37      16     2.1
phpunit  with      1       15     0        0         0       0       11.5.56    1        0          5              0              0.82      24     2.2
```

Both variants reached PHPUnit 11.5 with all 15 tests running and passing, no issues and no leftovers, so on this fixture the objective scores don't separate them. The skill run cost more because it went one major at a time (separate commits for 10 and 11, as the skill says) and kept its own count at each step. The diffs differ in the details. The blind judge preferred `with` on minimality (it swapped `setMethods()` for `onlyMethods()` instead of rewriting the mock, and left `composer.json`'s description alone) and on idiom (the documented `numberOfInvocations()` pattern for `withConsecutive()`). It preferred `without` on correctness and overall, because the skill run left `xsi:noNamespaceSchemaLocation` pointing at the 10.5 schema. It had migrated the config on 10 and didn't re-check it on 11, and its report mentions this. Both added `failOnDeprecation="true"` in place of the dropped `convertDeprecationsToExceptions`. Judge cost $0.24.

Read this as one sample on an easy fixture. A harder `phpunit` target (to 12, where `@test` silently drops tests) and the `laravel` target are the next runs worth paying for.

Note: `claude -p` loads your user-level plugins, skills and hooks in both variants. Keep that the same between runs you compare.
