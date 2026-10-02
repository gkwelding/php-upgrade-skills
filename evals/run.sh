#!/usr/bin/env bash
# Upgrade each fixture project with and without the skills, then score the result: suite green,
# target major installed (composer.lock), tests kept (JUnit count against the baseline), remaining
# deprecations/notices, removed APIs left in the code, files changed and deleted, cost/turns/minutes.
#
# Usage: evals/run.sh [all|phpunit|laravel]
# Env:   MODEL       model for claude -p (default: your claude default)
#        BUDGET_USD  spend cap per claude run (default 5)
#        WORK        scratch directory for the fixture apps (default evals/.work)
#        JUDGE       0 skips the blind side-by-side review of the diffs at the end (evals/judge.sh)
set -euo pipefail

root=$(cd "$(dirname "$0")/.." && pwd)
work=${WORK:-$root/evals/.work}
budget=${BUDGET_USD:-5}
which=${1:-all}
stamp=$(date +%Y%m%d-%H%M%S)
results=$work/results/$stamp
mkdir -p "$results"
csv=$results/results.csv
echo "target,variant,passed,tests,dropped,failures,errors,skipped,issues,installed,reached,leftovers,files_changed,files_deleted,cost_usd,turns,minutes" > "$csv"

# Laravel 10 and 11 (every release) and some PHPUnit majors carry security advisories, so Composer 2.9
# refuses to resolve them. This turns that off for the scaffold and for the claude runs (they inherit
# it) without writing audit config into the project the agent sees. Scratch apps only.
export COMPOSER_NO_SECURITY_BLOCKING=1
# Scoring runs PHPUnit without laravel/pao's agent JSON output; claude -p itself still gets pao.
export PAO_DISABLE=1 COMPOSER_NO_INTERACTION=1

# Per target: the package and major the upgrade must reach, the prompts, and a regex for code the
# target major removed or deprecated (counted in leftover_dirs after the run).
configure() {
    case $1 in
        phpunit)
            package=phpunit/phpunit major=11 leftover_dirs=tests
            leftover_regex='withConsecutive|setMethods\(|->at\(|returnValue\(|onConsecutiveCalls\(|assertObjectHasAttribute|@(test|dataProvider|testWith|covers|group)\b'
            with_prompt="/upgrade-php-test-tools phpunit 11"
            without_prompt="Upgrade PHPUnit in this project to version 11." ;;
        laravel)
            package=laravel/framework major=11 leftover_dirs= leftover_regex=
            with_prompt="/upgrade-laravel 11"
            without_prompt="Upgrade this Laravel application to Laravel 11." ;;
        *) return 1 ;;
    esac
}

scaffold() {
    local name=$1 dir=$work/$1
    if [ ! -d "$dir/.git" ]; then
        rm -rf "$dir"
        case $name in
            phpunit)
                mkdir -p "$dir"
                cp -r "$root/evals/fixtures/phpunit/." "$dir/"
                (cd "$dir" && composer update --quiet) ;;
            laravel)
                composer create-project --quiet "laravel/laravel:^10" "$dir"
                # Laravel 13 skeletons ship CLAUDE.md/AGENTS.md telling agents to install Laravel Boost; a run that follows it adds ~75 files and skews the scores.
                rm -f "$dir/CLAUDE.md" "$dir/AGENTS.md"
                # Laravel 10 needs doctrine/dbal for ->change(); Laravel 11 doesn't (a 10-to-11 trap).
                (cd "$dir" && composer require --quiet doctrine/dbal:^3)
                cp -r "$root/evals/fixtures/laravel/." "$dir/"
                sed -i -E 's#<!-- (<env name="DB_(CONNECTION|DATABASE)"[^>]*>) -->#\1#' "$dir/phpunit.xml" ;;
        esac
        (cd "$dir" && git init -q -b main && git config user.name eval && git config user.email eval@localhost && git config core.autocrlf false && git config core.longpaths true \
            && echo /.claude/ >> .git/info/exclude && git add -A && git commit -qm baseline && git tag baseline)
    fi
}

# Back to the baseline commit on main, other branches gone, vendor/ as the baseline lock says.
reset_app() {
    (cd "$1" && git checkout -qf -B main baseline \
        && { git branch --format='%(refname:short)' | { grep -vx main || true; } | xargs -r git branch -qD; } \
        && git clean -qfdx -e vendor/ -e .env && composer install --quiet)
}

