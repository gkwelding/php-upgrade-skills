#!/usr/bin/env bash
# Blind side-by-side review of the two upgrades of each target in a results folder.
# The diffs are shown as A and B in random order; verdicts are mapped back to with/without.
#
# Usage: evals/judge.sh <results folder>     (run.sh calls this at the end unless JUDGE=0)
# Env:   MODEL             model for claude -p (default: your claude default)
#        JUDGE_BUDGET_USD  spend cap per comparison (default 1)
set -euo pipefail

cd "$1" # relative paths from here also work with a native Windows php
budget=${JUDGE_BUDGET_USD:-1}

criteria=(correctness minimality idiomatic overall)
props=""
for c in "${criteria[@]}"; do
    props+="\"$c\":{\"type\":\"object\",\"properties\":{\"winner\":{\"type\":\"string\",\"enum\":[\"A\",\"B\",\"tie\"]},\"reason\":{\"type\":\"string\"}},\"required\":[\"winner\",\"reason\"]},"
done
required=$(printf '"%s",' "${criteria[@]}")
schema="{\"type\":\"object\",\"properties\":{${props%,}},\"required\":[${required%,}]}"

echo "target,criterion,winner,a_was,reason" > judge.csv

tail -n +2 results.csv | cut -d, -f1 | sort -u | while read -r name; do
    [ -f "$name-with.diff" ] && [ -f "$name-without.diff" ] || continue
    case $name in
        phpunit) goal="Upgrade a plain PHP library's test suite from PHPUnit 9.6 to PHPUnit 11." ;;
        laravel) goal="Upgrade a Laravel 10 application to Laravel 11." ;;
    esac

    # Random order, so position can't favour a variant.
    if (( RANDOM % 2 )); then a=with b=without; else a=without b=with; fi
    {
        cat <<EOF
Two agents independently did the same task on the same project: $goal
Below are their diffs against the starting commit (composer.lock left out). Judge only the diffs.

For each criterion, pick A, B or tie, and give one sentence that cites something specific in the diffs:
- correctness: the upgrade is complete for the target version and behaviour is unchanged: no removed or deprecated APIs left, no tests dropped, weakened or skipped, no silent behaviour changes from new framework defaults.
- minimality: only the changes the upgrade needs; no unrequested restructuring, reformatting or style changes.
- idiomatic: the replacements are what the target version's documentation recommends.
- overall: the change you would rather merge.

A larger diff is not better by itself.

=== Diff A ===
EOF
        cat "$name-$a.diff"
        echo
        echo "=== Diff B ==="
        cat "$name-$b.diff"
    } > "$name.judge-prompt.txt"

    echo "== judging $name (A = $a)"
    claude -p --tools "" --output-format json --json-schema "$schema" --max-budget-usd "$budget" \
        --no-session-persistence ${MODEL:+--model "$MODEL"} \
        < "$name.judge-prompt.txt" > "$name.judge.json" 2> "$name.judge.err" || echo "   judge failed, see $name.judge.err"

    php -r '
        [, $json, $a, $b, $name] = $argv;
        $verdict = json_decode((string) @file_get_contents($json), true)["structured_output"] ?? [];
        $out = fopen("judge.csv", "a");
        foreach ($verdict as $criterion => $v) {
            $winner = ["A" => $a, "B" => $b][$v["winner"]] ?? "tie";
            fputcsv($out, [$name, $criterion, $winner, $a, $v["reason"]], escape: "");
        }
    ' "$name.judge.json" "$a" "$b" "$name"
done

php -r '
    $rows = array_map(fn ($l) => str_getcsv($l, escape: ""), array_slice(file("judge.csv", FILE_IGNORE_NEW_LINES), 1));
    $tally = [];
    foreach ($rows as [, $criterion, $winner]) {
        $tally[$criterion][$winner] = ($tally[$criterion][$winner] ?? 0) + 1;
    }
    printf("\n%-12s %5s %8s %4s\n", "criterion", "with", "without", "tie");
    foreach ($tally as $criterion => $t) {
        printf("%-12s %5d %8d %4d\n", $criterion, $t["with"] ?? 0, $t["without"] ?? 0, $t["tie"] ?? 0);
    }
'
echo
echo "Verdicts with reasons: $1/judge.csv"
