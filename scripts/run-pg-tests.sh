#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

if (( $# > 0 )); then
    exec vendor/bin/pest --group=pg "$@"
fi

# Keep PostgreSQL setup and application memory isolated between these suites.
# Transfer guards measure the process memory, including test harness allocations.
vendor/bin/pest --group=pg --exclude-testsuite=Golden,ImportExportPackage,RecurringTasksPackage
for suite in Golden ImportExportPackage RecurringTasksPackage; do
    vendor/bin/pest --group=pg --testsuite="$suite"
done
