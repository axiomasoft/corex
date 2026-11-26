#!/usr/bin/env bash
#
# Contract-snapshot changelog check (ADR-008).
#
# Deterministic proxy for BC discipline: the job goes RED only when a diff touches
# `packages/<pkg>/resources/contracts/php-api.json` WITHOUT a matching `packages/<pkg>/CHANGELOG.md`
# change in the same diff. It performs no real BC analysis — that is post-v1.0
# SemVer enforcement (release phase). Until v1.0 every contract change must at
# least be announced in the package CHANGELOG.
#
# Usage: scripts/contract-guard.sh [BASE_REF]
#   BASE_REF defaults to $CONTRACT_GUARD_BASE, then to HEAD^ (previous commit).
set -euo pipefail

BASE_REF="${1:-${CONTRACT_GUARD_BASE:-}}"

# Fall back to HEAD^ when the base is empty, all-zeros (first push) or unresolvable.
if [[ -z "$BASE_REF" || "$BASE_REF" =~ ^0+$ ]] || ! git rev-parse --verify "${BASE_REF}^{commit}" >/dev/null 2>&1; then
  BASE_REF="$(git rev-parse HEAD^ 2>/dev/null || git rev-parse HEAD)"
fi

changed="$(git diff --name-only "${BASE_REF}...HEAD")"

# Packages whose generated PHP surface changed in this diff.
mapfile -t contract_pkgs < <(
  printf '%s\n' "$changed" \
    | grep -E '^packages/[^/]+/resources/contracts/php-api\.json$' \
    | sed -E 's#^packages/([^/]+)/.*#\1#' \
    | sort -u
)

if [[ ${#contract_pkgs[@]} -eq 0 ]]; then
  echo "contract-guard: no resources/contracts/php-api.json changes in ${BASE_REF}..HEAD — OK"
  exit 0
fi

fail=0
for pkg in "${contract_pkgs[@]}"; do
  if printf '%s\n' "$changed" | grep -E "^packages/${pkg}/CHANGELOG\.md$" >/dev/null; then
    echo "contract-guard: packages/${pkg}/resources/contracts/php-api.json changed WITH CHANGELOG — OK"
  else
    echo "contract-guard: ✗ packages/${pkg}/resources/contracts/php-api.json changed WITHOUT packages/${pkg}/CHANGELOG.md"
    fail=1
  fi
done

if [[ $fail -ne 0 ]]; then
  echo
  echo "contract-guard: a PHP surface change must carry a CHANGELOG entry in the same package"
  echo "                (ADR-008: document public contract changes before v1.0)."
  exit 1
fi

echo "contract-guard: all PHP surface changes accompanied by a CHANGELOG — OK"
