#!/usr/bin/env bash
# Keep resource-budget suites out of the large instrumented process; merge every lane.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
minimum="${1:---min=80}"
[[ "$minimum" =~ ^--min=([0-9]+)$ ]] || { echo 'coverage: expected --min=<integer>' >&2; exit 2; }
minimum="${BASH_REMATCH[1]}"
reports=build/root-coverage
mkdir -p "$reports"
vendor/bin/pest --exclude-testsuite=TransferPackage,TasksWorkflowPackage --order-by=default --coverage-php="$reports/main.cov"
vendor/bin/pest --testsuite=TransferPackage --order-by=default --coverage-php="$reports/transfer.cov"
vendor/bin/pest --testsuite=TasksWorkflowPackage --order-by=default --coverage-php="$reports/tasks-workflow.cov"
php scripts/merge-root-coverage.php "$minimum" "$reports/main.cov" "$reports/transfer.cov" "$reports/tasks-workflow.cov"