# Runs the suite and appends one row. Paths are relative to the app so a native Windows php can open them.
score() {
    local name=$1 variant=$2 dir=$work/$1 out=../results/$stamp/$1-$2 before=${3:-}
    (cd "$dir" && git add -A 2>/dev/null \
        && git diff --cached baseline --name-status -- . ':!.claude' ':!composer.lock' > "$out.files.txt" \
        && git diff --cached baseline -- . ':!.claude' ':!composer.lock' > "$out.diff")
    # auto_prepend_file is cleared because Laravel Herd sets one.
    (cd "$dir" && php -d auto_prepend_file= vendor/bin/phpunit --colors=never --log-junit "$out.junit.xml" > "$out.phpunit.txt" 2>&1) || true
    local row run
    row=$(cd "$dir" && php "$root/evals/score.php" "$out.junit.xml" "$out.phpunit.txt" composer.lock "$package" "$major" \
        "$before" "$leftover_regex" "$leftover_dirs" "$out.files.txt")
    run=$(cd "$dir" && php -r '
        $j = json_decode((string) @file_get_contents($argv[1]), true) ?? [];
        file_put_contents($argv[2], $j["result"] ?? "");
        echo isset($j["total_cost_usd"]) ? round($j["total_cost_usd"], 2) : "", ",", $j["num_turns"] ?? "", ",",
            isset($j["duration_ms"]) ? round($j["duration_ms"] / 60000, 1) : "";
    ' "$out.claude.json" "$out.claude.txt")
    echo "$name,$variant,$row,$run" >> "$csv"
}

run_one() {
    local name=$1 variant=$2 dir=$work/$1 prompt
    local out=../results/$stamp/$name-$variant
    reset_app "$dir"
    if [ "$variant" = with ]; then
        mkdir -p "$dir/.claude/skills"
        cp -r "$root"/skills/* "$dir/.claude/skills/"
        prompt=$with_prompt
    else
        prompt=$without_prompt
    fi

    echo "== $name-$variant"
    # The prompt goes in on stdin: as an argument, Git Bash on Windows rewrites "/upgrade-laravel" into a
    # file path, and the usual fix (MSYS_NO_PATHCONV=1) would be inherited by Claude's own Bash tool,
    # where it breaks the composer and vendor/bin wrapper scripts.
    (cd "$dir" && printf '%s' "$prompt" | claude -p --setting-sources project ${MODEL:+--model "$MODEL"} \
        --append-system-prompt "You are running non-interactively: nobody can answer questions. Where you would ask, choose the safest option, say so in your final message, and carry on." \
        --max-budget-usd "$budget" --no-session-persistence --permission-mode acceptEdits \
        --output-format stream-json --verbose \
        --allowedTools "Read,Write,Edit,Glob,Grep,Bash(php:*),Bash(composer:*),Bash(vendor/bin/phpunit:*),Bash(vendor/bin/rector:*),Bash(git:*),Bash(grep:*),Bash(ls:*),Bash(diff:*),Bash(rm phpunit.xml.bak),Bash(rm -f phpunit.xml.bak)" \
        > "$out.transcript.jsonl" 2> "$out.claude.err") || echo "   claude exited non-zero, see $results/$name-$variant.claude.err"
    # The transcript has every tool call (and shows whether the skill loaded); its last line is the
    # result object that --output-format json would print.
    (cd "$dir" && { grep '"type":"result"' "$out.transcript.jsonl" || true; } | tail -n 1 > "$out.claude.json")
    score "$name" "$variant" "$baseline_tests"
}

for name in phpunit laravel; do
    [ "$which" = all ] || [ "$which" = "$name" ] || continue
    configure "$name"
    scaffold "$name"
    # Baseline row: the untouched project, scored the same way. Its test count is what "dropped" compares to.
    reset_app "$work/$name"
    score "$name" baseline
    baseline_tests=$(tail -n 1 "$csv" | cut -d, -f4)
    for variant in without with; do
        run_one "$name" "$variant"
    done
done

echo
column -s, -t < "$csv" 2>/dev/null || cat "$csv"
echo
echo "Logs, diffs and results.csv: $results"

[ "${JUDGE:-1}" = 0 ] || bash "$root/evals/judge.sh" "$results"
