#!/usr/bin/env bash
# Exercise each package's own suite with the monorepo's installed dependencies.
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"
status=0

for manifest in packages/*/composer.json products/*/composer.json; do
    package="$(dirname "$manifest")"
    [[ -f "$package/phpunit.xml" ]] || continue
    printf 'Testing %s\n' "$package"
    vendor/bin/pest \
        --configuration="$package/phpunit.xml" \
        --bootstrap="$root/vendor/autoload.php" \
        --test-directory="$package/tests" \
        --exclude-group=pg \
        --exclude-group=meilisearch \
        "$@" || status=1
done

exit "$status"
