#!/usr/bin/env bash
set -euo pipefail
for arg in "$@"; do
    if [[ "$arg" == *TaskCompletion* ]]; then
        exec vendor/bin/pest --testsuite=TasksWorkflowPackage --exclude-group=pg --order-by=default "$@"
    fi
done
vendor/bin/pest --exclude-group=pg --exclude-group=meilisearch --exclude-testsuite=SearchMeilisearchPackage,TasksWorkflowPackage,Golden --order-by=default "$@"
vendor/bin/pest --testsuite=TasksWorkflowPackage --exclude-group=pg --order-by=default
