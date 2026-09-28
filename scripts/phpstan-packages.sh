#!/usr/bin/env bash
 # Analyses each package with its own config and baseline, then enforces the ratchet.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

status=0
for config in packages/*/phpstan.neon phpstan.neon; do
    echo "==> ${config}"
    vendor/bin/phpstan analyse -c "$config" --memory-limit=2G --no-progress || status=1
done
php scripts/quality-ceiling-guard.php || status=1
exit "$status"
