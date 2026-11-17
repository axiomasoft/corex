#!/usr/bin/env bash
# Analyse each package against its own standalone Composer installation.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

run() {
    local dir="$1" result=0
    if [[ ! -f "$dir/composer.json" ]]; then
        echo "Unknown package: $dir" >&2
        return 2
    fi
    python3 scripts/composer-with-snapshots.py --working-dir="$dir" update --no-interaction --no-progress --quiet || result=$?
    (( result == 0 )) || return "$result"
    vendor/bin/composer-dependency-analyser \
        --composer-json="$dir/composer.json" \
        --config="$dir/composer-dependency-analyser.php"
}

if [[ "${1:-}" == --all ]]; then
    status=0
    for manifest in packages/*/composer.json products/*/composer.json; do
        dir="$(dirname "$manifest")"
        echo "==> $dir"
        run "$dir" || status=1
    done
    exit "$status"
fi
dir="${1:?usage: dependency-analyser.sh <package-dir>|--all}"
[[ "$dir" == */* ]] || dir="packages/$dir"
run "$dir"
