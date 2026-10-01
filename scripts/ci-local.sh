#!/usr/bin/env bash
# Reproduces the committed CI candidate in a throwaway worktree.
set -euo pipefail
root="$(git -C "$(dirname "${BASH_SOURCE[0]}")/.." rev-parse --show-toplevel)"
copy="$(mktemp -d "${TMPDIR:-/tmp}/corex-ci.XXXXXX")"
git -C "$root" worktree add --detach "$copy" HEAD >/dev/null
trap 'git -C "$root" worktree remove --force "$copy"' EXIT
cd "$copy"
echo "ci-local: candidate $(git rev-parse HEAD), Laravel ${LARAVEL_VERSION:-default}"
if [[ "${LARAVEL_VERSION:-}" == 12.* ]]; then
    scripts/php84 composer require --dev 'pestphp/pest:^4.7' --no-update --no-interaction
fi
python3 scripts/distribution/stage-stancl-providers.py
update_args=(update --prefer-dist --no-interaction --no-progress)
if [[ -n "${LARAVEL_VERSION:-}" ]]; then
    update_args+=(--with "illuminate/contracts:$LARAVEL_VERSION")
fi
scripts/php84 composer "${update_args[@]}"
scripts/php84 composer --working-dir=packages/access update --prefer-dist --no-interaction --no-progress
if (( $# )); then
    "$@"
else
    scripts/php84 composer test:hermetic
    scripts/php84 composer test:arch
    scripts/php84 composer php-api
    scripts/php84 composer deps:analyse
    scripts/php84 composer analyse:tests-lint
fi
