#!/usr/bin/env bash
# Analyse each package against its own standalone Composer installation.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

run() {
    local dir="$1" result=0
    if [[ ! -f "packages/$dir/composer.json" ]]; then
        echo "Unknown package: $dir" >&2
        return 2
    fi
    if [[ "$dir" == tenancy || "$dir" == access ]]; then
        python3 scripts/distribution/stage-stancl-providers.py >/dev/null || result=$?
    fi
    if (( result == 0 )); then
        composer --working-dir="packages/$dir" update --no-interaction --no-progress --quiet || result=$?
    fi
    if [[ "$dir" == tenancy || "$dir" == access ]]; then
        # The staging tool registers three installing roots. Reconcile every one,
        # including after a failed staging or Composer invocation.
        composer config --unset repositories.stancl-providers || result=$?
        composer --working-dir=packages/tenancy config --unset repositories.stancl-providers || result=$?
        composer --working-dir=packages/access config --unset repositories.stancl-providers || result=$?
        python3 scripts/distribution/stage-stancl-providers.py --portable-locks >/dev/null || result=$?
    fi
    (( result == 0 )) || return "$result"
    vendor/bin/composer-dependency-analyser \
        --composer-json="packages/$dir/composer.json" \
        --config="packages/$dir/composer-dependency-analyser.php"
}

if [[ "${1:-}" == --all ]]; then
    status=0
    for manifest in packages/*/composer.json; do
        dir="$(basename "$(dirname "$manifest")")"
        echo "==> $dir"
        run "$dir" || status=1
    done
    exit "$status"
fi
run "${1:?usage: dependency-analyser.sh <package-dir>|--all}"
