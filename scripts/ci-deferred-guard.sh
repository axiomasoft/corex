#!/usr/bin/env bash
# D44 / PLAN-CLOSE-CI: skip CI-heavy local carriers while product path runs (P9.8–P11).
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
flag="${root}/scripts/.plan-close-deferred"
if [[ -f "$flag" ]]; then
  echo "Plan-close deferred (D44/D45): ${flag} present — remote publish + CI only at PLAN-CLOSE-CI."
  exit 0
fi
exec "$@"
