#!/usr/bin/env bash
# Keep resource-budget suites out of the large instrumented process; merge every lane.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
minimum="${1:---min=80}"
[[ "$minimum" =~ ^--min=([0-9]+)$ ]] || { echo 'coverage: expected --min=<integer>' >&2; exit 2; }
minimum="${BASH_REMATCH[1]}"
reports=build/root-coverage
mkdir -p "$reports"
vendor/bin/pest --exclude-testsuite=ImportExportPackage,RecurringTasksPackage --order-by=default --coverage-xml="$reports/main"
vendor/bin/pest --testsuite=ImportExportPackage --order-by=default --coverage-xml="$reports/import-export"
vendor/bin/pest --testsuite=RecurringTasksPackage --order-by=default --coverage-xml="$reports/recurring-tasks"
python3 scripts/merge-root-coverage.py "$minimum" "$reports/main" "$reports/import-export" "$reports/recurring-tasks"
