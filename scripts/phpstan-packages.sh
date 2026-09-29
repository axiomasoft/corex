#!/usr/bin/env bash
 # Analyses each package with its own config and baseline, then enforces the ratchet.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

if [[ "${1:-}" == --generate-baseline ]]; then
    task_config="${2:?supply the package directory}/phpstan.neon"
    [[ -f "$task_config" ]] || exit 2
    task_baseline="$(dirname "$task_config")/phpstan-baseline.neon"
    [[ -f "$task_baseline" ]] || printf "parameters:\n    ignoreErrors: []\n" > "$task_baseline"
    vendor/bin/phpstan analyse -c "$task_config" --memory-limit=2G --no-progress --generate-baseline="$task_baseline" --allow-empty-baseline
    exit $?
fi

status=0
for config in packages/*/phpstan.neon products/*/phpstan.neon phpstan.neon; do
    echo "==> ${config}"
    vendor/bin/phpstan analyse -c "$config" --memory-limit=2G --no-progress || status=1
done
php scripts/quality-ceiling-guard.php || status=1
exit "$status"
